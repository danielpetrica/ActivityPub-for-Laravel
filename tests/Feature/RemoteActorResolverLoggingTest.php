<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('activitypub.logging.enabled', true);
    config()->set('activitypub.logging.channel', null);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

afterEach(function (): void {
    Http::fake([]);
});

it('fetches remote actor data successfully with logging enabled', function (): void {
    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: json_encode([
                'type' => 'Person',
                'preferredUsername' => 'user',
                'inbox' => 'https://mastodon.social/users/user/inbox',
                'name' => 'Test User',
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/activity+json'],
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->fetchActorData('https://mastodon.social/users/user');

    expect($result)->not->toBeNull();
    expect($result['preferredUsername'])->toBe('user');
    expect($result['inbox'])->toBe('https://mastodon.social/users/user/inbox');
});

it('returns null on non-successful HTTP response with logging', function (): void {
    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: 'Forbidden',
            status: 403,
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->fetchActorData('https://mastodon.social/users/user');

    expect($result)->toBeNull();
});

it('returns null on empty JSON response with logging', function (): void {
    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: '',
            status: 200,
            headers: ['Content-Type' => 'application/activity+json'],
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->fetchActorData('https://mastodon.social/users/user');

    expect($result)->toBeNull();
});

it('returns null on HTTP exception with logging', function (): void {
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->fetchActorData('https://mastodon.social/users/user');

    expect($result)->toBeNull();
});

it('works identically when logging is disabled', function (): void {
    config()->set('activitypub.logging.enabled', false);

    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: json_encode([
                'type' => 'Person',
                'preferredUsername' => 'user',
                'inbox' => 'https://mastodon.social/users/user/inbox',
            ]),
            status: 200,
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->fetchActorData('https://mastodon.social/users/user');

    expect($result)->not->toBeNull();
    expect($result['preferredUsername'])->toBe('user');
});

it('resolves remote actor successfully with logging', function (): void {
    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: json_encode([
                'type' => 'Person',
                'preferredUsername' => 'user',
                'inbox' => 'https://mastodon.social/users/user/inbox',
            ]),
            status: 200,
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->resolve('https://mastodon.social/users/user');

    expect($result)->not->toBeNull();
    expect($result->username)->toBe('user');
    expect($result->domain)->toBe('mastodon.social');
});

it('returns null when resolve fails with logging', function (): void {
    Http::fake([
        'https://mastodon.social/users/user' => Http::response(
            body: 'Not Found',
            status: 404,
        ),
    ]);

    $resolver = app(\DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver::class);
    $result = $resolver->resolve('https://mastodon.social/users/user');

    expect($result)->toBeNull();
});
