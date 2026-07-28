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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Phase 06e+1 — throttled last_seen_at touch on every
            // authenticated request (5-min cache guard). Skipped on
            // console invocation inside the middleware itself.
            \App\Http\Middleware\RecordLastSeen::class,
        ]);

        // Phase 06e+2 — route middleware alias for gating admin stub
        // pages behind local/staging. Apply per-route via
        // ->middleware('gate.stubs'); the middleware itself owns the
        // route-name list and the env-allow-list.
        $middleware->alias([
            'gate.stubs' => \App\Http\Middleware\GateStubPagesByEnvironment::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
