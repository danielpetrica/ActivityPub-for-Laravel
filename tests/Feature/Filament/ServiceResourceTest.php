<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListServices::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateService::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $service = Service::factory()->create();

    Livewire::test(EditService::class, [
        'record' => $service->getRouteKey(),
    ])
        ->assertSuccessful();
});
