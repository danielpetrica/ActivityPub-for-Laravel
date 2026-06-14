<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListCities::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateCity::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $city = City::factory()->create();

    Livewire::test(EditCity::class, [
        'record' => $city->getRouteKey(),
    ])
        ->assertSuccessful();
});
