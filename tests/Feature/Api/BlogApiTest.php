<?php

use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('can fetch a post in HTML and Markdown formats', function () {
    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello World'],
                ],
            ],
        ],
    ];

    $post = Post::factory()->create([
        'slug' => 'test-post',
        'status' => PostStatus::Published,
        'content' => $content,
    ]);

    // Test HTML format (default JSON response with rendered_html)
    $response = $this->getJson('/api/posts/test-post');
    $response->assertSuccessful()
        ->assertJsonFragment(['slug' => 'test-post'])
        ->assertJsonStructure(['data' => ['rendered_html']]);

    expect($response->json('data.rendered_html'))->toContain('<p>Hello World</p>');

    // Test Markdown format via header
    $response = $this->get('/api/posts/test-post', ['Accept' => 'text/markdown']);
    $response->assertSuccessful()
        ->assertHeaderContains('Content-Type', 'text/markdown')
        ->assertSee('title: '.$post->title)
        ->assertSee('Hello World');

    // Test Markdown format via extension
    $response = $this->get('/api/posts/test-post.md');
    $response->assertSuccessful()
        ->assertHeaderContains('Content-Type', 'text/markdown')
        ->assertSee('Hello World');
});

it('purges cache when a post is saved', function () {
    $post = Post::factory()->create(['slug' => 'cache-test', 'status' => PostStatus::Published]);

    $cacheKey = "posts.{$post->id}.html";
    Cache::put($cacheKey, 'old content');

    $post->update(['title' => 'Updated Title']);

    expect(Cache::get($cacheKey))->toBeNull();
});

it('can fetch approved comments for a post', function () {
    $post = Post::factory()->create(['status' => PostStatus::Published]);
    Comment::factory()->create(['post_id' => $post->id, 'is_approved' => true, 'comment' => 'Approved comment']);
    Comment::factory()->create(['post_id' => $post->id, 'is_approved' => false, 'comment' => 'Pending comment']);

    $response = $this->getJson("/api/posts/{$post->id}/comments");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['comment' => 'Approved comment']);
});

it('can submit a comment for a post', function () {
    $post = Post::factory()->create(['status' => PostStatus::Published]);

    $response = $this->postJson('/api/comments', [
        'post_id' => $post->id,
        'author_name' => 'John Doe',
        'comment' => 'This is a test comment',
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['author_name' => 'John Doe']);

    $this->assertDatabaseHas('comments', [
        'post_id' => $post->id,
        'author_name' => 'John Doe',
        'is_approved' => false,
    ]);
});

it('can like a post', function () {
    $post = Post::factory()->create(['status' => PostStatus::Published]);

    $response = $this->postJson("/api/posts/{$post->id}/like");

    $response->assertSuccessful()
        ->assertJsonFragment(['message' => 'Post liked!', 'likes_count' => 1]);

    $this->assertDatabaseHas('likes', [
        'post_id' => $post->id,
    ]);
});

it('prevents multiple likes from same IP today', function () {
    $post = Post::factory()->create(['status' => PostStatus::Published]);

    $this->postJson("/api/posts/{$post->id}/like");
    $response = $this->postJson("/api/posts/{$post->id}/like");

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'You already liked this post today.']);
});

it('can fetch a tag and its posts', function () {
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $post = Post::factory()->create(['status' => PostStatus::Published]);
    $post->tags()->attach($tag);

    $response = $this->get('/tag/laravel');

    $response->assertSuccessful()
        ->assertSee('Tag: Laravel')
        ->assertSee($post->title);
});

it('can fetch a tool page', function () {
    $tool = Tool::factory()->create([
        'name' => 'HTML Encoder',
        'slug' => 'html-encoder',
        'html_content' => '<div id="tool">Tool content</div>',
    ]);

    $response = $this->get('/tools/html-encoder');

    $response->assertSuccessful()
        ->assertSee('HTML Encoder')
        ->assertSee('Tool content');
});

it('can track a post view', function () {
    $post = Post::factory()->create(['slug' => 'test-post', 'status' => PostStatus::Published]);

    $response = $this->get('/api/tracker/logo.gif?type=post&slug=test-post');

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'image/gif');

    $this->assertDatabaseHas('page_views', [
        'viewable_id' => $post->id,
        'viewable_type' => Post::class,
    ]);
});

it('returns guest status when not logged in', function () {
    $response = $this->getJson('/api/auth/status');

    $response->assertSuccessful()
        ->assertJson(['is_logged_in' => false, 'user' => null]);
});
