<?php

namespace App\Observers;

use App\Classes\Business\OgImageBusiness;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TagObserver
{
    public function saved(Tag $tag): void
    {
        $this->invalidatePopularTagsCache();

        $needsGeneration = $tag->og_image_generated_at === null
            || $tag->wasChanged(['name', 'description', 'og_title', 'og_description', 'meta_description']);

        if ($needsGeneration) {
            OgImageBusiness::generateForTag(tag: $tag);
        }
    }

    public function deleted(Tag $tag): void
    {
        $this->invalidatePopularTagsCache();

        if ($tag->og_image) {
            try {
                OgImageBusiness::deleteOgImage(path: $tag->og_image);
            } catch (\Throwable $e) {
                Log::error('TagObserver: failed to delete OG image', [
                    'tag_id' => $tag->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function invalidatePopularTagsCache(): void
    {
        Cache::forget('tags.popular');
    }
}
