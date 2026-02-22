<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListAnnouncements::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateAnnouncement::class)
        ->assertSuccessful();
});

it('can create announcement', function () {
    $newData = Announcement::factory()->make();

    Livewire::test(CreateAnnouncement::class)
        ->fillForm([
            'text' => $newData->text,
            'is_active' => $newData->is_active,
            'is_cross_site' => $newData->is_cross_site,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Announcement::class, [
        'is_active' => $newData->is_active,
        'is_cross_site' => $newData->is_cross_site,
    ]);
});

it('can render edit page', function () {
    $announcement = Announcement::factory()->create();

    Livewire::test(EditAnnouncement::class, [
        'record' => $announcement->getRouteKey(),
    ])
        ->assertSuccessful();
});

it('can update announcement', function () {
    $announcement = Announcement::factory()->create();
    $newData = Announcement::factory()->make();

    Livewire::test(EditAnnouncement::class, [
        'record' => $announcement->getRouteKey(),
    ])
        ->fillForm([
            'is_active' => ! $announcement->is_active,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($announcement->refresh())
        ->is_active->not->toBe($newData->is_active);
});

it('can delete announcement', function () {
    $announcement = Announcement::factory()->create();

    Livewire::test(EditAnnouncement::class, [
        'record' => $announcement->getRouteKey(),
    ])
        ->callAction('delete');

    $this->assertModelMissing($announcement);
});
