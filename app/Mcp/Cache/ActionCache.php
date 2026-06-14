<?php

namespace App\Mcp\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class ActionCache
{
    private static function store(): Repository
    {
        return Cache::store(config('gh-actions.cache.store', 'redis'));
    }

    private static function actionKey(string $owner, string $repo): string
    {
        return "mcp:gh-actions:{$owner}:{$repo}";
    }

    private static function negativeKey(string $owner, string $repo): string
    {
        return "mcp:gh-actions:404:{$owner}:{$repo}";
    }

    private static function popularKey(string $category): string
    {
        return "mcp:gh-actions:popular:{$category}";
    }

    public static function getAction(string $owner, string $repo): ?array
    {
        return self::store()->get(self::actionKey($owner, $repo));
    }

    public static function putAction(string $owner, string $repo, array $data): void
    {
        $ttl = config('gh-actions.cache.action_ttl', 21600);
        self::store()->put(self::actionKey($owner, $repo), $data, $ttl);
    }

    public static function getPopular(string $category): ?array
    {
        return self::store()->get(self::popularKey($category));
    }

    public static function putPopular(string $category, array $data): void
    {
        $ttl = config('gh-actions.cache.popular_ttl', 86400);
        self::store()->put(self::popularKey($category), $data, $ttl);
    }

    public static function hasNegative(string $owner, string $repo): bool
    {
        return (bool) self::store()->get(self::negativeKey($owner, $repo));
    }

    public static function putNegative(string $owner, string $repo): void
    {
        $ttl = config('gh-actions.cache.negative_ttl', 86400);
        self::store()->put(self::negativeKey($owner, $repo), true, $ttl);
    }
}
