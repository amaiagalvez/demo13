<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: function (): array {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST);

                if (! is_string($host) || $host === '') {
                    throw new LogicException('APP_URL must include a valid host for trusted host validation.');
                }

                return ['^'.preg_quote($host, '/').'$'];
            },
            subdomains: false,
        );
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureUserIsActive::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
