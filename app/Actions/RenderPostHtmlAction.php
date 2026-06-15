<?php

namespace App\Actions;

use App\Classes\Business\MediaUrlBusiness;
use App\Enums\CacheTtl;
use App\Models\Page;
use App\Models\Post;
use App\Tiptap\Nodes\Div;
use App\Tiptap\Nodes\Figcaption;
use App\Tiptap\Nodes\Figure;
use App\Tiptap\Nodes\Iframe;
use Illuminate\Support\Facades\Cache;
use Tiptap\Editor;
use Tiptap\Extensions\StarterKit;
use Tiptap\Marks\Link;
use Tiptap\Nodes\Image;

final class RenderPostHtmlAction
{
    /**
     * Render the given post or page content from Tiptap JSON to HTML.
     */
    public static function execute(Post|Page $model): string
    {
        $cacheKey = sprintf('%s.%d.html', $model->getTable(), $model->id);

        return Cache::remember(
            key: $cacheKey,
            ttl: CacheTtl::getHtmlRenderTtl()->value,
            callback: fn () => self::render(content: $model->content)
        );
    }

    private static function getEditor(): Editor
    {
        return new Editor(configuration: [
            'extensions' => [
                new StarterKit,
                new Image,
                new Link,
                new Div,
                new Figure,
                new Figcaption,
                new Iframe,
            ],
        ]);
    }

    private static function render(array|string|null $content): string
    {
        if (is_string($content)) {
            return self::rewriteImageSrcs(html: $content);
        }

        if (empty($content)) {
            return '';
        }

        // Convert Tiptap JSON to HTML first.
        $html = self::getEditor()->setContent($content)->getHTML();

        // Then, normalize image URLs so they point to web-accessible paths.
        return self::rewriteImageSrcs(html: $html);
    }

    /**
     * Ensure <img src> attributes point to the image proxy for S3-backed media.
     */
    private static function rewriteImageSrcs(string $html): string
    {
        // Fast bail-out if there's no <img
        if (stripos($html, '<img') === false) {
            return $html;
        }

        // Use DOMDocument for robust attribute rewriting.
        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        // Load with UTF-8 handling; add wrapper to ensure proper parsing.
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            /** @var \DOMElement $img */
            $src = $img->getAttribute('src');
            if ($src === '') {
                continue;
            }

            // 1. Rewrite raw S3 URLs (from old imports) to the image proxy.
            $proxied = MediaUrlBusiness::fromS3Url($src);
            if ($proxied !== null) {
                $img->setAttribute('src', $proxied);

                continue;
            }

            // 2. Leave non-S3 absolute URLs and root-absolute paths intact.
            if (preg_match('/^https?:\/\//i', $src) === 1 || str_starts_with($src, '/')) {
                continue;
            }

            // 3. Handle relative media/ paths through the image proxy.
            if (str_starts_with($src, 'media/')) {
                $img->setAttribute('src', MediaUrlBusiness::forMedia($src));

                continue;
            }

            // 4. Other relative paths — ensure leading slash and use asset().
            $normalized = $src;
            if (! str_starts_with($normalized, '/')) {
                $normalized = '/'.$normalized;
            }

            $img->setAttribute('src', asset($normalized));
        }

        $result = $dom->saveHTML();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $result !== false ? $result : $html;
    }
}
