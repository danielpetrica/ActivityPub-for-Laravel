<?php

namespace App\Classes\Business;

use App\Enums\CacheTtl;
use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

final class PostBusiness
{
    /**
     * Find a published post by slug or fail.
     */
    public static function findPublishedBySlugOrFail(string $slug): Post
    {
        $post = Post::query()
            ->with(relations: ['tags', 'primaryTag'])
            ->where(column: 'slug', operator: '=', value: $slug)
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->first();

        if (! $post) {
            throw new ModelNotFoundException(message: 'Published post not found.');
        }

        return $post;
    }

    /**
     * Find a published page by slug or fail.
     */
    public static function findPublishedPageBySlugOrFail(string $slug): Page
    {
        $page = Page::query()
            ->where(column: 'slug', operator: '=', value: $slug)
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->first();

        if (! $page) {
            throw new ModelNotFoundException(message: 'Published page not found.');
        }

        return $page;
    }

    /**
     * Get recent published posts.
     */
    public static function getRecentPublished(int $limit = 10): Collection
    {
        return Post::query()
            ->with(relations: 'tags')
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest(column: 'published_at')
            ->limit(value: $limit)
            ->get();
    }

    /**
     * Get top viewed published posts.
     */
    public static function getRecentCreated(int $limit = 5): Collection
    {
        return Cache::remember(
            key: 'posts.recent-created',
            ttl: CacheTtl::Long->value, // 1 hour
            callback: fn () => self::recentCreatedCallback(limit: $limit)
        );
    }

    protected static function recentCreatedCallback(int $limit): Collection
    {
        return Post::query()
            ->with(relations: 'tags')
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest(column: 'created_at')
            ->limit(value: $limit)
            ->get();
    }

    /**
     * Get popular tags.
     */
    public static function getPopularTags(int $limit = 3): Collection
    {
        return Cache::remember(
            key: 'tags.popular',
            ttl: CacheTtl::Long->value,
            callback: fn () => Tag::query()
                ->withCount(relations: 'posts')
                ->orderBy(column: 'posts_count', direction: 'desc')
                ->limit(value: $limit)
                ->get()
        );
    }

    /**
     * Get paginated published posts.
     */
    public static function getPaginatedPublished(int $perPage = 12, int $page = 1): LengthAwarePaginator
    {
        return Post::query()
            ->with(relations: 'tags')
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest(column: 'published_at')
            ->paginate(
                perPage: $perPage,
                page: $page
            );
    }

    /**
     * Get published posts that share at least one tag with the given post,
     * excluding the post itself. Used for the "Related articles" section.
     */
    public static function getRelatedPosts(Post $post, int $limit = 3): Collection
    {
        $tagIds = $post->tags->pluck('id');

        if ($tagIds->isEmpty()) {
            return new Collection;
        }

        return Post::query()
            ->with(relations: 'tags')
            ->where(column: 'id', operator: '!=', value: $post->id)
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->whereHas(
                relation: 'tags',
                callback: fn ($query) => $query->whereIn(column: 'tags.id', values: $tagIds)
            )
            ->latest(column: 'published_at')
            ->limit(value: $limit)
            ->get();
    }
}
