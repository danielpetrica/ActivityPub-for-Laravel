<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('static routes have cloudflare caching headers', function (string $route) {
    // Create necessary data if route requires it
    if (str_contains($route, 'posts.show')) {
        Post::factory()->create(['slug' => 'test-post', 'status' => \App\Enums\PostStatus::Published]);
        $url = route($route, 'test-post');
    } elseif (str_contains($route, 'tags.show')) {
        Tag::factory()->create(['slug' => 'test-tag']);
        $url = route($route, 'test-tag');
    } elseif (str_contains($route, 'tools.show')) {
        Tool::factory()->create(['slug' => 'test-tool']);
        $url = route($route, 'test-tool');
    } elseif (str_contains($route, 'pages.show')) {
        // We create a page manually to avoid factory issues with seo_metadata
        \Illuminate\Support\Facades\DB::table('pages')->insert([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => json_encode(['type' => 'doc', 'content' => []]),
            'status' => \App\Enums\PostStatus::Published->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $url = route($route, 'test-page');
    } else {
        $url = route($route);
    }

    $response = $this->get($url);

    $response->assertStatus(200);
    // Cache-Control: max-age=7200, public, stale-while-revalidate=21600
    // The Expires header is also set to now() + 3600
    $response->assertHeader('Cache-Control', 'max-age=7200, public, stale-while-revalidate=21600');
    $response->assertHeader('Expires');
})->with([
    'welcome',
    'posts.show',
    'pages.show',
    'tags.show',
    'posts.index',
    'tools.show',
    'demo',
]);

test('static routes do not set cookies or session', function () {
    $response = $this->get(route('welcome'));

    $response->assertStatus(200);
    $response->assertHeaderMissing('Set-Cookie');
});
