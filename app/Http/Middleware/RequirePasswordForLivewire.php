<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Middleware\RequirePassword;

final class RequirePasswordForLivewire
{
    public function __construct(
        private RequirePassword $requirePassword,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $this->requirePassword->handle($request, $next);

        // Livewire's middleware replay continues after JSON responses unless they are aborted.
        if ($response instanceof JsonResponse && $response->getStatusCode() === 423) {
            abort($response);
        }

        return $response;
    }
}
