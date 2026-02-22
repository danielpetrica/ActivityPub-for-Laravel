<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Models\Page;

final class PageObserver
{
    /**
     * Handle the Page "saved" event.
     */
    public function saved(Page $page): void
    {
        PurgePostCacheAction::execute($page);
    }

    /**
     * Handle the Page "deleted" event.
     */
    public function deleted(Page $page): void
    {
        PurgePostCacheAction::execute($page);
    }
}
