<?php

namespace DanielPetrica\LaravelActivityPub;

use DanielPetrica\LaravelActivityPub\Actions\Handlers\ActivityHandler;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleAcceptAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleAnnounceAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleBlockAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleCreateAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleDeleteAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleFlagAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleFollowAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleLikeAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleMoveAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleRejectAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleUndoAction;
use DanielPetrica\LaravelActivityPub\Actions\Handlers\HandleUpdateAction;
use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Console\Commands\CreateActorCommand;
use DanielPetrica\LaravelActivityPub\Console\Commands\DeliverContentCommand;
use DanielPetrica\LaravelActivityPub\Console\Commands\PruneActivitiesCommand;
use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActorProfileContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatedActorContract;
use DanielPetrica\LaravelActivityPub\Handlers\HandlerRegistry;
use DanielPetrica\LaravelActivityPub\Http\Middleware\AuthorizeFediverse;
use DanielPetrica\LaravelActivityPub\Http\Middleware\VerifyHttpSignature;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

final class ActivityPubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/activitypub.php',
            'activitypub',
        );

        $this->app->bind(
            ActorContract::class,
            fn (): ActorContract => $this->resolveActorContract(),
        );

        $this->app->bind(
            ActorProfileContract::class,
            fn (): ActorProfileContract => $this->resolveActorContract(),
        );

        $this->app->bind(
            FederatedActorContract::class,
            fn (): FederatedActorContract => $this->resolveActorContract(),
        );

        $this->app->bind(
            ActivityBuilderContract::class,
            ActivityBuilder::class,
        );

        $this->app->singleton(
            'activitypub',
            ActivityPubService::class,
        );

        $this->app->tag([
            HandleFollowAction::class,
            HandleLikeAction::class,
            HandleAnnounceAction::class,
            HandleUndoAction::class,
            HandleCreateAction::class,
            HandleDeleteAction::class,
            HandleUpdateAction::class,
            HandleBlockAction::class,
            HandleAcceptAction::class,
            HandleRejectAction::class,
            HandleFlagAction::class,
            HandleMoveAction::class,
        ], 'activitypub.activity-handlers');

        $this->app->singleton(HandlerRegistry::class, function ($app): HandlerRegistry {
            $registry = new HandlerRegistry;

            // Bootstrap the container-tagged handlers. Consumers that have
            // already registered or replaced a handler keep precedence.
            foreach ($app->tagged('activitypub.activity-handlers') as $handler) {
                if ($handler instanceof ActivityHandler) {
                    $registry->registerIfTypeAbsent(handler: $handler);
                }
            }

            return $registry;
        });

        $this->app->bind(InboxProcessor::class, function ($app): InboxProcessor {
            return new InboxProcessor(
                handlerRegistry: $app->make(HandlerRegistry::class),
            );
        });
    }

    public function boot(): void
    {
        RateLimiter::for('activitypub-inbox', fn ($request) => Limit::perMinute(
            60,
        )->by($request->ip() ?? 'global'));

        RateLimiter::for('fediverse-interact', fn () => Limit::perMinute(30));

        $this->publishes(
            [
                __DIR__.'/../config/activitypub.php' => $this->app->configPath('activitypub.php'),
            ],
            'activitypub-config',
        );

        $this->publishes(
            [
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ],
            'activitypub-migrations',
        );

        Route::aliasMiddleware(
            'activitypub.verify-signature',
            VerifyHttpSignature::class,
        );

        Route::aliasMiddleware(
            'activitypub.authorize-fediverse',
            AuthorizeFediverse::class,
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Resolve {actor:username} through the configured actor model instead
        // of the hardcoded Actor class, so swapping config('activitypub.actor_model')
        // changes both DI and route binding.
        Route::bind('actor', function (string $value) {
            $model = $this->actorModelClass();

            return $model::query()
                ->where('username', '=', $value)
                ->firstOrFail();
        });

        // Ensure the inbox rate limiter runs before signature verification so
        // invalid-signature floods are throttled instead of verified.
        $this->registerSignatureMiddlewarePriority();

        if (config('activitypub.routes.enabled', true)) {
            Route::group(
                [
                    'prefix' => config('activitypub.routes.prefix', ''),
                    'middleware' => config('activitypub.routes.middleware', ['api']),
                ],
                function (): void {
                    $this->loadRoutesFrom(__DIR__.'/../routes/activitypub.php');
                },
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateActorCommand::class,
                DeliverContentCommand::class,
                PruneActivitiesCommand::class,
            ]);

            $this->registerPruneSchedule();
        }

        if (config('activitypub.fediverse.enabled', true)) {
            Route::group(
                [
                    'prefix' => config('activitypub.fediverse.prefix', 'fediverse'),
                    'middleware' => array_merge(
                        config('activitypub.fediverse.middleware', ['web', 'auth']),
                        ['activitypub.authorize-fediverse'],
                    ),
                    'as' => 'fediverse.',
                ],
                function (): void {
                    $this->loadRoutesFrom(__DIR__.'/../routes/fediverse.php');
                },
            );
        }

        $this->loadViewsFrom(
            __DIR__.'/../resources/views',
            'activitypub',
        );

        $this->publishes(
            [
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/activitypub'),
            ],
            'activitypub-views',
        );

        Blade::directive('sanitize', function (string $expression) {
            return "<?php echo \\DanielPetrica\\LaravelActivityPub\\Helpers\\ActivityPubHelper::sanitizeContent({$expression}); ?>";
        });
    }

    /**
     * Register the retention command on Laravel's scheduler.
     *
     * Runs daily and is guarded by `withoutOverlapping()` so a slow prune
     * cannot stack on the next run. The retention windows themselves default
     * to 30 days and stay overridable through `activitypub.retention.*`.
     */
    protected function registerPruneSchedule(): void
    {
        Schedule::command('activitypub:prune-activities')
            ->daily()
            ->withoutOverlapping();
    }

    /**
     * Resolve the configured actor model class, falling back to the bundled
     * Actor model when the configured class does not extend it.
     *
     * @return class-string<Actor>
     */
    protected function actorModelClass(): string
    {
        $model = config('activitypub.actor_model', Actor::class);

        if (is_string($model) && is_a($model, Actor::class, true)) {
            return $model;
        }

        return Actor::class;
    }

    protected function resolveActorContract(): ActorContract
    {
        return $this->app->make($this->actorModelClass());
    }

    /**
     * Register VerifyHttpSignature after the framework throttle middleware in
     * the HTTP kernel's middleware priority list. This guarantees the rate
     * limiter runs first even if a consumer reorders the route middleware.
     */
    protected function registerSignatureMiddlewarePriority(): void
    {
        if (! $this->app->bound(Kernel::class)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if ($kernel instanceof \Illuminate\Foundation\Http\Kernel) {
            $kernel->addToMiddlewarePriorityAfter(
                ThrottleRequests::class,
                VerifyHttpSignature::class,
            );
        }
    }
}
