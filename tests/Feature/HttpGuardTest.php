<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

it('prevents stray outbound HTTP requests by default', function (): void {
    // The suite is hermetic: TestCase installs Http::preventStrayRequests().
    // Any test that forgets to Http::fake() an outbound call fails fast here
    // instead of depending on the real network.
    expect(fn () => Http::get('https://mastodon.social/users/someone'))
        ->toThrow(StrayRequestException::class);
});

it('stubs the shared federation hosts with fakeFederationHttp', function (): void {
    fakeFederationHttp();

    $inbox = Http::post('https://mastodon.social/users/someone/inbox');
    $actor = Http::get('https://infosec.exchange/users/someone');

    expect($inbox->status())->toBe(202);
    expect($actor->status())->toBe(200);
    expect($actor->header('Content-Type'))->toContain('application/activity+json');
});
