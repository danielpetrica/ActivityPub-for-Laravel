<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListTags::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateTag::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $tag = Tag::factory()->create();

    Livewire::test(EditTag::class, [
        'record' => $tag->getRouteKey(),
    ])
        ->assertSuccessful();
});
