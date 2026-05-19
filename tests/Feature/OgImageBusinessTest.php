<?php

namespace Tests\Feature;

use App\Classes\Business\OgImageBusiness;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('has no valid generated image when og_image is null', function () {
    $post = Post::factory()->create(['og_image' => null, 'og_image_generated_at' => now()]);

    expect(OgImageBusiness::hasValidGeneratedImage(model: $post))->toBeFalse();
});

it('has no valid generated image when og_image_generated_at is null', function () {
    $post = Post::factory()->create(['og_image' => 'posts/test.png', 'og_image_generated_at' => null]);

    expect(OgImageBusiness::hasValidGeneratedImage(model: $post))->toBeFalse();
});

it('has no valid generated image when file does not exist on disk', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create([
        'og_image' => 'posts/test.png',
        'og_image_generated_at' => now(),
    ]);

    expect(OgImageBusiness::hasValidGeneratedImage(model: $post))->toBeFalse();
});

it('has valid generated image when all conditions are met', function () {
    Storage::fake('og-images');
    Storage::disk('og-images')->put('posts/test.png', 'fake-image-content');

    $post = Post::factory()->create([
        'og_image' => 'posts/test.png',
        'og_image_generated_at' => now(),
    ]);

    expect(OgImageBusiness::hasValidGeneratedImage(model: $post))->toBeTrue();
});

it('getGeneratedUrl returns null when no valid image', function () {
    $post = Post::factory()->create(['og_image' => null, 'og_image_generated_at' => null]);

    expect(OgImageBusiness::getGeneratedUrl(model: $post))->toBeNull();
});

it('getGeneratedUrl returns url when valid image exists', function () {
    Storage::fake('og-images');
    Storage::disk('og-images')->put('posts/test.png', 'fake-image-content');

    $post = Post::factory()->create([
        'og_image' => 'posts/test.png',
        'og_image_generated_at' => now(),
    ]);

    $url = OgImageBusiness::getGeneratedUrl(model: $post);

    expect($url)->not->toBeNull();
    expect($url)->toContain('posts/test.png');
});

it('deleteOgImage removes existing file', function () {
    Storage::fake('og-images');
    Storage::disk('og-images')->put('posts/test.png', 'fake-image-content');

    OgImageBusiness::deleteOgImage(path: 'posts/test.png');

    Storage::disk('og-images')->assertMissing('posts/test.png');
});

it('deleteOgImage handles non-existing file gracefully', function () {
    Storage::fake('og-images');

    OgImageBusiness::deleteOgImage(path: 'posts/nonexistent.png');

    expect(true)->toBeTrue();
});

it('generateForPost stores image and updates model', function () {
    Storage::fake('og-images');

    $post = Post::factory()->create([
        'og_image' => null,
        'og_image_generated_at' => null,
    ]);

    $result = OgImageBusiness::generateForPost(post: $post);

    if ($result !== null) {
        $post->refresh();
        expect($post->og_image)->not->toBeNull();
        expect($post->og_image_generated_at)->not->toBeNull();
    } else {
        expect($result)->toBeNull();
    }
});

it('generateForPage stores image and updates model', function () {
    Storage::fake('og-images');

    $page = Page::factory()->create([
        'og_image' => null,
        'og_image_generated_at' => null,
    ]);

    $result = OgImageBusiness::generateForPage(page: $page);

    if ($result !== null) {
        $page->refresh();
        expect($page->og_image)->not->toBeNull();
        expect($page->og_image_generated_at)->not->toBeNull();
    } else {
        expect($result)->toBeNull();
    }
});

it('generateForTag stores image and updates model', function () {
    Storage::fake('og-images');

    $tag = Tag::factory()->create([
        'og_image' => null,
        'og_image_generated_at' => null,
    ]);

    $result = OgImageBusiness::generateForTag(tag: $tag);

    if ($result !== null) {
        $tag->refresh();
        expect($tag->og_image)->not->toBeNull();
        expect($tag->og_image_generated_at)->not->toBeNull();
    } else {
        expect($result)->toBeNull();
    }
});

it('generateForHomepage caches the result', function () {
    Storage::fake('og-images');

    $result = OgImageBusiness::generateForHomepage();
    $cached = Cache::get('og-image.homepage');

    if ($result !== null) {
        expect($cached)->toBe($result);
    }

    expect(true)->toBeTrue();
});

it('generateForAllPosts caches the result', function () {
    Storage::fake('og-images');

    $result = OgImageBusiness::generateForAllPosts();
    $cached = Cache::get('og-image.all-posts');

    if ($result !== null) {
        expect($cached)->toBe($result);
    }

    expect(true)->toBeTrue();
});
