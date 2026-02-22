<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\PostStatus;
use App\Models\City;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Tag;
use App\Models\Tool;

it('returns the sitemap index with correct content-type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee('sitemapindex', escape: false);
    $response->assertSee(route('sitemap.posts'), escape: false);
    $response->assertSee(route('sitemap.pages'), escape: false);
    $response->assertSee(route('sitemap.tags'), escape: false);
    $response->assertSee(route('sitemap.tools'), escape: false);
    $response->assertSee(route('sitemap.services'), escape: false);
    $response->assertSee('https://random.danielpetrica.com/sitemap.xml', escape: false);
});

it('returns the posts sitemap with published posts', function () {
    $published = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()]);
    $draft = Post::factory()->create(['status' => PostStatus::Draft]);

    $response = $this->get('/sitemap-posts.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('posts.show', $published->slug), escape: false);
    $response->assertDontSee(route('posts.show', $draft->slug), escape: false);
});

it('returns the pages sitemap with published pages', function () {
    $published = Page::factory()->create(['status' => PostStatus::Published]);
    $draft = Page::factory()->create(['status' => PostStatus::Draft]);

    $response = $this->get('/sitemap-pages.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('pages.show', $published->slug), escape: false);
    $response->assertDontSee(route('pages.show', $draft->slug), escape: false);
});

it('returns the tags sitemap with all tags', function () {
    $tag = Tag::factory()->create();

    $response = $this->get('/sitemap-tags.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('tags.show', $tag->slug), escape: false);
});

it('returns the tools sitemap with all tools', function () {
    $tool = Tool::factory()->create();

    $response = $this->get('/sitemap-tools.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(route('tools.show', $tool->slug), escape: false);
    $response->assertSee(route('tools.docker-traefik-generator'), escape: false);
});

it('returns the services sitemap with active service and city combinations', function () {
    $service = Service::factory()->create(['is_active' => true]);
    $city = City::factory()->create();

    $response = $this->get('/sitemap-services.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee(
        route('services.local', ['service' => $service->slug, 'city' => $city->slug]),
        escape: false
    );
});

it('excludes inactive services from the services sitemap', function () {
    $inactive = Service::factory()->create(['is_active' => false]);
    $city = City::factory()->create();

    $response = $this->get('/sitemap-services.xml');

    $response->assertOk();
    $response->assertDontSee(
        route('services.local', ['service' => $inactive->slug, 'city' => $city->slug]),
        escape: false
    );
});
