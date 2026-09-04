<?php

namespace App\Http\Controllers;

use App\Actions\RenderPostHtmlAction;
use App\Classes\Business\OgImageBusiness;
use App\Classes\Business\PostBusiness;
use App\Enums\PostStatus;
use App\Models\Tag;
use App\Models\Tool;
use Illuminate\Contracts\View\View;

final class StaticController extends Controller
{
    public function welcome(): View
    {
        $featuredPost = PostBusiness::getRecentPublished(limit: 1)->first();
        $featuredId = $featuredPost?->id;

        // Exclude the featured post from the "Featured Article" and the recent
        // sidebar lists so it is never duplicated on the homepage.
        $recentCreatedPosts = PostBusiness::getRecentCreated(limit: 6)
            ->reject(fn ($post) => $post->id === $featuredId);

        // Fetch one extra so the sidebar still shows 5 items after the exclusion.
        $recentPosts = PostBusiness::getRecentPublished(limit: 6)
            ->reject(fn ($post) => $post->id === $featuredId)
            ->take(5)
            ->values();

        $popularTags = PostBusiness::getPopularTags(limit: 3);
        $metaImage = OgImageBusiness::generateForHomepage();

        return view(
            view: 'welcome',
            data: [
                'featuredPost' => $featuredPost,
                'recentCreatedPosts' => $recentCreatedPosts,
                'recentPosts' => $recentPosts,
                'popularTags' => $popularTags,
                'metaImage' => $metaImage,
            ]
        );
    }

    public function showPost(string $slug): View
    {
        $post = PostBusiness::findPublishedBySlugOrFail(slug: $slug);

        return view(
            view: 'post-show',
            data: [
                'post' => $post,
                'tocItems' => RenderPostHtmlAction::toc($post),
                'relatedPosts' => PostBusiness::getRelatedPosts(post: $post),
            ]
        );
    }

    public function showPage(string $slug): View
    {
        $page = PostBusiness::findPublishedPageBySlugOrFail(slug: $slug);

        return view(
            view: 'page-show',
            data: ['page' => $page]
        );
    }

    public function showTag(string $slug): View
    {
        $tag = Tag::query()
            ->where(column: 'slug', operator: '=', value: $slug)
            ->firstOrFail();

        $posts = $tag->posts()
            ->with(relations: 'tags')
            ->where(column: 'status', operator: '=', value: PostStatus::Published)
            ->latest(column: 'published_at')
            ->paginate(perPage: 12);

        return view(
            view: 'tag-show',
            data: [
                'tag' => $tag,
                'posts' => $posts,
            ]
        );
    }

    public function postIndex(int $page = 1): View
    {
        $posts = PostBusiness::getPaginatedPublished(perPage: 12, page: $page);

        return view(
            view: 'all-posts',
            data: [
                'posts' => $posts,
            ]
        );
    }

    public function showTool(string $slug): View
    {
        $tool = Tool::query()
            ->where(column: 'slug', operator: '=', value: $slug)
            ->firstOrFail();

        return view(
            view: 'tool-show',
            data: ['tool' => $tool]
        );
    }

    public function demo(): View
    {
        return view(view: 'design-system-demo');
    }

    public function dockerTraefikGenerator(): View
    {
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'Docker Compose & Traefik Config Generator',
            'description' => 'Free online wizard to generate production-ready Docker Compose and Traefik reverse proxy configurations. Supports Laravel Octane (FrankenPHP, Swoole, RoadRunner), static sites, generic services, SSL via Cloudflare DNS challenge or Let\'s Encrypt HTTP challenge, MySQL, PostgreSQL, Redis, queue workers, schedulers, and database backups.',
            'applicationCategory' => 'DeveloperApplication',
            'operatingSystem' => 'Any',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'USD',
            ],
            'author' => [
                '@type' => 'Person',
                'name' => 'Daniel Petrica',
                'url' => url('/'),
            ],
            'url' => url('/tools/docker-traefik-generator'),
            'featureList' => [
                'Generate Traefik reverse proxy Docker Compose configuration',
                'Generate Traefik static YAML configuration (traefik.yml)',
                'SSL certificate automation via Cloudflare DNS challenge or Let\'s Encrypt HTTP challenge',
                'Laravel Octane support with FrankenPHP, Swoole, and RoadRunner',
                'Queue worker and scheduler service generation',
                'Laravel Pulse and Nightwatch agent support',
                'MySQL and PostgreSQL database service generation',
                'Redis cache service generation',
                'Automated database backup service (tiredofit/db-backup)',
                'Dockerfile generation for multi-stage Laravel builds',
                'Deployment run.sh script generation',
                '.env file generation with required variables',
                'No data sent to any server — fully client-side',
            ],
        ];

        return view(
            view: 'tools.docker-traefik-generator',
            data: ['structuredData' => $structuredData]
        );
    }

    public function ghActionsMcp(): View
    {
        return view(view: 'mcp.gh-actions');
    }

    public function vpsContainersTalk(): View
    {
        return view(view: 'talks.vps-containers');
    }
}
