<?php

namespace App\Classes\Business;

use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimonHamp\TheOg\Background;
use SimonHamp\TheOg\Image;
use SimonHamp\TheOg\Theme\Fonts\Inter;
use SimonHamp\TheOg\Theme\Theme;

final class OgImageBusiness
{
    private const string DISK = 'og-images';

    private const string HOMEPAGE_PATH = 'homepage.png';

    private const string ALL_POSTS_PATH = 'all-posts.png';

    private static function makeTheme(): Theme
    {
        return new Theme(
            accentColor: '#c76523',
            backgroundColor: '#ffffff',
            baseColor: '#1a1a1a',
            baseFont: Inter::bold(),
            descriptionFont: Inter::light(),
            titleFont: Inter::black(),
        );
    }

    private static function buildImage(
        string $title,
        string $description,
        string $url,
    ): Image {
        return (new Image)
            ->theme(theme: self::makeTheme())
            ->url(url: $url)
            ->title(title: $title)
            ->description(description: $description)
            ->background(background: Background::JustWaves, opacity: 0.15)
            ->border();
    }

    private static function store(string $path, Image $image): string
    {
        $disk = Storage::disk(name: self::DISK);
        $disk->put(path: $path, contents: $image->toString());

        Log::debug('OgImageBusiness: stored OG image', [
            'path' => $path,
            'disk' => self::DISK,
        ]);

        return MediaUrlBusiness::forOgImage(path: $path);
    }

    public static function deleteOgImage(string $path): void
    {
        $disk = Storage::disk(name: self::DISK);

        if ($disk->exists(path: $path)) {
            $disk->delete(paths: $path);

            Log::debug('OgImageBusiness: deleted OG image', [
                'path' => $path,
            ]);
        }
    }

    /**
     * @return string|null Returns the public URL or null on failure
     */
    private static function tryGenerate(string $title, string $description, string $url, string $path): ?string
    {
        try {
            $image = self::buildImage(
                title: $title,
                description: $description,
                url: $url,
            );

            return self::store(path: $path, image: $image);
        } catch (\Throwable $e) {
            Log::error('OgImageBusiness: generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function hasValidGeneratedImage(Post|Page|Tag $model): bool
    {
        if ($model->og_image_generated_at === null || $model->og_image === null) {
            return false;
        }

        $disk = Storage::disk(name: self::DISK);

        return $disk->exists(path: $model->og_image);
    }

    public static function getGeneratedUrl(Post|Page|Tag $model): ?string
    {
        if (! self::hasValidGeneratedImage(model: $model)) {
            return null;
        }

        return MediaUrlBusiness::forOgImage(path: $model->og_image);
    }

    public static function generateForPost(Post $post): ?string
    {
        $title = $post->og_title ?? $post->title;
        $description = $post->og_description
            ?? $post->meta_description
            ?? $post->excerpt
            ?? '';
        $url = route(name: 'posts.show', parameters: ['slug' => $post->slug]);
        $path = "posts/{$post->slug}.png";

        $imageUrl = self::tryGenerate(
            title: $title,
            description: $description,
            url: $url,
            path: $path,
        );

        if ($imageUrl === null) {
            return null;
        }

        $post->updateQuietly([
            'og_image' => $path,
            'og_image_generated_at' => now(),
        ]);

        return $imageUrl;
    }

    public static function generateForPage(Page $page): ?string
    {
        $title = $page->og_title ?? $page->title;
        $description = $page->og_description
            ?? $page->meta_description
            ?? $page->excerpt
            ?? '';
        $url = route(name: 'pages.show', parameters: ['slug' => $page->slug]);
        $path = "pages/{$page->slug}.png";

        $imageUrl = self::tryGenerate(
            title: $title,
            description: $description,
            url: $url,
            path: $path,
        );

        if ($imageUrl === null) {
            return null;
        }

        $page->updateQuietly([
            'og_image' => $path,
            'og_image_generated_at' => now(),
        ]);

        return $imageUrl;
    }

    public static function generateForTag(Tag $tag): ?string
    {
        $title = $tag->og_title ?? $tag->name;
        $description = $tag->og_description
            ?? $tag->meta_description
            ?? $tag->description
            ?? '';
        $url = route(name: 'tags.show', parameters: ['slug' => $tag->slug]);
        $path = "tags/{$tag->slug}.png";

        $imageUrl = self::tryGenerate(
            title: $title,
            description: $description,
            url: $url,
            path: $path,
        );

        if ($imageUrl === null) {
            return null;
        }

        $tag->updateQuietly([
            'og_image' => $path,
            'og_image_generated_at' => now(),
        ]);

        return $imageUrl;
    }

    public static function generateForHomepage(): ?string
    {
        $cacheKey = 'og-image.homepage';

        $cached = Cache::get(key: $cacheKey);

        if ($cached !== null) {
            return $cached === '__OG_FAILED__' ? null : $cached;
        }

        $title = config(key: 'app.name');
        $description = 'Documenting my journey through Laravel, Docker, and the freelance world. Helping you build better software.';
        $url = url(path: '/');

        $result = self::tryGenerate(
            title: $title,
            description: $description,
            url: $url,
            path: self::HOMEPAGE_PATH,
        );

        Cache::put(
            key: $cacheKey,
            value: $result ?? '__OG_FAILED__',
            ttl: now()->addDay(),
        );

        return $result;
    }

    public static function generateForAllPosts(): ?string
    {
        $cacheKey = 'og-image.all-posts';

        $cached = Cache::get(key: $cacheKey);

        if ($cached !== null) {
            return $cached === '__OG_FAILED__' ? null : $cached;
        }

        $title = 'All Posts';
        $description = 'Browse all articles on Laravel, DevOps, and more';
        $url = route(name: 'posts.index');

        $result = self::tryGenerate(
            title: $title,
            description: $description,
            url: $url,
            path: self::ALL_POSTS_PATH,
        );

        Cache::put(
            key: $cacheKey,
            value: $result ?? '__OG_FAILED__',
            ttl: now()->addDay(),
        );

        return $result;
    }
}
