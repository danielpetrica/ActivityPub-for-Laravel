<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CaseStudies\Pages\CreateCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Filament\Resources\CaseStudies\Pages\ListCaseStudies;
use App\Models\CaseStudy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListCaseStudies::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateCaseStudy::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $caseStudy = CaseStudy::factory()->create();

    Livewire::test(EditCaseStudy::class, [
        'record' => $caseStudy->getRouteKey(),
    ])
        ->assertSuccessful();
});
