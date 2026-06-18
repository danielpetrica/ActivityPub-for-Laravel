<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Response;

final class RssController extends Controller
{
    public function index(): Response
    {
        $feeds = [
            ['title' => 'All Posts', 'url' => route(name: 'rss.posts', absolute: true)],
            ['title' => 'Pages', 'url' => route(name: 'rss.pages', absolute: true)],
            ['title' => 'Tags', 'url' => route(name: 'rss.tags', absolute: true)],
        ];

        $content = view(
            view: 'rss.index',
            data: ['feeds' => $feeds]
        )->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/rss+xml');
    }

    public function posts(): Response
    {
        $posts = Post::query()
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest(column: 'published_at')
            ->limit(value: 50)
            ->get();

        $content = view(
            view: 'rss.feed',
            data: [
                'title' => 'Daniel Petrica - All Posts',
                'description' => 'Latest blog posts from Daniel Petrica.',
                'routeName' => 'rss.posts',
                'items' => $posts->map(fn (Post $post): array => [
                    'title' => $post->title,
                    'url' => route(name: 'posts.show', parameters: $post->slug, absolute: true),
                    'description' => $post->excerpt ?? $post->meta_description ?? '',
                    'published_at' => $post->published_at ?? $post->created_at,
                ]),
            ]
        )->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/rss+xml');
    }

    public function pages(): Response
    {
        $pages = Page::query()
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest()
            ->limit(value: 50)
            ->get();

        $content = view(
            view: 'rss.feed',
            data: [
                'title' => 'Daniel Petrica - Pages',
                'description' => 'Static pages from Daniel Petrica.',
                'routeName' => 'rss.pages',
                'items' => $pages->map(fn (Page $page): array => [
                    'title' => $page->title,
                    'url' => route(name: 'pages.show', parameters: $page->slug, absolute: true),
                    'description' => $page->excerpt ?? $page->meta_description ?? '',
                    'published_at' => $page->created_at,
                ]),
            ]
        )->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/rss+xml');
    }

    public function tags(): Response
    {
        $tags = Tag::query()
            ->withCount(relations: 'posts')
            ->orderBy(column: 'name')
            ->get();

        $content = view(
            view: 'rss.feed',
            data: [
                'title' => 'Daniel Petrica - Tags',
                'description' => 'All tags from Daniel Petrica.',
                'routeName' => 'rss.tags',
                'items' => $tags->map(fn (Tag $tag): array => [
                    'title' => $tag->name.' ('.$tag->posts_count.' posts)',
                    'url' => route(name: 'tags.show', parameters: $tag->slug, absolute: true),
                    'description' => $tag->description ?? 'Tag: '.$tag->name,
                    'published_at' => $tag->created_at,
                ]),
            ]
        )->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/rss+xml');
    }
}
