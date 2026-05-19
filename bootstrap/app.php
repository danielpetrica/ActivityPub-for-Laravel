<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            \Illuminate\Support\Facades\Route::middleware('static')
                ->group(__DIR__.'/../routes/static.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(middleware: \App\Http\Middleware\AnalyticsMiddleware::class);

        $middleware->group(
            group: 'static',
            middleware: [
                \App\Http\Middleware\SetCacheControlHeader::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
            ]
        );

        $middleware->throttleWithRedis();

        $middleware->group(
            group: 'mcp',
            middleware: [
                \App\Http\Middleware\LogMcpRequest::class,
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
            ]
        )->throttleApi(limiter: 'mcp', redis: true);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
