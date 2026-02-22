<?php

use App\Classes\Business\LinkBusiness;
use App\Enums\LinkPosition;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('caches links for each position', function () {
    Link::factory()->create(['position' => LinkPosition::Header, 'label' => 'Cached Header']);
    Link::factory()->create(['position' => LinkPosition::Footer, 'label' => 'Cached Footer']);

    $headerLinks = LinkBusiness::getLinksForPosition(LinkPosition::Header);
    expect($headerLinks->first()->label)->toBe('Cached Header');
    expect(Cache::has('links.header'))->toBeTrue();

    $footerLinks = LinkBusiness::getLinksForPosition(LinkPosition::Footer);
    expect($footerLinks->first()->label)->toBe('Cached Footer');
    expect(Cache::has('links.footer'))->toBeTrue();
});

it('invalidates cache when a link is created', function () {
    Link::factory()->create(['position' => LinkPosition::Header]);
    LinkBusiness::getLinksForPosition(LinkPosition::Header);
    expect(Cache::has('links.header'))->toBeTrue();

    Link::factory()->create(['position' => LinkPosition::Header]);
    expect(Cache::has('links.header'))->toBeFalse();
});

it('invalidates cache when a link is updated', function () {
    $link = Link::factory()->create(['position' => LinkPosition::Header]);
    LinkBusiness::getLinksForPosition(LinkPosition::Header);
    expect(Cache::has('links.header'))->toBeTrue();

    $link->update(['label' => 'Updated Label']);
    expect(Cache::has('links.header'))->toBeFalse();
});

it('invalidates cache when a link is deleted', function () {
    $link = Link::factory()->create(['position' => LinkPosition::Header]);
    LinkBusiness::getLinksForPosition(LinkPosition::Header);
    expect(Cache::has('links.header'))->toBeTrue();

    $link->delete();
    expect(Cache::has('links.header'))->toBeFalse();
});
