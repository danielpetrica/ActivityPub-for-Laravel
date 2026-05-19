<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Classes\Business\OgImageBusiness;
use App\Models\Page;
use Illuminate\Support\Facades\Log;

final class PageObserver
{
    public function saved(Page $page): void
    {
        PurgePostCacheAction::execute($page);

        $needsGeneration = $page->og_image_generated_at === null
            || $page->wasChanged(['title', 'excerpt', 'og_title', 'og_description', 'meta_description']);

        if ($needsGeneration) {
            OgImageBusiness::generateForPage(page: $page);
        }
    }

    public function deleted(Page $page): void
    {
        PurgePostCacheAction::execute($page);

        if ($page->og_image) {
            try {
                OgImageBusiness::deleteOgImage(path: $page->og_image);
            } catch (\Throwable $e) {
                Log::error('PageObserver: failed to delete OG image', [
                    'page_id' => $page->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
