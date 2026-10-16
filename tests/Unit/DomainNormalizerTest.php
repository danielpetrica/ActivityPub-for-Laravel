<?php

use DanielPetrica\LaravelActivityPub\Helpers\DomainNormalizer;

it('lowercases and strips trailing dots', function (): void {
    expect(DomainNormalizer::normalize('Example.COM.'))->toBe('example.com');
});

it('strips a numeric port', function (): void {
    expect(DomainNormalizer::normalize('example.com:8443'))->toBe('example.com');
});

it('returns null for empty input', function (): void {
    expect(DomainNormalizer::normalize(null))->toBeNull();
    expect(DomainNormalizer::normalize(' '))->toBeNull();
    expect(DomainNormalizer::normalize('.'))->toBeNull();
});

it('extracts a normalized host from a url', function (): void {
    expect(DomainNormalizer::hostFromUrl('https://Example.com./users/a'))->toBe('example.com');
});

it('produces the www and bare variants as candidates', function (): void {
    expect(DomainNormalizer::candidates('example.com'))
        ->toContain('example.com')
        ->toContain('www.example.com');

    expect(DomainNormalizer::candidates('www.example.com'))
        ->toContain('www.example.com')
        ->toContain('example.com');
});

it('converts an internationalized domain to punycode', function (): void {
    if (! function_exists('idn_to_ascii')) {
        $this->markTestSkipped(reason: 'intl extension not available');
    }

    expect(DomainNormalizer::normalize('münchen.example'))->toBe('xn--mnchen-3ya.example');
});
