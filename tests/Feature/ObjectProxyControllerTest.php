<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('hetzner');
    Storage::fake('og-images');
});

it('streams a file from the media disk', function () {
    Storage::disk('hetzner')->put('media/feature/test.jpg', 'fake-image-content');

    $response = $this->get('/objectproxy/media/media/feature/test.jpg');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'image/jpeg');
    $response->assertHeader('Cache-Control', 'max-age=86400, public');
});

it('streams a file from the og-images disk', function () {
    Storage::disk('og-images')->put('posts/test-post.png', 'fake-og-content');

    $response = $this->get('/objectproxy/og-images/posts/test-post.png');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'image/png');
    $response->assertHeader('Cache-Control', 'max-age=86400, public');
});

it('returns 404 for unknown disk slug', function () {
    $this->get('/objectproxy/unknown/some/path.jpg')
        ->assertStatus(404);
});

it('returns 404 for non-existent file', function () {
    $this->get('/objectproxy/media/non-existent.jpg')
        ->assertStatus(404);
});

it('returns 404 for path with directory traversal', function () {
    Storage::disk('hetzner')->put('secret.txt', 'hidden');

    $this->get('/objectproxy/media/../../secret.txt')
        ->assertStatus(404);
});

it('returns 404 for empty path', function () {
    $this->get('/objectproxy/media/')
        ->assertStatus(404);
});

it('supports HEAD requests', function () {
    Storage::disk('hetzner')->put('media/feature/test.jpg', 'fake-image-content');

    $response = $this->head('/objectproxy/media/media/feature/test.jpg');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'image/jpeg');
    $response->assertHeader('Cache-Control', 'max-age=86400, public');
});
