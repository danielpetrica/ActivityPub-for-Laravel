<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Comments\Pages\CreateComment;
use App\Filament\Resources\Comments\Pages\EditComment;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListComments::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateComment::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $comment = Comment::factory()->create();

    Livewire::test(EditComment::class, [
        'record' => $comment->getRouteKey(),
    ])
        ->assertSuccessful();
});
