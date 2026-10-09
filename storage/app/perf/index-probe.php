<?php

/**
 * Throwaway probe behind .github/tasks/09.performancce-test-plan.md. It replays the statements of
 * the customer list, measures each one in isolation and then repeats the measurement with a
 * candidate index in place, so the report can say what the index would buy before anyone writes a
 * migration for it. The index is dropped again at the end.
 *
 *     php storage/app/perf/index-probe.php
 */

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

/** @var list<array{sql: string, bindings: array, time: float}> $queries */
$queries = [];
DB::listen(function ($event) use (&$queries): void {
    $queries[] = ['sql' => $event->sql, 'bindings' => $event->bindings, 'time' => (float) $event->time];
});

$send = function (string $uri, string $method = 'GET', array $parameters = [], array $cookies = []) use ($kernel) {
    return $kernel->handle(Request::create($uri, $method, $parameters, $cookies));
};

// Repeated runs log in again from the same address and Fortify throttles that.
cache()->flush();

$loginPage = $send('/login');
preg_match('/name="_token" value="([^"]+)"/', (string) $loginPage->getContent(), $matches);
$cookie = null;

foreach ($loginPage->headers->getCookies() as $candidate) {
    if ($candidate->getName() === config('session.cookie')) {
        $cookie = $candidate->getValue();
    }
}

$user = DB::table('users')->orderBy('id')->first();
$login = $send('/login', 'POST', [
    'email' => $user->email,
    'password' => 'password',
    '_token' => $matches[1],
], [(string) config('session.cookie') => $cookie]);
$cookie = null;

foreach ($login->headers->getCookies() as $candidate) {
    if ($candidate->getName() === config('session.cookie')) {
        $cookie = $candidate->getValue();
    }
}

$cookies = [(string) config('session.cookie') => $cookie];
$list = [['active', '/customers', []], ['archived', '/customers/archived', []], ['trash', '/customers/trash', []]];

/** Statements of one request per list state, without the session and auth bookkeeping. */
$statements = [];

foreach ($list as [$state, $uri, $parameters]) {
    $queries = [];
    $send($uri . '?' . http_build_query(['page' => 1] + $parameters), cookies: $cookies);

    foreach ($queries as $query) {
        if (str_contains($query['sql'], 'customers') && ! str_contains($query['sql'], 'count(*) as `aggregate`')) {
            $statements[$state . ' / results'] = [$query['sql'], $query['bindings']];

            continue;
        }

        if (str_contains($query['sql'], 'count(*) as `aggregate` from `customers`')) {
            $statements[$state . ' / counts'] = [$query['sql'], $query['bindings']];
        }
    }
}

/**
 * @param  list<array{0: string, 1: array}>  $statements
 * @return array<string, float>
 */
$measure = static function (array $statements): array {
    $medians = [];

    foreach ($statements as $label => [$sql, $bindings]) {
        DB::select($sql, $bindings);
        $times = [];

        for ($run = 0; $run < 25; $run++) {
            $started = hrtime(true);
            DB::select($sql, $bindings);
            $times[] = (hrtime(true) - $started) / 1e6;
        }

        sort($times);
        $medians[$label] = $times[(int) (count($times) / 2)];
    }

    return $medians;
};

$before = $measure($statements);

$plans = static function (array $statements, string $title): void {
    echo PHP_EOL . $title . PHP_EOL;

    foreach ($statements as $label => [$sql, $bindings]) {
        echo PHP_EOL . $label . PHP_EOL;

        foreach (DB::select('EXPLAIN ' . $sql, $bindings) as $row) {
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
};

$plans($statements, 'EXPLAIN sin el indice candidato');

DB::statement('create index customers_state_name_index on customers (active, deleted_at, name)');
$after = $measure($statements);
$plans($statements, 'EXPLAIN con el indice candidato (active, deleted_at, name)');
DB::statement('drop index customers_state_name_index on customers');

echo PHP_EOL . 'Customers: ' . Customer::withTrashed()->count() . PHP_EOL . PHP_EOL;
echo sprintf("%-22s %10s %10s %10s\n", 'statement', 'before', 'after', 'delta');
echo str_repeat('-', 56) . PHP_EOL;

foreach ($before as $label => $time) {
    echo sprintf(
        "%-22s %9.2fms %9.2fms %9.2fms\n",
        $label,
        $time,
        $after[$label],
        $after[$label] - $time,
    ) . PHP_EOL;
}
