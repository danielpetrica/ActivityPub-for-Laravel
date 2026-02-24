<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Post;
use App\Observers\PageObserver;
use App\Observers\PostObserver;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        Post::observe(PostObserver::class);
        Page::observe(PageObserver::class);

        \App\Models\Link::observe(\App\Observers\LinkObserver::class);

        // Register TipTap JS extensions for the Filament RichEditor.
        FilamentAsset::register([
            Js::make('rich-content-plugins/figure', Vite::asset('resources/js/filament/rich-content-plugins/figure.js'))->loadedOnRequest(),
            Js::make('rich-content-plugins/figcaption', Vite::asset('resources/js/filament/rich-content-plugins/figcaption.js'))->loadedOnRequest(),
            Js::make('rich-content-plugins/div', Vite::asset('resources/js/filament/rich-content-plugins/div.js'))->loadedOnRequest(),
            Js::make('rich-content-plugins/iframe', Vite::asset('resources/js/filament/rich-content-plugins/iframe.js'))->loadedOnRequest(),
            Js::make('rich-content-plugins/image-proxy', Vite::asset('resources/js/filament/rich-content-plugins/image-proxy.js'))->loadedOnRequest(),
        ]);

        if($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
    }
}
