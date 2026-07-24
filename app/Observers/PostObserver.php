<?php

namespace App\Observers;

use App\Actions\PurgePostCacheAction;
use App\Classes\Business\OgImageBusiness;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

final class PostObserver
{
    public function saved(Post $post): void
    {
        PurgePostCacheAction::execute($post);

        $needsGeneration = $post->og_image_generated_at === null
            || $post->wasChanged(['title', 'excerpt', 'og_title', 'og_description', 'meta_description']);

        if ($needsGeneration) {
            try {
                OgImageBusiness::generateForPost(post: $post);
            } catch (\Throwable $e) {
                Log::error('PostObserver: failed to generate OG image', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(Post $post): void
    {
        PurgePostCacheAction::execute($post);

        if ($post->og_image) {
            try {
                OgImageBusiness::deleteOgImage(path: $post->og_image);
            } catch (\Throwable $e) {
                Log::error('PostObserver: failed to delete OG image', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
