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
     * Bump this whenever the rendered HTML output format changes so that
     * previously-cached renders (e.g. images missing an `alt`) are not served.
     * We version the key instead of clearing the whole cache to avoid a thundering herd.
     */
    private const CACHE_VERSION = '.v2';

    /**
     * Render the given post or page content from Tiptap JSON to HTML.
     */
    public static function execute(Post|Page $model): string
    {
        $cacheKey = sprintf('%s.%d.html%s', $model->getTable(), $model->id, self::CACHE_VERSION);

        return Cache::remember(
            key: $cacheKey,
            ttl: CacheTtl::getHtmlRenderTtl()->value,
            callback: fn () => self::render(content: $model->content, title: $model->title)
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

    private static function render(array|string|null $content, string $title): string
    {
        if (is_string($content)) {
            return self::rewriteImageSrcs(html: $content, title: $title);
        }

        if (empty($content)) {
            return '';
        }

        // Convert Tiptap JSON to HTML first.
        $html = self::getEditor()->setContent($content)->getHTML();

        // Then, normalize image URLs so they point to web-accessible paths.
        return self::rewriteImageSrcs(html: $html, title: $title);
    }

    /**
     * Ensure <img src> attributes point to the image proxy for S3-backed media,
     * and that every <img> carries an `alt` attribute for SEO/accessibility.
     *
     * $title is optional so existing reflection-based tests that call this
     * method with a single argument keep working.
     */
    private static function rewriteImageSrcs(string $html, string $title = ''): string
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

        self::ensureAltAttributes(images: $images, title: $title);

        $result = $dom->saveHTML();
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $result !== false ? $result : $html;
    }

    /**
     * Give every <img> an `alt` attribute when it does not already have one.
     *
     * Preferred source: the text of a sibling <figcaption> inside the same
     * <figure>. Fallback: the model title. Existing alts are left untouched.
     */
    private static function ensureAltAttributes(\DOMNodeList $images, string $title): void
    {
        foreach ($images as $img) {
            /** @var \DOMElement $img */
            if ($img->hasAttribute('alt')) {
                continue;
            }

            // Walk up to the parent <figure> (if any) and use its <figcaption> text.
            $alt = '';
            $figure = $img->parentNode;
            while ($figure !== null && $figure->nodeName !== 'figure') {
                $figure = $figure->parentNode;
            }

            if ($figure !== null) {
                $captions = $figure->getElementsByTagName('figcaption');
                if ($captions->length > 0) {
                    $alt = trim(strip_tags($captions->item(0)->textContent));
                }
            }

            // Fallback to the model title when no usable caption was found.
            if ($alt === '') {
                $alt = $title;
            }

            if ($alt !== '') {
                $img->setAttribute('alt', $alt);
            }
        }
    }
}
