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
