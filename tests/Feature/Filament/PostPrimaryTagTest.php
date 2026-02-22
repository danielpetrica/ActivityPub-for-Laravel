<?php

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('allows selecting a primary tag from selected tags in Filament post form', function () {
    $tags = Tag::factory()->count(3)->create();

    $data = [
        'title' => 'Primary Tag Test',
        'slug' => 'primary-tag-test',
        'content' => '<p>Body</p>',
        'status' => PostStatus::Published->value,
        'tags' => $tags->pluck('id')->all(),
        'primary_tag_id' => $tags[1]->id,
    ];

    Livewire::test(CreatePost::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Post::class, [
        'slug' => 'primary-tag-test',
        'primary_tag_id' => $tags[1]->id,
    ]);
});
