<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListPosts::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreatePost::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $post = Post::factory()->create();

    Livewire::test(EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->assertSuccessful();
});
