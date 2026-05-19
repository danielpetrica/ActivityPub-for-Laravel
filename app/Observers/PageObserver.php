<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Classes\Business\OgImageBusiness;
use App\Models\Page;

final class PageObserver
{
    public function saved(Page $page): void
    {
        PurgePostCacheAction::execute($page);

        if ($page->og_image_generated_at !== null) {
            OgImageBusiness::generateForPage(page: $page);
        }
    }

    public function deleted(Page $page): void
    {
        PurgePostCacheAction::execute($page);

        if ($page->og_image) {
            OgImageBusiness::deleteOgImage(path: $page->og_image);
        }
    }
}
