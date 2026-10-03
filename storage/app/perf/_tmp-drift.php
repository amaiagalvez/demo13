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

echo 'Customers: '.Customer::withTrashed()->count().
    ' | memoria inicial: '.round(memory_get_usage(true) / 1024 / 1024).' MB'.PHP_EOL.PHP_EOL;

foreach (range(1, 30) as $run) {
    $started = hrtime(true);
    $response = $send('/customers?page=1', cookies: $cookies);
    $elapsed = (hrtime(true) - $started) / 1e6;

    printf(
        "%2d  %7.1fms  %6.1f kB  memoria %5.1f MB (pico %5.1f MB)\n",
        $run,
        $elapsed,
        strlen((string) $response->getContent()) / 1024,
        memory_get_usage(true) / 1024 / 1024,
        memory_get_peak_usage(true) / 1024 / 1024,
    );
}