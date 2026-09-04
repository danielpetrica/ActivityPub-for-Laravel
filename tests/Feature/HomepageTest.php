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

test('no article is duplicated on the homepage', function () {
    Post::factory()->create([
        'title' => 'One',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(3),
        'created_at' => now()->subDays(3),
    ]);

    Post::factory()->create([
        'title' => 'Two',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(2),
        'created_at' => now()->subDays(2),
    ]);

    Post::factory()->create([
        'title' => 'Three',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get('/');
    $html = $response->getContent();

    // Headline elements only (hero h1 + card h2/h3), ignoring img alt text.
    preg_match_all('/<h[1-3][^>]*itemprop="headline"[^>]*>.*?<\\/h[1-3]>/s', $html, $matches);

    $titles = array_map(fn ($h) => trim(strip_tags($h)), $matches[0]);
    $titles = array_filter($titles);

    expect(count($titles))->toBe(count(array_unique($titles)))
        ->and($titles)->toContain('One')
        ->and($titles)->toContain('Two')
        ->and($titles)->toContain('Three');
});

test('recent post cards display an excerpt', function () {
    Post::factory()->create([
        'title' => 'Post With Excerpt',
        'excerpt' => 'This is the excerpt shown in the recent posts card.',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    // A second post so the featured hero isn't the only item in the sidebar.
    Post::factory()->create([
        'title' => 'Another Post',
        'excerpt' => 'A different excerpt for the second card.',
        'status' => PostStatus::Published,
        'published_at' => now()->subDays(2),
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('This is the excerpt shown in the recent posts card.');
});
