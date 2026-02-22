<?php

namespace App\Classes\Business\Import\Ghost;

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
        $this->ghostBaseUrl = rtrim(string: $ghostBaseUrl, characters: '/');
        $this->dryRun = $dryRun;
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

    private function importPostsAndPages(array $posts, array $postsMeta, array $postsTags, array $allData): void
    {
        $metaByPostId = collect($postsMeta)->keyBy('post_id');
        $tagsByPostId = collect($postsTags)->groupBy('post_id');

        foreach ($posts as $postData) {
            try {
                $type = $postData['type']; // 'post' or 'page'
                $slug = $postData['slug'];
                $isPost = $type === 'post';

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
                    'feature_image_caption' => strip_tags($featureImageCaption),
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

        $fullUrl = str_starts_with(haystack: $url, needle: '__GHOST_URL__')
            ? str_replace(search: '__GHOST_URL__', replace: $this->ghostBaseUrl, subject: $url)
            : $url;

        try {
            $response = Http::timeout(seconds: 30)->get(url: $fullUrl);

            if ($response->failed()) {
                $this->report['warnings'][] = "Failed to download image: {$fullUrl}";

                return $url; // Return original URL if download fails
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
        // Replace __GHOST_URL__ in HTML
        $html = str_replace(search: '__GHOST_URL__', replace: $this->ghostBaseUrl, subject: $html);

        // Download and replace inline images
        preg_match_all(pattern: '/<img[^>]+src="([^">]+)"/i', subject: $html, matches: $matches);

        if (! empty($matches[1])) {
            foreach (array_unique(array: $matches[1]) as $imgUrl) {
                $localPath = $this->ingestImage(url: $imgUrl, subfolder: 'content');
                if ($localPath && $localPath !== $imgUrl) {
                    $localUrl = Storage::url(path: $localPath);
                    $html = str_replace(search: $imgUrl, replace: $localUrl, subject: $html);
                }
            }
        }

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
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->report['redirects']['created']++;
    }
}
