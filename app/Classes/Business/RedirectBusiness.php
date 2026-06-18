<?php

namespace App\Classes\Business;

use App\Enums\CacheTtl;
use App\Models\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class RedirectBusiness
{
    /**
     * Cache key for redirects.
     */
    public const CACHE_KEY = 'redirects.active';

    /**
     * Get all active redirects from cache or database.
     */
    public static function getActiveRedirects(): Collection
    {
        if (! Schema::hasTable('redirects')) {
            return collect();
        }

        return Cache::remember(
            key: self::CACHE_KEY,
            ttl: CacheTtl::HalfDay->value,
            callback: fn () => self::getActiveRedirectsCallback()
        );
    }

    /**
     * Refresh the redirects cache.
     */
    public static function refreshCache(): Collection
    {
        Log::debug('RedirectBusiness: refreshing cache');

        $redirects = self::getActiveRedirectsCallback();

        Cache::put(
            key: self::CACHE_KEY,
            value: $redirects,
            ttl: CacheTtl::HalfDay->value
        );

        return $redirects;
    }

    /**
     * Database callback for active redirects.
     */
    protected static function getActiveRedirectsCallback(): Collection
    {
        return Redirect::query()
            ->where(column: 'is_enabled', operator: '=', value: true)
            ->get(['path', 'destination_url', 'status_code']);
    }
}
