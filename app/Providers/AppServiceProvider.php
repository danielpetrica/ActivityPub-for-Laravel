<?php

namespace App\Providers;

use App\Models\Link;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Observers\LinkObserver;
use App\Observers\PageObserver;
use App\Observers\PostObserver;
use App\Observers\TagObserver;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $isTesting = $this->app->environment('testing');

        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute($isTesting ? 10000 : 10)->by($request->ip()));
        RateLimiter::for('subscribe', fn (Request $request) => Limit::perMinute($isTesting ? 10000 : 10)->by($request->ip()));
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute($isTesting ? 10000 : 30)->by($request->ip()));
        RateLimiter::for('likes', fn (Request $request) => Limit::perMinute($isTesting ? 10000 : 20)->by($request->ip()));

        Post::observe(PostObserver::class);
        Page::observe(PageObserver::class);
        Tag::observe(TagObserver::class);

        Link::observe(LinkObserver::class);

        // Register TipTap JS extensions for the Filament RichEditor.
        // Guarded: manifest doesn't exist during `composer install` before frontend build.
        if (file_exists(public_path('build/manifest.json'))) {
            FilamentAsset::register([
                Js::make('rich-content-plugins/figure', Vite::asset('resources/js/filament/rich-content-plugins/figure.js'))->loadedOnRequest(),
                Js::make('rich-content-plugins/figcaption', Vite::asset('resources/js/filament/rich-content-plugins/figcaption.js'))->loadedOnRequest(),
                Js::make('rich-content-plugins/div', Vite::asset('resources/js/filament/rich-content-plugins/div.js'))->loadedOnRequest(),
                Js::make('rich-content-plugins/iframe', Vite::asset('resources/js/filament/rich-content-plugins/iframe.js'))->loadedOnRequest(),
                Js::make('rich-content-plugins/image-proxy', Vite::asset('resources/js/filament/rich-content-plugins/image-proxy.js'))->loadedOnRequest(),
            ]);
        }

        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

        // Safety net: register fediverse routes if the package's ServiceProvider
        // didn't load them (e.g., stale route cache, env issues during deploy).
        $this->app->booted(function (): void {
            if (
                ! Route::has(name: 'fediverse.dashboard')
                && class_exists(\DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse\DashboardController::class)
            ) {
                Route::middleware(['web', 'auth'])
                    ->prefix('fediverse')
                    ->name('fediverse.')
                    ->group(base_path('vendor/danielpetrica/laravel-activitypub/routes/fediverse.php'));
            }
        });
    }
}
