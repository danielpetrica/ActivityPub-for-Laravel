<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListRedirects::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateRedirect::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $redirect = Redirect::factory()->create();

    Livewire::test(EditRedirect::class, [
        'record' => $redirect->getRouteKey(),
    ])
        ->assertSuccessful();
});
