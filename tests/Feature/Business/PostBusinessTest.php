<?php

namespace Tests\Feature\Business;

use App\Classes\Business\PostBusiness;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('recentCreated posts are ordered by created_at desc', function () {
    // Clear cache to avoid interference
    Cache::forget('posts.recent-created');

    // Create three posts with different created_at times
    $post1 = Post::factory()->create([
        'status' => PostStatus::Published,
        'created_at' => now()->subDays(3),
    ]);

    $post2 = Post::factory()->create([
        'status' => PostStatus::Published,
        'created_at' => now()->subDays(1),
    ]);

    $post3 = Post::factory()->create([
        'status' => PostStatus::Published,
        'created_at' => now()->subDays(2),
    ]);

    // Expected order: $post2 (newest), $post3, $post1 (oldest)
    $topPosts = PostBusiness::getRecentCreated(limit: 3);

    expect($topPosts->count())->toBe(3);
    expect($topPosts[0]->id)->toBe($post2->id);
    expect($topPosts[1]->id)->toBe($post3->id);
    expect($topPosts[2]->id)->toBe($post1->id);
});
