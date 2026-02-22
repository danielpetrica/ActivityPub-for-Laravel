<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders JSON-LD BlogPosting and OG meta for post page', function () {
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $post = Post::factory()->create([
        'title' => 'SEO Test Post',
        'meta_title' => 'SEO Test Post Meta',
        'meta_description' => 'A meta description for SEO.',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'updated_at' => now(),
    ]);
    $post->tags()->attach($tag);

    $response = $this->get(route('posts.show', $post->slug));

    $response->assertSuccessful();

    // JSON-LD presence and BlogPosting type
    $response->assertSee('<script type="application/ld+json">', false);
    $response->assertSee('"@type":"BlogPosting"', false);

    // Canonical link present
    $response->assertSee('<link rel="canonical"', false);

    // OG meta tags
    $response->assertSee('property="og:type" content="article"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('property="og:description"', false);

    // Twitter card
    $response->assertSee('name="twitter:card"', false);
});
