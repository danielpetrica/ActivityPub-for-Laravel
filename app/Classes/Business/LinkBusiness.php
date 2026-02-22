<?php

namespace App\Classes\Business;

use App\Enums\CacheTtl;
use App\Enums\LinkPosition;
use App\Models\Link;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final class LinkBusiness
{
    /**
     * Get links for a specific position, cached for one hour.
     */
    public static function getLinksForPosition(LinkPosition $position): Collection
    {
        return Cache::remember(
            key: "links.{$position->value}",
            ttl: CacheTtl::Long->value,
            callback: fn () => Link::query()
                ->where('position', $position)
                ->orderBy('sort_order')
                ->get()
        );
    }
}
