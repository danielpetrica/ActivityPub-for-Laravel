<?php

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tag page shows custom description and image', function () {
    $tag = Tag::create([
        'name' => 'Laravel',
        'slug' => 'laravel',
        'description' => 'A great framework',
        'image_path' => 'tags/laravel.png',
    ]);

    $this->get(route('tags.show', $tag->slug))
        ->assertStatus(200)
        ->assertSee('Laravel')
        ->assertSee('A great framework')
        ->assertSee('tags/laravel.png');
});

test('tag page uses seo metadata', function () {
    $tag = Tag::create([
        'name' => 'DevOps',
        'slug' => 'devops',
        'meta_title' => 'Custom SEO Title',
        'meta_description' => 'Custom SEO Description',
    ]);

    $this->get(route('tags.show', $tag->slug))
        ->assertStatus(200)
        ->assertSee('Custom SEO Title')
        ->assertSee('Custom SEO Description');
});
