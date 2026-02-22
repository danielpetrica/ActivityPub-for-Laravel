<?php

use App\Filament\Resources\Links\Pages\CreateLink;
use App\Filament\Resources\Links\Pages\EditLink;
use App\Filament\Resources\Links\Pages\ListLinks;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListLinks::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateLink::class)
        ->assertSuccessful();
});

it('can create link', function () {
    $newData = Link::factory()->make();

    Livewire::test(CreateLink::class)
        ->fillForm([
            'position' => $newData->position,
            'label' => $newData->label,
            'url' => $newData->url,
            'sort_order' => $newData->sort_order,
            'is_external' => $newData->is_external,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Link::class, [
        'position' => $newData->position,
        'label' => $newData->label,
        'url' => $newData->url,
    ]);
});

it('can render edit page', function () {
    $link = Link::factory()->create();

    Livewire::test(EditLink::class, [
        'record' => $link->getRouteKey(),
    ])
        ->assertSuccessful();
});

it('can update link', function () {
    $link = Link::factory()->create();
    $newData = Link::factory()->make();

    Livewire::test(EditLink::class, [
        'record' => $link->getRouteKey(),
    ])
        ->fillForm([
            'label' => $newData->label,
            'url' => $newData->url,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($link->refresh())
        ->label->toBe($newData->label)
        ->url->toBe($newData->url);
});

it('can delete link', function () {
    $link = Link::factory()->create();

    Livewire::test(EditLink::class, [
        'record' => $link->getRouteKey(),
    ])
        ->callAction('delete');

    $this->assertModelMissing($link);
});
