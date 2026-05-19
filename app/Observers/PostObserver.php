<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Classes\Business\OgImageBusiness;
use App\Models\Post;

final class PostObserver
{
    public function saved(Post $post): void
    {
        PurgePostCacheAction::execute($post);

        if ($post->og_image_generated_at !== null) {
            OgImageBusiness::generateForPost(post: $post);
        }
    }

    public function deleted(Post $post): void
    {
        PurgePostCacheAction::execute($post);

        if ($post->og_image) {
            OgImageBusiness::deleteOgImage(path: $post->og_image);
        }
    }
}
