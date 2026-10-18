<?php

use App\Http\Middleware\RecordMetrics;
use App\Http\Middleware\DebugMiddleware;
use App\Http\Middleware\EnsureStaffRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Token-based (Bearer) auth: no CSRF protection on API routes.
        // statefulApi() was removed because it applied VerifyCsrfToken to
        // cookie-bearing requests and caused 419 CSRF token mismatches.
        $middleware->alias([
            'staff' => EnsureStaffRole::class,
        ]);

        $middleware->append(RecordMetrics::class);
        $middleware->append(DebugMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
    })->create();
