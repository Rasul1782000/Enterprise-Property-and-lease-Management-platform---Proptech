<?php

use App\Http\Middleware\HandleHardcodedToken;
use App\Http\Middleware\RecordMetrics;
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
        $middleware->statefulApi();
        $middleware->alias([
            'hardcoded.token' => HandleHardcodedToken::class,
        ]);

        // Global so every route lands on the dashboard, not just the ones that
        // remember to opt in. It excludes the scrape endpoint and /up itself.
        $middleware->append(RecordMetrics::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
