<?php

use App\Classes\Business\RedirectBusiness;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects imported ghost urls with and without a trailing slash', function () {
    $slug = 'how-to-replace-raw-s3-urls-with-a-laravel-image-proxy-and-keep-your-cdn-cache';
    $destination = "/posts/{$slug}/";

    // Mirrors the storage format produced by the Ghost importer.
    Redirect::factory()->create([
        'path' => "/{$slug}/",
        'destination_url' => $destination,
        'status_code' => 301,
        'is_enabled' => true,
    ]);

    // Bypass the half-day cache so the freshly inserted row is served.
    RedirectBusiness::refreshCache();

    $this->get("/{$slug}/")
        ->assertStatus(301)
        ->assertRedirect($destination);

    $this->get("/{$slug}")
        ->assertStatus(301)
        ->assertRedirect($destination);
});

it('returns 404 for unknown legacy paths', function () {
    $this->get('/this-legacy-path-does-not-exist/')->assertStatus(404);
    $this->get('/this-legacy-path-does-not-exist')->assertStatus(404);
});