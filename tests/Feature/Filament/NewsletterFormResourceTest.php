<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\NewsletterForms\Pages\CreateNewsletterForm;
use App\Filament\Resources\NewsletterForms\Pages\EditNewsletterForm;
use App\Filament\Resources\NewsletterForms\Pages\ListNewsletterForms;
use App\Models\NewsletterForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can render list page', function () {
    Livewire::test(ListNewsletterForms::class)
        ->assertSuccessful();
});

it('can render create page', function () {
    Livewire::test(CreateNewsletterForm::class)
        ->assertSuccessful();
});

it('can render edit page', function () {
    $newsletterForm = NewsletterForm::factory()->create();

    Livewire::test(EditNewsletterForm::class, [
        'record' => $newsletterForm->getRouteKey(),
    ])
        ->assertSuccessful();
});
