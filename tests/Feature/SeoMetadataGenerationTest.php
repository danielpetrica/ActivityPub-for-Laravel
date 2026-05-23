<?php

use App\Ai\Agents\SeoGenerator;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@danielpetrica.com',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
    ]);

    Filament::setCurrentPanel(
        Filament::getPanel('office'),
    );

    $this->actingAs($user);
});

it('can generate SEO metadata via the action', function () {
    SeoGenerator::fake(function (AgentPrompt $prompt) {
        return [
            'meta_title' => 'Generated Meta Title',
            'meta_description' => 'Generated meta description for the page.',
            'og_title' => 'Generated OG Title',
            'og_description' => 'Generated OG description for social sharing.',
            'twitter_title' => 'Generated Twitter Title',
            'twitter_description' => 'Generated Twitter card description.',
        ];
    });

    $page = Page::factory()->create();

    Livewire::test(EditPage::class, [
        'record' => $page->getKey(),
    ])
        ->mountAction('generateSeo')
        ->assertActionMounted('generateSeo');
});

it('generates SEO and fills form fields on edit page', function () {
    SeoGenerator::fake(function (AgentPrompt $prompt) {
        return [
            'meta_title' => 'Generated Meta Title',
            'meta_description' => 'Generated meta description for the page.',
            'og_title' => 'Generated OG Title',
            'og_description' => 'Generated OG description for social sharing.',
            'twitter_title' => 'Generated Twitter Title',
            'twitter_description' => 'Generated Twitter card description.',
        ];
    });

    $page = Page::factory()->create([
        'title' => 'Test Page',
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Test content for SEO generation.']]],
            ],
        ],
    ]);

    Livewire::test(EditPage::class, [
        'record' => $page->getKey(),
    ])
        ->callAction('generateSeo', data: [
            'context' => 'Test content for SEO generation.',
        ])
        ->assertHasNoFormErrors();

    SeoGenerator::assertPrompted(function (AgentPrompt $prompt) {
        return str_contains($prompt->prompt, 'Test Page')
            && str_contains($prompt->prompt, 'Test content');
    });
});

it('handles generation failure gracefully', function () {
    SeoGenerator::fake(function () {
        throw new Exception('API error');
    });

    $page = Page::factory()->create();

    Livewire::test(EditPage::class, [
        'record' => $page->getKey(),
    ])
        ->callAction('generateSeo', data: [
            'context' => 'Some content.',
        ]);
});

it('shows generate seo button on create page', function () {
    SeoGenerator::fake();

    Livewire::test(CreatePage::class)
        ->mountAction('generateSeo')
        ->assertActionMounted('generateSeo');
});
