<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListPages::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreatePage::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $page = Page::factory()->create();

    Livewire::test(EditPage::class, [
        'record' => $page->getRouteKey(),
    ])
        ->assertSuccessful();
});
