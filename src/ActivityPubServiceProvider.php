<?php

namespace DanielPetrica\LaravelActivityPub;

use DanielPetrica\LaravelActivityPub\Console\Commands\CreateActorCommand;
use DanielPetrica\LaravelActivityPub\Console\Commands\DeliverContentCommand;
use DanielPetrica\LaravelActivityPub\Console\Commands\PruneActivitiesCommand;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Http\Middleware\VerifyHttpSignature;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ActivityPubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/activitypub.php',
            key: 'activitypub',
        );

        $this->app->bind(
            abstract: ActorContract::class,
            concrete: Actor::class,
        );

        $this->app->singleton(
            abstract: 'activitypub',
            concrete: ActivityPubService::class,
        );
    }

    public function boot(): void
    {
        RateLimiter::for(name: 'activitypub-inbox', callback: fn ($request) => Limit::perMinute(
            maxAttempts: 60,
        )->by($request->ip() ?? 'global'));

        RateLimiter::for(name: 'fediverse-interact', callback: fn () => Limit::perMinute(maxAttempts: 30));

        $this->publishes(
            paths: [
                __DIR__.'/../config/activitypub.php' => $this->app->configPath(path: 'activitypub.php'),
            ],
            groups: 'activitypub-config',
        );

        $this->publishes(
            paths: [
                __DIR__.'/../database/migrations' => $this->app->databasePath(path: 'migrations'),
            ],
            groups: 'activitypub-migrations',
        );

        Route::aliasMiddleware(
            name: 'activitypub.verify-signature',
            class: VerifyHttpSignature::class,
        );

        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');

        if (config(key: 'activitypub.routes.enabled', default: true)) {
            Route::group(
                attributes: [
                    'prefix' => config(key: 'activitypub.routes.prefix', default: ''),
                    'middleware' => config(key: 'activitypub.routes.middleware', default: ['api']),
                ],
                routes: function (): void {
                    $this->loadRoutesFrom(path: __DIR__.'/../routes/activitypub.php');
                },
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands(commands: [
                CreateActorCommand::class,
                DeliverContentCommand::class,
                PruneActivitiesCommand::class,
            ]);
        }

        if (config(key: 'activitypub.fediverse.enabled', default: true)) {
            Route::group(
                attributes: [
                    'prefix' => config(key: 'activitypub.fediverse.prefix', default: 'fediverse'),
                    'middleware' => config(key: 'activitypub.fediverse.middleware', default: ['web', 'auth']),
                    'as' => 'fediverse.',
                ],
                routes: function (): void {
                    $this->loadRoutesFrom(path: __DIR__.'/../routes/fediverse.php');
                },
            );
        }

        $this->loadViewsFrom(
            path: __DIR__.'/../resources/views',
            namespace: 'activitypub',
        );

        $this->publishes(
            paths: [
                __DIR__.'/../resources/views' => $this->app->resourcePath(path: 'views/vendor/activitypub'),
            ],
            groups: 'activitypub-views',
        );
    }
}
