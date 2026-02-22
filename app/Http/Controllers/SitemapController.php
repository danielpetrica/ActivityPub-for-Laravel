<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\City;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Tag;
use App\Models\Tool;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    /** Main sitemap index listing all sub-sitemaps. */
    public function index(): Response
    {
        $content = view(view: 'sitemaps.index')->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }

    /** Sitemap for static/misc pages (homepage, all-posts, etc.). */
    public function pages(): Response
    {
        $pages = Page::query()
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->orderBy(column: 'updated_at', direction: 'desc')
            ->get();

        $content = view(view: 'sitemaps.pages', data: ['pages' => $pages])->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }

    /** Sitemap for blog posts. */
    public function posts(): Response
    {
        $posts = Post::query()
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->orderBy(column: 'published_at', direction: 'desc')
            ->get();

        $content = view(view: 'sitemaps.posts', data: ['posts' => $posts])->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }

    /** Sitemap for tags. */
    public function tags(): Response
    {
        $tags = Tag::query()
            ->orderBy(column: 'updated_at', direction: 'desc')
            ->get();

        $content = view(view: 'sitemaps.tags', data: ['tags' => $tags])->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }

    /** Sitemap for tools. */
    public function tools(): Response
    {
        $tools = Tool::query()
            ->orderBy(column: 'updated_at', direction: 'desc')
            ->get();

        $content = view(view: 'sitemaps.tools', data: ['tools' => $tools])->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }

    /** Sitemap for local service pages (service + city combinations). */
    public function services(): Response
    {
        $services = Service::query()
            ->where(column: 'is_active', operator: '=', value: true)
            ->get();

        $cities = City::query()->get();

        $content = view(
            view: 'sitemaps.services',
            data: [
                'services' => $services,
                'cities' => $cities,
            ]
        )->render();

        return response(content: $content, status: 200)
            ->header(key: 'Content-Type', values: 'application/xml');
    }
}
