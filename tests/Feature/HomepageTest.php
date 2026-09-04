<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create();
});

test('homepage shows featured and top posts', function () {
    $post = Post::factory()->create([
        'status' => PostStatus::Published,
        'published_at' => now(),
        'feature_image_path' => 'posts/test.jpg',
        'seo_metadata' => [
            'description' => 'Test SEO description',
        ],
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);
});

test('featured post is not duplicated on the homepage', function () {
    Post::factory()->create([
        'title' => 'Oldest Article',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(3),
    ]);

    Post::factory()->create([
        'title' => 'Middle Article',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(2),
    ]);

    Post::factory()->create([
        'title' => 'Newest Feature Article',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);

    // The featured (newest) article must appear exactly once — only in the hero.
    $html = $response->getContent();
    expect(substr_count($html, 'Newest Feature Article'))->toBe(1);

    // The other articles should still show up.
    expect($html)->toContain('Oldest Article');
    expect($html)->toContain('Middle Article');
});
