<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ActivityPub federation is enabled in .env and Post::factory()->create()
    // saves with a Published status, which triggers the saved event and calls
    // activityPubActor(). A User must exist or that call throws.
    User::factory()->create();
});

it('can access all posts page', function () {
    Post::factory()->count(5)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts');

    $response->assertStatus(200);
    $response->assertViewIs('all-posts');
});

it('can access specific page of all posts', function () {
    Post::factory()->count(15)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts/2');

    $response->assertStatus(200);
    $response->assertViewIs('all-posts');
});

it('shows pagination links with custom format', function () {
    Post::factory()->count(25)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts/1');

    $response->assertStatus(200);
    // Check for the custom URL format in pagination links
    $response->assertSee('/allposts/2');
});

it('shows an SEO friendly title on the all posts page', function () {
    Post::factory()->count(3)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts');

    $response->assertStatus(200);
    $response->assertSee('All Articles on Laravel, DevOps and More - Daniel Petrica', false);
});

it('renders CollectionPage structured data on the all posts page', function () {
    $posts = Post::factory()->count(3)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts');

    $response->assertStatus(200);
    $response->assertSee('CollectionPage', false);
    $response->assertSee('ItemList', false);

    foreach ($posts as $post) {
        $response->assertSee(route('posts.show', $post->slug), false);
    }
});
