<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only explicitly configured proxies (TRUSTED_PROXIES, comma-separated IPs/CIDRs).
        $trusted = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));
        if ($trusted !== []) {
            $middleware->trustProxies(at: $trusted);
        }

        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class, \App\Http\Middleware\EnforcePasswordChange::class]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
