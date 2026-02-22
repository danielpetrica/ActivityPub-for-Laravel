<?php

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a View All Articles link to /allposts on the homepage', function () {
    // Seed a minimal set of published posts so homepage renders sections
    Post::factory()->count(3)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('View All Articles');
    $response->assertSee(route('posts.index'));
});

it('resolves the all posts page when visiting the CTA link', function () {
    Post::factory()->count(3)->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);

    $response = $this->get('/allposts');

    $response->assertOk();
    $response->assertViewIs('all-posts');
});
