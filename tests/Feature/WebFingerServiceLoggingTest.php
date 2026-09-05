<?php

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', null);
});

afterEach(function (): void {
    Http::fake([]);
});

it('resolves WebFinger successfully with logging enabled', function (): void {
    Http::fake([
        'mastodon.social' => Http::response(
            body: json_encode([
                'subject' => 'acct:user@mastodon.social',
                'links' => [
                    [
                        'rel' => 'self',
                        'type' => 'application/activity+json',
                        'href' => 'https://mastodon.social/users/user',
                    ],
                ],
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/jrd+json'],
        ),
    ]);

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->not->toBeNull();
    expect($result['href'])->toBe('https://mastodon.social/users/user');
});

it('returns null on non-successful HTTP response with logging', function (): void {
    Http::fake([
        'mastodon.social' => Http::response(
            body: 'Not Found',
            status: 404,
        ),
    ]);

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->toBeNull();
});

it('returns null when response has no links with logging', function (): void {
    Http::fake([
        'mastodon.social' => Http::response(
            body: json_encode(['subject' => 'acct:user@mastodon.social']),
            status: 200,
            headers: ['Content-Type' => 'application/jrd+json'],
        ),
    ]);

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->toBeNull();
});

it('returns null when no activity+json link found with logging', function (): void {
    Http::fake([
        'mastodon.social' => Http::response(
            body: json_encode([
                'subject' => 'acct:user@mastodon.social',
                'links' => [
                    [
                        'rel' => 'self',
                        'type' => 'text/html',
                        'href' => 'https://mastodon.social/@user',
                    ],
                ],
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/jrd+json'],
        ),
    ]);

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->toBeNull();
});

it('returns null on HTTP exception with logging', function (): void {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('DNS resolution failed');
    });

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->toBeNull();
});

it('works identically when logging is disabled', function (): void {
    config()->set('activitypub.logging.enabled', false);

    Http::fake([
        'mastodon.social' => Http::response(
            body: json_encode([
                'subject' => 'acct:user@mastodon.social',
                'links' => [
                    [
                        'rel' => 'self',
                        'type' => 'application/activity+json',
                        'href' => 'https://mastodon.social/users/user',
                    ],
                ],
            ]),
            status: 200,
        ),
    ]);

    $service = app(\DanielPetrica\LaravelActivityPub\Services\WebFingerService::class);
    $result = $service->resolve('acct:user@mastodon.social');

    expect($result)->not->toBeNull();
    expect($result['href'])->toBe('https://mastodon.social/users/user');
});
