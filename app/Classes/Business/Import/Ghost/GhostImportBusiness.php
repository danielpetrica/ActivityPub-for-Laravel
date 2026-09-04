<?php

namespace App\Classes\Business\Import\Ghost;

use App\Classes\Business\MediaUrlBusiness;
use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Tiptap\Nodes\Div;
use App\Tiptap\Nodes\Figcaption;
use App\Tiptap\Nodes\Figure;
use App\Tiptap\Nodes\Iframe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tiptap\Editor;
use Tiptap\Extensions\StarterKit;
use Tiptap\Marks\Link;
use Tiptap\Nodes\Image;

final class GhostImportBusiness
{
    private string $ghostBaseUrl;

    private bool $dryRun;

    private array $report = [
        'tags' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        'posts' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        'pages' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
        'redirects' => ['created' => 0],
        'warnings' => [],
        'errors' => [],
    ];

    public function __construct(string $ghostBaseUrl, bool $dryRun = false)
    {
        $this->ghostBaseUrl = self::normalizeGhostBaseUrl(ghostBaseUrl: $ghostBaseUrl);
        $this->dryRun = $dryRun;
    }

    /**
     * Normalize the Ghost base URL: strip the trailing slash, fix the common
     * "danielpetrica.co" typo (missing the 'm'), and fall back to the app URL
     * when an empty string is passed. Without this, __GHOST_URL__ placeholders
     * are replaced with a broken host and image downloads silently fail.
     */
    private static function normalizeGhostBaseUrl(string $ghostBaseUrl): string
    {
        $base = rtrim(string: $ghostBaseUrl, characters: '/');

        if ($base === '') {
            $base = rtrim(string: (string) config('app.url'), characters: '/');
        }

        // Replace the typo "danielpetrica.co" ONLY when it is not already part
        // of the correct "danielpetrica.com" (a naive str_replace would corrupt
        // correct URLs into "danielpetrica.comm").
        return preg_replace('/danielpetrica\.co(?!m)/', 'danielpetrica.com', $base) ?? $base;
    }

    /**
     * Resolve any URL/asset reference found in the Ghost export into an
     * absolute, downloadable URL. Handles:
     *  - the __GHOST_URL__ placeholder (replaced with the base URL),
     *  - the "danielpetrica.co" typo,
     *  - the "media/__GHOST_URL__/…" form Ghost sometimes stores,
     *  - plain relative root paths such as "/content/images/x.jpg".
     */
    private function resolveAssetUrl(string $url): string
    {
        // Strip a leading "media/" only when it precedes the placeholder or a
        // /content path, so "media/__GHOST_URL__/content/…" resolves cleanly.
        if (str_starts_with(haystack: $url, needle: 'media/')) {
            $rest = substr(string: $url, offset: strlen('media/'));
            if (str_starts_with(haystack: $rest, needle: '__GHOST_URL__') || str_starts_with(haystack: $rest, needle: 'content/')) {
                $url = $rest;
            }
        }

        $url = str_replace(search: '__GHOST_URL__', replace: $this->ghostBaseUrl, subject: $url);
        $url = preg_replace('/danielpetrica\.co(?!m)/', 'danielpetrica.com', $url) ?? $url;

        // Relative root path (e.g. "/content/images/x.jpg") -> absolute against the base.
        if (str_starts_with(haystack: $url, needle: '/') && ! str_starts_with(haystack: $url, needle: '//')) {
            $url = $this->ghostBaseUrl.$url;
        }

        return $url;
    }

    public function run(string $jsonPath): array
    {
        if (! File::exists(path: $jsonPath)) {
            throw new \InvalidArgumentException(message: "Ghost export file not found at: {$jsonPath}");
        }

        $json = json_decode(json: File::get(path: $jsonPath), associative: true);
        if (! isset($json['db'][0]['data'])) {
            throw new \InvalidArgumentException(message: 'Invalid Ghost export format.');
        }

        $data = $json['db'][0]['data'];

        DB::transaction(callback: function () use ($data) {
            $this->importTags(tags: $data['tags'] ?? []);
            $this->importPostsAndPages(
                posts: $data['posts'] ?? [],
                postsMeta: $data['posts_meta'] ?? [],
                postsTags: $data['posts_tags'] ?? [],
                allData: $data
            );
        });

        return $this->report;
    }

    private function importTags(array $tags): void
    {
        foreach ($tags as $tagData) {
            try {
                $slug = $tagData['slug'];
                $tag = Tag::query()->where(column: 'slug', operator: '=', value: $slug)->first();

                $featureImage = $this->ingestImage(
                    url: $tagData['feature_image'] ?? null,
                    subfolder: 'tags'
                );

                $data = [
                    'name' => $tagData['name'],
                    'slug' => $slug,
                    'description' => $tagData['description'] ?? null,
                    'image_path' => $featureImage,
                    'meta_title' => $tagData['meta_title'] ?? null,
                    'meta_description' => $tagData['meta_description'] ?? null,
                    'og_image' => $tagData['og_image'] ?? null,
                    'og_title' => $tagData['og_title'] ?? null,
                    'og_description' => $tagData['og_description'] ?? null,
                    'twitter_image' => $tagData['twitter_image'] ?? null,
                    'twitter_title' => $tagData['twitter_title'] ?? null,
                    'twitter_description' => $tagData['twitter_description'] ?? null,
                    'accent_color' => $tagData['accent_color'] ?? null,
                    'canonical_url' => $tagData['canonical_url'] ?? null,
                ];

                if ($this->dryRun) {
                    $tag ? $this->report['tags']['updated']++ : $this->report['tags']['created']++;

                    continue;
                }

                if ($tag) {
                    $tag->update(attributes: $data);
                    $this->report['tags']['updated']++;
                } else {
                    Tag::create(attributes: $data);
                    $this->report['tags']['created']++;
                }
            } catch (\Exception $e) {
                $this->report['errors'][] = "Failed to import tag {$tagData['slug']}: {$e->getMessage()}";
            }
        }
    }

    private function getEditor(): Editor
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

    private function importPostsAndPages(array $posts, array $postsMeta, array $postsTags, array $allData): void
    {
        $metaByPostId = collect($postsMeta)->keyBy('post_id');
        $tagsByPostId = collect($postsTags)->groupBy('post_id');

        foreach ($posts as $postData) {
            try {
                $type = $postData['type']; // 'post' or 'page'
                $slug = $postData['slug'];
                $isPost = $type === 'post';
                $isPage = $type === 'page';

                $modelClass = $isPost ? Post::class : Page::class;
                $record = $modelClass::query()->where('slug', '=', $slug)->first();

                $status = $postData['status'] === 'published' ? PostStatus::Published : PostStatus::Draft;
                $title = $postData['title'];

                if ($postData['visibility'] !== 'public') {
                    $status = PostStatus::Draft;
                    $title = "Private: {$title}";
                    $this->report['warnings'][] = "Post/Page {$slug} marked as private, imported as Draft with 'Private:' prefix.";
                }

                $meta = $metaByPostId->get($postData['id']);
                $featureImage = $this->ingestImage(
                    url: $postData['feature_image'] ?? null,
                    subfolder: 'feature'
                );

                // Build attributes mapped to explicit columns instead of seo_metadata JSON
                $featureImageAlt = $meta['feature_image_alt'] ?? null;
                // Some Ghost exports place the caption on posts_meta, others on the post itself
                $featureImageCaption = $meta['feature_image_caption']
                    ?? ($postData['feature_image_caption'] ?? null);
                $metaTitle = $meta['meta_title'] ?? null;
                $metaDesc = $meta['meta_description'] ?? null;
                $ogImage = $meta['og_image'] ?? null;
                $ogTitle = $meta['og_title'] ?? null;
                $ogDesc = $meta['og_description'] ?? null;
                $twImage = $meta['twitter_image'] ?? null;
                $twTitle = $meta['twitter_title'] ?? null;
                $twDesc = $meta['twitter_description'] ?? null;
                $canonical = $postData['canonical_url'] ?? null;
                $codeHead = $postData['codeinjection_head'] ?? null;
                $codeFoot = $postData['codeinjection_foot'] ?? null;
                $excerpt = $postData['custom_excerpt'] ?? null;
                $showTitleAndFeature = (bool) ($postData['show_title_and_feature_image'] ?? true);

                // Sensible SEO fallbacks from Ghost's automated logic
                $metaTitle = $metaTitle ?: $title;
                $metaDesc = $metaDesc ?: ($excerpt ?: Str::limit(strip_tags($postData['html'] ?? ''), 160));
                $ogTitle = $ogTitle ?: $metaTitle;
                $ogDesc = $ogDesc ?: $metaDesc;
                $ogImage = $ogImage ?: $featureImage;
                $twTitle = $twTitle ?: $ogTitle;
                $twDesc = $twDesc ?: $ogDesc;
                $twImage = $twImage ?: $ogImage;

                // Try to extract extra SEO from JSON-LD if present in codeinjection_foot
                if ($codeFoot && str_contains($codeFoot, 'application/ld+json')) {
                    if (preg_match('/"description":\s*"([^"]+)"/', $codeFoot, $matches)) {
                        $metaDesc = $matches[1];
                        $ogDesc = $matches[1];
                        $twDesc = $matches[1];
                    }
                    if (preg_match('/"image":\s*"([^"]+)"/', $codeFoot, $matches)) {
                        $ogImage = $matches[1];
                        $twImage = $matches[1];
                    }
                }

                $htmlContent = $this->rewriteHtmlContent(html: $postData['html'] ?? '');
                if (empty(trim($htmlContent))) {
                    $contentJson = $this->getEditor()->setContent('<p></p>')->getDocument();
                } else {
                    // Ensure we have a body tag or at least some content that DOMParser can handle
                    if (! str_contains($htmlContent, '<body>')) {
                        $htmlContent = "<body>{$htmlContent}</body>";
                    }
                    $contentJson = $this->getEditor()->setContent($htmlContent)->getDocument();
                }

                $attributes = [
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $contentJson,
                    'status' => $status,
                    'ghost_uuid' => $postData['uuid'] ?? null,
                    'feature_image_path' => $featureImage,
                    'feature_image_alt' => $featureImageAlt,
                    'feature_image_caption' => $featureImageCaption ? strip_tags($featureImageCaption) : null,
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDesc,
                    'og_image' => $ogImage,
                    'og_title' => $ogTitle,
                    'og_description' => $ogDesc,
                    'twitter_image' => $twImage,
                    'twitter_title' => $twTitle,
                    'twitter_description' => $twDesc,
                    'canonical_url' => $canonical,
                    'codeinjection_head' => $codeHead,
                    'codeinjection_foot' => $codeFoot,
                    'excerpt' => $excerpt,
                    'show_title_and_feature_image' => $showTitleAndFeature,
                ];

                if ($isPost) {
                    $attributes['published_at'] = $postData['published_at'];
                }

                if ($this->dryRun) {
                    $isPost ? ($record ? $this->report['posts']['updated']++ : $this->report['posts']['created']++)
                           : ($record ? $this->report['pages']['updated']++ : $this->report['pages']['created']++);

                    continue;
                }

                if ($record) {
                    $record->update($attributes);
                    $isPost ? $this->report['posts']['updated']++ : $this->report['pages']['updated']++;
                } else {
                    $record = $modelClass::create($attributes);
                    $isPost ? $this->report['posts']['created']++ : $this->report['pages']['created']++;
                }

                // Handle Tags for Posts
                if ($isPost && $tagsByPostId->has($postData['id'])) {
                    $ghostTagIds = collect($tagsByPostId->get($postData['id']))->pluck('tag_id');
                    $localTagIds = Tag::query()
                        ->whereIn('slug', collect($allData['tags'])->whereIn('id', $ghostTagIds)->pluck('slug'))
                        ->pluck('id')
                        ->toArray();

                    $record->tags()->sync($localTagIds);
                }

                // Handle Redirects for Posts (Ghost /{slug} -> Our /posts/{slug})
                if ($isPost) {
                    $this->createRedirect(from: "/{$slug}/", to: "/posts/{$slug}/");
                } elseif ($isPage) {
                    $this->createRedirect(from: "/{$slug}/", to: "/pages/{$slug}/");
                }
            } catch (\Exception $e) {
                $typeLabel = $postData['type'];
                $this->report['errors'][] = "Failed to import {$typeLabel} {$postData['slug']}: {$e->getMessage()}";
            }
        }
    }

    private function ingestImage(?string $url, string $subfolder): ?string
    {
        if (empty($url)) {
            return null;
        }

        $fullUrl = $this->resolveAssetUrl(url: $url);

        try {
            $response = Http::timeout(seconds: 30)->get(url: $fullUrl);

            if ($response->failed()) {
                $this->report['warnings'][] = "Failed to download image: {$fullUrl}";

                // Return the resolved URL (placeholder/typo fixed) rather than the
                // raw export value, so the stored src is at least usable.
                return $fullUrl;
            }

            $contents = $response->body();
            $hash = hash(algo: 'sha256', data: $contents);
            $extension = pathinfo(path: parse_url(url: $fullUrl, component: PHP_URL_PATH), flags: PATHINFO_EXTENSION) ?: 'png';
            $filename = "{$hash}.{$extension}";

            $year = now()->format(format: 'Y');
            $month = now()->format(format: 'm');
            $path = "media/{$subfolder}/{$year}/{$month}/{$filename}";

            if (! $this->dryRun) {
                Storage::disk(name: 'public')->put(path: $path, contents: $contents);
            }

            return $path;
        } catch (\Exception $e) {
            $this->report['warnings'][] = "Error ingesting image {$fullUrl}: {$e->getMessage()}";

            return $url;
        }
    }

    private function rewriteHtmlContent(string $html): string
    {
        // Download and replace inline images FIRST, before the global
        // __GHOST_URL__ replacement. resolveAssetUrl() handles the placeholder,
        // the danielpetrica.co typo, the "media/__GHOST_URL__/…" form and
        // relative "/content/…" paths, so every <img> gets a usable src even
        // when the download itself fails.
        preg_match_all(pattern: '/<img[^>]+src="([^">]+)"/i', subject: $html, matches: $matches);

        if (! empty($matches[1])) {
            foreach (array_unique(array: $matches[1]) as $imgUrl) {
                $ingested = $this->ingestImage(url: $imgUrl, subfolder: 'content');

                if ($ingested === null) {
                    continue;
                }

                // Local path (download succeeded) -> proxy URL; otherwise the
                // already-resolved absolute URL (download failed).
                $replacement = str_starts_with(haystack: $ingested, needle: 'media/')
                    ? MediaUrlBusiness::forMedia(path: $ingested)
                    : $ingested;

                if ($replacement !== $imgUrl) {
                    $html = str_replace(search: $imgUrl, replace: $replacement, subject: $html);
                }
            }
        }

        // Replace __GHOST_URL__ everywhere else (links, etc.), then fix the
        // domain typo in any remaining URLs.
        $html = str_replace(search: '__GHOST_URL__', replace: $this->ghostBaseUrl, subject: $html);
        $html = preg_replace('/danielpetrica\.co(?!m)/', 'danielpetrica.com', $html) ?? $html;

        return $html;
    }

    private function createRedirect(string $from, string $to): void
    {
        if ($this->dryRun) {
            $this->report['redirects']['created']++;

            return;
        }

        // Use DB directly or Redirect model if exists.
        // Migration shows 'redirects' table with 'path' and 'destination_url'
        DB::table(table: 'redirects')->updateOrInsert(
            attributes: ['path' => $from],
            values: [
                'destination_url' => $to,
                'status_code' => 301,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->report['redirects']['created']++;
    }
}
