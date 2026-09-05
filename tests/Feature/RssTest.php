<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

it('returns the rss index with correct content-type', function () {
    $response = $this->get('/rss.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml');
    $response->assertSee('rss version="2.0"', escape: false);
    $response->assertSee(route('rss.posts', absolute: true), escape: false);
    $response->assertSee(route('rss.pages', absolute: true), escape: false);
    $response->assertSee(route('rss.tags', absolute: true), escape: false);
});

it('starts the rss index with the xml declaration', function () {
    $response = $this->get('/rss.xml');

    $response->assertOk();
    $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', escape: false);
});

it('returns the posts rss feed with published posts', function () {
    User::factory()->create();
    $published = Post::factory()->create(['status' => PostStatus::Published, 'published_at' => now()]);
    $draft = Post::factory()->create(['status' => PostStatus::Draft]);

    $response = $this->get('/rss/posts.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml');
    $response->assertSee(route('posts.show', $published->slug, absolute: true), escape: false);
    $response->assertDontSee(route('posts.show', $draft->slug, absolute: true), escape: false);
});

it('returns the pages rss feed with published pages', function () {
    $published = Page::factory()->create(['status' => PostStatus::Published]);
    $draft = Page::factory()->create(['status' => PostStatus::Draft]);

    $response = $this->get('/rss/pages.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml');
    $response->assertSee(route('pages.show', $published->slug, absolute: true), escape: false);
    $response->assertDontSee(route('pages.show', $draft->slug, absolute: true), escape: false);
});

it('returns the tags rss feed with all tags', function () {
    $tag = Tag::factory()->create();

    $response = $this->get('/rss/tags.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml');
    $response->assertSee(route('tags.show', $tag->slug, absolute: true), escape: false);
});