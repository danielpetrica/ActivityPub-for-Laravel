<?php

namespace App\Actions;

use App\Enums\CacheTtl;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

final class ConvertPostToMarkdownAction
{
    /**
     * Convert post/page content to Markdown (Principle 5).
     */
    public static function execute(Post|Page $model): string
    {
        $cacheKey = sprintf('%s.%d.markdown', $model->getTable(), $model->id);

        return Cache::remember(
            key: $cacheKey,
            ttl: CacheTtl::getMarkdownConversionTtl()->value,
            callback: fn () => self::convertToMarkdown($model)
        );
    }

    private static function convertToMarkdown(Post|Page $model): string
    {
        // Simple Markdown conversion from JSON or HTML
        // For now, we'll do a simple conversion.
        // In a real scenario, you might use a dedicated Tiptap->Markdown converter.

        $header = '---'.PHP_EOL;
        $header .= 'title: '.$model->title.PHP_EOL;
        $header .= 'slug: '.$model->slug.PHP_EOL;
        $header .= 'date: '.($model->published_at?->toIso8601String() ?? $model->created_at->toIso8601String()).PHP_EOL;
        $tags = $model instanceof Post ? $model->tags->pluck('name')->toArray() : [];
        $header .= 'tags: ['.implode(', ', $tags).']'.PHP_EOL;
        $header .= '---'.PHP_EOL.PHP_EOL;

        // Fallback: strip tags if we don't have a good JSON->Markdown converter yet
        $html = RenderPostHtmlAction::execute($model);
        $markdown = strip_tags(str_replace(['<p>', '</p>', '<br>'], [PHP_EOL, PHP_EOL, PHP_EOL], $html));

        return $header.$markdown;
    }
}
