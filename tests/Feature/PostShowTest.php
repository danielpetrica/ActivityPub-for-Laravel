<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('post show page displays the main tag as a label', function () {
    $tag = Tag::factory()->create(['name' => 'Laravel']);
    $post = Post::factory()->create([
        'title' => 'Test Post Title',
        'status' => PostStatus::Published,
        'published_at' => now(),
    ]);
    $post->tags()->attach($tag);

    $response = $this->get(route('posts.show', $post->slug));

    $response->assertStatus(200);
    $response->assertSee('Laravel');
    $response->assertSee('itemprop="articleSection"', false);
});
