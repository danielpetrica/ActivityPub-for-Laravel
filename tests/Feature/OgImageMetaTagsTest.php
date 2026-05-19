<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders og:image and dimensions when metaImage is set', function () {
    $html = view('components.layouts.app', [
        'metaImage' => 'https://example.com/og.png',
        'slot' => '<div>content</div>',
    ])->render();

    expect($html)->toContain('property="og:image"', 'https://example.com/og.png');
    expect($html)->toContain('property="og:image:width"', 'content="1200"');
    expect($html)->toContain('property="og:image:height"', 'content="630"');
    expect($html)->toContain('property="og:image:type"', 'content="image/png"');
});

it('does not render og:image when metaImage is not set', function () {
    $html = view('components.layouts.app', [
        'slot' => '<div>content</div>',
    ])->render();

    expect($html)->not->toContain('property="og:image"');
});

it('renders twitter:image when metaImage is set', function () {
    $html = view('components.layouts.app', [
        'metaImage' => 'https://example.com/og.png',
        'slot' => '<div>content</div>',
    ])->render();

    expect($html)->toContain('name="twitter:image"', 'https://example.com/og.png');
});

it('uses summary_large_image when metaImage is set', function () {
    $html = view('components.layouts.app', [
        'metaImage' => 'https://example.com/og.png',
        'slot' => '<div>content</div>',
    ])->render();

    expect($html)->toContain('summary_large_image');
    expect($html)->not->toContain('summary"');
});

it('uses summary card when metaImage is not set', function () {
    $html = view('components.layouts.app', [
        'slot' => '<div>content</div>',
    ])->render();

    expect($html)->toContain('content="summary"');
});
