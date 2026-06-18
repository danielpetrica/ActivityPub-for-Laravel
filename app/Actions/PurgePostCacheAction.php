<?php

namespace App\Actions;

use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

final class PurgePostCacheAction
{
    /**
     * Purge all caches related to a post or page.
     */
    public static function execute(Post|Page $model): void
    {
        // 1. Purge Laravel HTML cache
        Cache::forget(sprintf('%s.%d.html', $model->getTable(), $model->id));

        // 2. Purge Laravel Markdown cache
        Cache::forget(sprintf('%s.%d.markdown', $model->getTable(), $model->id));

        // 3. Purge recent-created posts cache
        Cache::forget('posts.recent-created');

        // 3. Purge related tag/archive caches if necessary
        // ...

        // 4. Cloudflare integration would go here (Principle 4)
        // Cloudflare::purgeByTag(['post-' . $model->id]);
    }
}
