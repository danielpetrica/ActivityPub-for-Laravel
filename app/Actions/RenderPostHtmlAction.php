<?php

namespace App\Actions;

use App\Enums\CacheTtl;
use App\Models\Page;
use App\Models\Post;
use App\Tiptap\Nodes\Div;
use App\Tiptap\Nodes\Figcaption;
use App\Tiptap\Nodes\Figure;
use App\Tiptap\Nodes\Iframe;
use Illuminate\Support\Facades\Cache;
use Tiptap\Editor;

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
                new \Tiptap\Extensions\StarterKit,
                new \Tiptap\Nodes\Image,
                new \Tiptap\Marks\Link,
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
     * Ensure <img src> attributes use absolute asset URLs for public disk files.
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

            // Leave absolute URLs and root-absolute paths intact.
            if (preg_match('/^https?:\/\//i', $src) === 1 || str_starts_with($src, '/')) {
                continue;
            }

            // Normalize common relative forms generated during import.
            // - media/... -> /storage/media/...
            // - storage/... -> /storage/...
            $normalized = $src;
            if (str_starts_with($normalized, 'media/')) {
                $normalized = 'storage/'.$normalized; // point to the public storage symlink
            }

            // Ensure we have a leading slash for web path.
            if (! str_starts_with($normalized, '/')) {
                $normalized = '/'.$normalized;
            }

            // Finally build a full URL using the asset() helper for correctness behind subdirectories/CDNs.
            $absolute = asset($normalized);
            $img->setAttribute('src', $absolute);
        }

        $result = $dom->saveHTML();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $result !== false ? $result : $html;
    }
}
