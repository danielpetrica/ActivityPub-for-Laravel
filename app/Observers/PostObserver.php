<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Models\Post;

final class PostObserver
{
    /**
     * Handle the Post "saved" event.
     */
    public function saved(Post $post): void
    {
        PurgePostCacheAction::execute($post);
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void
    {
        PurgePostCacheAction::execute($post);
    }
}
