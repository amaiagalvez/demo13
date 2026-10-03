<?php

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Contracts\Http\Kernel;

require __DIR__ . '/../../../vendor/autoload.php';

$app = require __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$send = function (string $uri, string $method = 'GET', array $parameters = [], array $cookies = []) use ($kernel) {
    return $kernel->handle(Request::create($uri, $method, $parameters, $cookies));
};

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

echo "Customers: ".Customer::withTrashed()->count().PHP_EOL.PHP_EOL;
printf("%-10s %7s %9s %12s %10s\n", 'estado', 'páginas', 'enlaces', 'html', 'mediana');
echo str_repeat('-', 56).PHP_EOL;

foreach (['active' => '/customers', 'inactive' => '/customers/inactive', 'trash' => '/customers/trash'] as $state => $uri) {
    $body = '';
    $pages = 0;

    for ($run = 0; $run < 7; $run++) {
        $started = hrtime(true);
        $response = $send($uri.'?page=1', cookies: $cookies);
        $times[] = (hrtime(true) - $started) / 1e6;
        $body = (string) $response->getContent();
        $pages = max($pages, (int) (preg_match('/of\s+([\d,]+)\s*\??/u', strip_tags($body), $m) ? str_replace(',', '', $m[1]) : 0));
    }

    sort($times);

    printf(
        "%-10s %7s %9d %9.1f kB %8.1fms\n",
        $state,
        $pages ?: '?',
        substr_count($body, 'page='),
        strlen($body) / 1024,
        $times[(int) (count($times) / 2)],
    );

    printf(
        "           filas=%d <tr>=%d head=%.1f kB cuerpo=%.1f kB\n",
        substr_count($body, 'data-test="customer-name-'),
        substr_count($body, '<tr'),
        strlen(substr($body, 0, (int) strpos($body, '</head>'))) / 1024,
        (strlen($body) - strlen(substr($body, 0, (int) strpos($body, '</head>')))) / 1024,
    );

    file_put_contents(__DIR__.'/dump-'.$state.'.html', $body);
}