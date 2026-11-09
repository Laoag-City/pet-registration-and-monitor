<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an authenticated user still has the default password, only the
 * dashboard (which hosts the change dialog), the password change and logout
 * are reachable.
 */
class EnforcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user || ! $route || ! in_array('auth', $route->gatherMiddleware(), true)) {
            return $next($request);
        }

        // Computed once per session (covers remember-me, registration and admin-set passwords).
        if (! $request->session()->has('must_change_password')) {
            $request->session()->put('must_change_password', $user->usesDefaultPassword());
        }

        if (! $request->session()->get('must_change_password')) {
            return $next($request);
        }

        $name = $route->getName();
        if ($name === 'password.change' || $name === 'logout' || ($name === 'dashboard' && $request->isMethodSafe())) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
