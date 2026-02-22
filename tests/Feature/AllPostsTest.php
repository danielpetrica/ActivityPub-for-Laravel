<?php

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
