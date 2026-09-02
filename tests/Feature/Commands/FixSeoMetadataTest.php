<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Published posts fire the ActivityPub federation event on save, which
    // resolves the actor user. Create one so creating posts in tests works.
    User::create([
        'name' => 'Admin',
        'email' => 'admin@danielpetrica.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);
});

it('reports issues without modifying data in dry-run mode', function () {
    $post = Post::factory()->create([
        'meta_title' => 'A valid post title',
        'meta_description' => str_repeat('a', 200),
    ]);

    $tag = Tag::factory()->create([
        'description' => 'A short tag description.',
    ]);

    $this->artisan('app:fix-seo-metadata')
        ->expectsOutputToContain('meta_title')
        ->expectsOutputToContain('meta_description')
        ->assertExitCode(0);

    $post->refresh();
    $tag->refresh();

    expect($post->meta_description)->toBe(str_repeat('a', 200));
    expect($tag->meta_title)->toBeNull();
    expect($tag->meta_description)->toBeNull();
});

it('applies fixes with --force', function () {
    $post = Post::factory()->create([
        'title' => 'A concise post title',
        'meta_title' => null,
        'meta_description' => str_repeat('a', 200),
    ]);

    $tag = Tag::factory()->create([
        'name' => 'Laravel',
        'description' => 'Hand-picked Laravel tips and tutorials.',
    ]);

    $this->artisan('app:fix-seo-metadata', ['--force' => true])
        ->assertExitCode(0);

    $post->refresh();
    $tag->refresh();

    expect($post->meta_title)->toBe('A concise post title');
    expect(mb_strlen($post->meta_description))->toBeLessThanOrEqual(160);
    expect($tag->meta_title)->toBe('Posts tagged with Laravel - Daniel Petrica');
    expect($tag->meta_description)->toBe('Hand-picked Laravel tips and tutorials.');
});

it('leaves records with valid metadata untouched', function () {
    $post = Post::factory()->create([
        'meta_title' => 'A valid post title',
        'meta_description' => str_repeat('b', 130),
    ]);

    $this->artisan('app:fix-seo-metadata', ['--force' => true])
        ->assertExitCode(0);

    $post->refresh();

    expect($post->meta_title)->toBe('A valid post title');
    expect($post->meta_description)->toBe(str_repeat('b', 130));
});

it('never touches the excluded random_image page even with --force', function () {
    $page = Page::factory()->create([
        'slug' => 'random_image',
        'title' => 'Random Image',
        'meta_title' => null,
        'meta_description' => null,
        'excerpt' => 'Some excerpt that would normally be used.',
    ]);

    $this->artisan('app:fix-seo-metadata', ['--force' => true])
        ->expectsOutputToContain('excluded')
        ->assertExitCode(0);

    $page->refresh();

    expect($page->meta_title)->toBeNull();
    expect($page->meta_description)->toBeNull();
});

it('truncates long meta titles to 60 characters at a word boundary', function () {
    $post = Post::factory()->create([
        'meta_title' => 'This is an extremely long meta title that definitely exceeds the sixty character limit for search results',
        'meta_description' => 'A valid description.',
    ]);

    $this->artisan('app:fix-seo-metadata', ['--force' => true])
        ->assertExitCode(0);

    $post->refresh();

    expect(mb_strlen($post->meta_title))->toBeLessThanOrEqual(60);
    expect($post->meta_title)->toEndWith('…');
});
