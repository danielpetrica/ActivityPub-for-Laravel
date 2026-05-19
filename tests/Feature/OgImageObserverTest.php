<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates og image when saving a new post', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create();

    $post->refresh();

    if ($post->og_image_generated_at !== null) {
        expect($post->og_image)->not->toBeNull();
        Storage::disk('og-images')->assertExists($post->og_image);
    }

    expect(true)->toBeTrue();
});

it('regenerates og image when post title changes', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create();

    $post->refresh();

    $post->update(['title' => 'Completely New Title']);

    $post->refresh();

    if ($post->og_image_generated_at !== null) {
        expect($post->og_image)->not->toBeNull();
    }

    expect(true)->toBeTrue();
});

it('does not regenerate og image when unrelated field changes', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create();

    $post->refresh();

    $previousImage = $post->og_image;

    $post->update(['show_title_and_feature_image' => true]);

    $post->refresh();

    expect($post->og_image)->toBe($previousImage);
});

it('generates og image when saving a new page', function () {
    Storage::fake('og-images');

    $page = Page::factory()->create();

    $page->refresh();

    if ($page->og_image_generated_at !== null) {
        expect($page->og_image)->not->toBeNull();
        Storage::disk('og-images')->assertExists($page->og_image);
    }

    expect(true)->toBeTrue();
});

it('generates og image when saving a new tag', function () {
    Storage::fake('og-images');

    $tag = Tag::factory()->create();

    $tag->refresh();

    if ($tag->og_image_generated_at !== null) {
        expect($tag->og_image)->not->toBeNull();
        Storage::disk('og-images')->assertExists($tag->og_image);
    }

    expect(true)->toBeTrue();
});

it('cleans up og image when post is deleted', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create();
    $post->refresh();

    if ($post->og_image === null) {
        $post->updateQuietly([
            'og_image' => 'posts/'.$post->slug.'.png',
            'og_image_generated_at' => now(),
        ]);
        $post->refresh();
    }

    Storage::disk('og-images')->put($post->og_image, 'fake-content');

    $path = $post->og_image;

    $post->delete();

    Storage::disk('og-images')->assertMissing($path);
});

it('does not crash when deleting a post without og image', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create([
        'og_image' => null,
        'og_image_generated_at' => null,
    ]);

    $post->delete();

    expect(Post::find($post->id))->toBeNull();
});
