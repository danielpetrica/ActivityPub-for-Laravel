<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListTools::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateTool::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $tool = Tool::factory()->create();

    Livewire::test(EditTool::class, [
        'record' => $tool->getRouteKey(),
    ])
        ->assertSuccessful();
});
