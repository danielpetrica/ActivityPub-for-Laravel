<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class CleanupOgImages extends Command
{
    protected $signature = 'app:cleanup-og-images';

    protected $description = 'Delete auto-generated OG images older than 60 days';

    public function handle(): int
    {
        $disk = Storage::disk('og-images');
        $cutoff = now()->subDays(60);
        $deleted = 0;

        // Clean up model-tied images
        foreach ([Post::class, Page::class, Tag::class] as $modelClass) {
            $models = $modelClass::query()
                ->whereNotNull('og_image_generated_at')
                ->where('og_image_generated_at', '<', $cutoff)
                ->get();

            /** @var Post|Page|Tag $model */
            foreach ($models as $model) {
                if ($model->og_image && $disk->exists($model->og_image)) {
                    $disk->delete($model->og_image);
                    $deleted++;
                }

                $model->updateQuietly([
                    'og_image' => null,
                    'og_image_generated_at' => null,
                ]);
            }
        }

        // Clean up non-model-tied images (homepage, all-posts)
        $staticPaths = ['homepage.png', 'all-posts.png'];
        foreach ($staticPaths as $path) {
            if ($disk->exists($path)) {
                $lastModified = $disk->lastModified($path);
                if ($lastModified && $lastModified < $cutoff->timestamp) {
                    $disk->delete($path);
                    $deleted++;
                }
            }
        }

        Log::info('OgImageBusiness: cleanup completed', [
            'deleted' => $deleted,
        ]);

        $this->info("Deleted {$deleted} expired OG image(s).");

        return self::SUCCESS;
    }
}
