<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders the docker traefik generator page successfully', function () {
    get(route('tools.docker-traefik-generator'))
        ->assertOk()
        ->assertSee('Docker & Traefik Config Generator', false)
        ->assertSee('Loading generator...');
});

it('includes the SoftwareApplication JSON-LD structured data', function () {
    get(route('tools.docker-traefik-generator'))
        ->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('SoftwareApplication', false)
        ->assertSee('DeveloperApplication', false);
});

it('includes static seo copy visible to crawlers', function () {
    get(route('tools.docker-traefik-generator'))
        ->assertOk()
        ->assertSee('What this tool generates')
        ->assertSee('Supported application types')
        ->assertSee('SSL certificate options')
        ->assertSee('Output files')
        ->assertSee('Optional services');
});

it('includes the faq section with faqpage microdata', function () {
    get(route('tools.docker-traefik-generator'))
        ->assertOk()
        ->assertSee('Frequently Asked Questions')
        ->assertSee('schema.org/FAQPage', false)
        ->assertSee('Is this tool free to use?')
        ->assertSee('What is Traefik and why use it?')
        ->assertSee('Does this work with Laravel Octane?');
});

it('loads the vue and generator scripts', function () {
    get(route('tools.docker-traefik-generator'))
        ->assertOk()
        ->assertSee('vue@3', false)
        ->assertSee('docker-traefik-generator.js', false);
});
