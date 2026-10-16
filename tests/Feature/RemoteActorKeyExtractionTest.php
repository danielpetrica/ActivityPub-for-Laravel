<?php

use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

it('extracts the public key from publicKey.publicKeyPem', function (): void {
    $keys = generateTestKeyPair();

    $actor = app(RemoteActorResolver::class)->upsertFromData(
        actorUri: 'https://keys.example.com/users/classic',
        data: [
            'preferredUsername' => 'classic',
            'inbox' => 'https://keys.example.com/users/classic/inbox',
            'publicKey' => ['publicKeyPem' => $keys['public']],
        ],
    );

    expect($actor->public_key_pem)->toBe($keys['public']);
});

it('extracts the public key from assertionMethod', function (): void {
    $keys = generateTestKeyPair();

    $actor = app(RemoteActorResolver::class)->upsertFromData(
        actorUri: 'https://keys.example.com/users/assertion',
        data: [
            'preferredUsername' => 'assertion',
            'inbox' => 'https://keys.example.com/users/assertion/inbox',
            'assertionMethod' => [
                'type' => 'Multikey',
                'publicKeyPem' => $keys['public'],
            ],
        ],
    );

    expect($actor->public_key_pem)->toBe($keys['public']);
});

it('extracts the public key from a list of assertionMethod entries', function (): void {
    $keys = generateTestKeyPair();

    $actor = app(RemoteActorResolver::class)->upsertFromData(
        actorUri: 'https://keys.example.com/users/assertion-list',
        data: [
            'preferredUsername' => 'assertion-list',
            'inbox' => 'https://keys.example.com/users/assertion-list/inbox',
            'assertionMethod' => [
                ['type' => 'Multikey', 'publicKeyPem' => $keys['public']],
            ],
        ],
    );

    expect($actor->public_key_pem)->toBe($keys['public']);
});

it('decodes a Multikey publicKeyMultibase carrying a PEM key', function (): void {
    $keys = generateTestKeyPair();

    $multibase = 'u'.rtrim(strtr(base64_encode($keys['public']), '+/', '-_'), '=');

    $actor = app(RemoteActorResolver::class)->upsertFromData(
        actorUri: 'https://keys.example.com/users/multikey',
        data: [
            'preferredUsername' => 'multikey',
            'inbox' => 'https://keys.example.com/users/multikey/inbox',
            'assertionMethod' => [
                'type' => 'Multikey',
                'publicKeyMultibase' => $multibase,
            ],
        ],
    );

    expect($actor->public_key_pem)->toBe($keys['public']);
});

it('returns a null key when no key is present', function (): void {
    $actor = app(RemoteActorResolver::class)->upsertFromData(
        actorUri: 'https://keys.example.com/users/nokey',
        data: [
            'preferredUsername' => 'nokey',
            'inbox' => 'https://keys.example.com/users/nokey/inbox',
        ],
    );

    expect($actor->public_key_pem)->toBeNull();
});
