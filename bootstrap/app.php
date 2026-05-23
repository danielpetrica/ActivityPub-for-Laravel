<?php

use App\Http\Middleware\AnalyticsMiddleware;
use App\Http\Middleware\LogMcpRequest;
use App\Http\Middleware\SetCacheControlHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::middleware('static')
                ->group(__DIR__.'/../routes/static.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(middleware: AnalyticsMiddleware::class);

        $middleware->group(
            group: 'static',
            middleware: [
                SetCacheControlHeader::class,
                SubstituteBindings::class,
            ]
        );

        $middleware->throttleWithRedis();

        $middleware->group(
            group: 'mcp',
            middleware: [
                LogMcpRequest::class,
                ThrottleRequests::class,
            ]
        )->throttleApi(limiter: 'mcp', redis: true);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
