<?php

/**
 * Throwaway harness behind .github/tasks/09.performancce-test-plan.md.
 *
 * It walks the real login flow through the HTTP kernel and then measures the three states of the
 * customer list (active, archived, trash) with and without search, on the first and on a later
 * page. Every request records its total duration, its query count and the duration of each query,
 * so the numbers come from the real list requests and not from the fixture alone.
 *
 * Usage (inside the container, against the disposable database):
 *
 *     php storage/app/perf/customer-list-bench.php [runs] [warmups]
 */

use App\Models\Customer;
use App\Queries\Customers\CustomerListQuery;
use App\Queries\ListQueryBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$runs = (int) ($argv[1] ?? 20);
$warmups = (int) ($argv[2] ?? 3);

/** @var list<array{sql: string, bindings: array, time: float}> $queries */
$queries = [];
DB::listen(function ($event) use (&$queries): void {
    $queries[] = ['sql' => $event->sql, 'bindings' => $event->bindings, 'time' => (float) $event->time];
});

/**
 * Send one request through the kernel and return the response.
 *
 * @param  array<string, string>  $parameters
 * @param  array<string, string>  $cookies
 */
$send = function (string $uri, string $method = 'GET', array $parameters = [], array $cookies = []) use ($kernel) {
    return $kernel->handle(Request::create($uri, $method, $parameters, $cookies));
};

/**
 * Session cookie of a response, to send it back on the following requests.
 *
 * @return array{name: string, value: string}|null
 */
$sessionCookie = function ($response): ?array {
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === config('session.cookie')) {
            return ['name' => $cookie->getName(), 'value' => $cookie->getValue()];
        }
    }

    return null;
};

/**
 * Replace the bindings of a query by its literal values, so the statement can be explained.
 *
 * @param  array{sql: string, bindings: array, time: float}  $query
 */
$interpolate = static function (array $query): string {
    foreach ($query['bindings'] as $binding) {
        $value = is_int($binding) || is_float($binding)
            ? (string) $binding
            : "'" . str_replace("'", "''", (string) $binding) . "'";
        $query['sql'] = preg_replace('/\?/', $value, $query['sql'], 1);
    }

    return $query['sql'];
};

// --- Log in through the real form, so every measured request is an authenticated one. -----------

// Repeated runs log in again from the same address and Fortify throttles that.
cache()->flush();

$loginPage = $send('/login');
preg_match('/name="_token" value="([^"]+)"/', (string) $loginPage->getContent(), $matches);
$token = $matches[1] ?? null;
$cookie = $sessionCookie($loginPage);

if ($token === null || $cookie === null) {
    exit("Could not read the login form token or the session cookie.\n");
}

$user = DB::table('users')->orderBy('id')->first();
$login = $send('/login', 'POST', [
    'email' => $user->email,
    'password' => 'password',
    '_token' => $token,
], [$cookie['name'] => $cookie['value']]);
$cookie = $sessionCookie($login);

if ($cookie === null) {
    exit("The login did not return a session cookie.\n");
}

$cookies = [$cookie['name'] => $cookie['value']];
$probe = $send('/customers', cookies: $cookies);

if ($probe->getStatusCode() !== 200) {
    exit('The customer list answered ' . $probe->getStatusCode() . ' instead of 200: authentication failed.');
}

$volumes = [
    'customers' => Customer::withTrashed()->count(),
    'projects' => DB::table('projects')->count(),
    'epics' => DB::table('epics')->count(),
    'epic comments' => DB::table('epic_comments')->count(),
];

echo 'Authenticated as ' . $user->email . '. Debug: ' . var_export((bool) config('app.debug'), true) . PHP_EOL;
echo 'Volumes: ' . json_encode($volumes) . PHP_EOL . PHP_EOL;

// --- Scenarios ---------------------------------------------------------------------------------

$terms = static function (string $state): array {
    $name = match ($state) {
        'archived' => Customer::query()->where('active', false)->value('name'),
        'trash' => Customer::onlyTrashed()->value('name'),
        default => Customer::query()->where('active', true)->value('name'),
    };

    return [
        'none' => [],
        'all' => ['search' => 'Perf'],
        'one' => ['search' => substr((string) $name, -5)],
        'none-match' => ['search' => 'zzz-no-match'],
    ];
};

$uris = ['active' => '/customers', 'archived' => '/customers/archived', 'trash' => '/customers/trash'];
$counts = app(CustomerListQuery::class)->stateCounts();
$perPage = ListQueryBase::PER_PAGE;
$lastPages = [
    'active' => (int) ceil($counts['active'] / $perPage),
    'archived' => (int) ceil($counts['archived'] / $perPage),
    'trash' => (int) ceil($counts['trashed'] / $perPage),
];

$scenarios = [];

foreach ($uris as $state => $uri) {
    foreach ($terms($state) as $variant => $parameters) {
        $pages = [1];

        if ($lastPages[$state] > 1) {
            $pages[] = 2;
        }

        foreach ($pages as $page) {
            $scenarios[] = [
                'label' => sprintf('%s / %s / page %d', $state, $variant, $page),
                'uri' => $uri . '?' . http_build_query(['page' => $page] + $parameters),
            ];
        }
    }
}

echo 'States: active ' . $counts['active'] . ', archived ' . $counts['archived'] . ', trashed ' . $counts['trashed'] .
    ' (page size ' . $perPage . '; last pages ' . json_encode($lastPages) . ').' . PHP_EOL;

// --- Measure -----------------------------------------------------------------------------------

/** @var array<string, array{durations: list<float>, counts: list<int>, sql: array<string, list<float>>}> $results */
$results = [];
$plans = [];

/**
 * @param  array{label: string, uri: string, reference: bool}  $scenario
 */
$request = function (array $scenario) use ($send, $cookies, &$queries): array {
    $queries = [];
    $started = hrtime(true);
    $response = $send($scenario['uri'], cookies: $cookies);
    $elapsed = (hrtime(true) - $started) / 1e6;

    if ($response->getStatusCode() !== 200) {
        exit($scenario['label'] . ' answered ' . $response->getStatusCode() . PHP_EOL);
    }

    return [$elapsed, $queries];
};

foreach ($scenarios as $scenario) {
    $request($scenario);
}

foreach ($scenarios as $scenario) {
    for ($run = 0; $run < $warmups + $runs; $run++) {
        [$elapsed, $recorded] = $request($scenario);

        if ($run < $warmups) {
            $plans[$scenario['label']] = $recorded;

            continue;
        }

        $results[$scenario['label']]['durations'][] = $elapsed;
        $results[$scenario['label']]['counts'][] = count($recorded);

        foreach ($recorded as $query) {
            $results[$scenario['label']]['sql'][$query['sql']][] = $query['time'];
        }
    }
}

/** @param list<float> $values */
$percentile = static function (array $values, float $percentile): float {
    sort($values);
    $index = (int) ceil($percentile * count($values)) - 1;

    return $values[max(0, min($index, count($values) - 1))];
};

echo sprintf(
    "%-34s %8s %8s %8s %8s %8s %9s  %s\n",
    'scenario',
    'median',
    'p95',
    'min',
    'db',
    'php',
    'queries',
    'slowest query (median ms)',
);
echo str_repeat('-', 186) . PHP_EOL;

foreach ($results as $label => $result) {
    $db = array_sum(array_map(
        static fn(array $times): float => array_sum($times) / count($times),
        $result['sql'],
    ));

    $slowest = [];

    foreach ($result['sql'] as $sql => $times) {
        $slowest[$sql] = array_sum($times) / count($times);
    }

    arsort($slowest);
    $topSql = (string) array_key_first($slowest);
    $median = $percentile($result['durations'], 0.5);

    echo sprintf(
        "%-34s %6.1fms %6.1fms %6.1fms %6.1fms %6.1fms %4d-%-4d  %6.2f  %s\n",
        $label,
        $median,
        $percentile($result['durations'], 0.95),
        $percentile($result['durations'], 0.0),
        $db,
        $median - $db,
        min($result['counts']),
        max($result['counts']),
        $slowest[$topSql] ?? 0.0,
        mb_substr($topSql, 0, 70),
    ) . PHP_EOL;
}

echo PHP_EOL . 'Query breakdown of the first page of each state, without search' . PHP_EOL;

foreach ($plans as $label => $queries) {
    if (! str_contains($label, '/ none / page 1')) {
        continue;
    }

    echo PHP_EOL . $label . PHP_EOL;

    foreach ($queries as $query) {
        printf("  %6.2fms  %s\n", $query['time'], mb_substr($interpolate($query), 0, 130));
    }
}

echo PHP_EOL . 'EXPLAIN of the queries behind the active list' . PHP_EOL;

foreach ($plans as $label => $queries) {
    if (! str_starts_with($label, 'active /')) {
        continue;
    }

    foreach ($queries as $query) {
        $sql = $interpolate($query);

        if (! str_contains($sql, 'customers')) {
            continue;
        }

        echo PHP_EOL . $label . PHP_EOL . '  ' . $sql . PHP_EOL;

        foreach (DB::select('EXPLAIN ' . $sql) as $row) {
            echo sprintf(
                "    table=%-14s type=%-8s key=%-32s rows=%-8s extra=%s\n",
                $row->table ?? '-',
                $row->type ?? '-',
                $row->key ?? '-',
                $row->rows ?? '-',
                $row->Extra ?? '-',
            );
        }
    }
}
