<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports the header and footer links from the old ghost install', function () {
    expect(Link::query()->count())->toBe(15);

    expect(Link::query()->where('position', 'header')->count())->toBe(6);
    expect(Link::query()->where('position', 'footer')->count())->toBe(9);

    expect(Link::query()->where('label', 'Freelance services')->exists())->toBeTrue();
    expect(Link::query()->where('label', 'Seo Monitor!')->exists())->toBeTrue();
    expect(Link::query()->where('label', 'Photo Collection')->exists())->toBeTrue();
    expect(Link::query()->where('label', 'Let me build your project!')->exists())->toBeTrue();
});

it('maps internal ghost urls to the new site routes', function () {
    $about = Link::query()->where('label', 'About')->firstOrFail();
    $traefik = Link::query()->where('label', 'Traefik posts')->firstOrFail();

    expect($about->url)->toBe('/pages/about/');
    expect($traefik->url)->toBe('/tag/traefik/');
});
