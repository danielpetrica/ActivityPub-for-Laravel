<?php

namespace App\Classes\Business;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;

final class AnnouncementBusiness
{
    /**
     * @param  array<int>  $tagIds
     * @return Collection<int, Announcement>
     */
    public static function getActiveAnnouncements(array $tagIds = []): Collection
    {
        return Announcement::query()
            ->where('is_active', true)
            ->where(function ($query) use ($tagIds) {
                $query->where('is_cross_site', true);

                if (! empty($tagIds)) {
                    $query->orWhereHas('tags', function ($tagQuery) use ($tagIds) {
                        $tagQuery->whereIn('tags.id', $tagIds);
                    });
                }
            })
            ->latest()
            ->get();
    }
}
