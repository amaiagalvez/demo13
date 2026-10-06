<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The row is read again on purpose. The guard caches the user instance for the request, so
        // reading `$user->active` would trust a model that a concurrent deactivation, or a test
        // that updates the row behind it, has already made stale.
        if ($user instanceof User &&
            ! User::whereKey($user->getAuthIdentifier())->where('active', true)->exists()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
