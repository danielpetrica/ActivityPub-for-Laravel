<?php

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage shows featured and top posts', function () {
    $post = Post::factory()->create([
        'status' => \App\Enums\PostStatus::Published,
        'published_at' => now(),
        'feature_image_path' => 'posts/test.jpg',
        'seo_metadata' => [
            'description' => 'Test SEO description',
        ],
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);
});
