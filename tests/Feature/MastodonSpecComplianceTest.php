<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;

beforeEach(function (): void {
    config()->set('activitypub.domain', 'http://localhost');
    config()->set('activitypub.http_signatures.enabled', false);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'daniel',
        'name' => 'Daniel',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

// ---------------------------------------------------------------------------
// WebFinger compliance (per Mastodon spec)
// ---------------------------------------------------------------------------

it('returns valid JRD with subject as acct URI', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:daniel@'.$domain,
        ]),
    );

    $response->assertStatus(status: 200);
    $response->assertJson([
        'subject' => 'acct:daniel@'.$domain,
    ]);
});

it('returns self link with application/activity+json type', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:daniel@'.$domain,
        ]),
    );

    $response->assertStatus(status: 200);

    $json = $response->json();
    $selfLinks = array_filter($json['links'], fn ($link) => ($link['rel'] ?? '') === 'self');

    expect($selfLinks)->not->toBeEmpty();

    $selfLink = array_values($selfLinks)[0];
    expect($selfLink['type'])->toBe('application/activity+json');
    expect($selfLink['href'])->toBe($this->actor->actor_id);
});

it('returns aliases array containing actor URL', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:daniel@'.$domain,
        ]),
    );

    $response->assertStatus(status: 200);

    $json = $response->json();
    expect($json)->toHaveKey('aliases');
    expect($json['aliases'])->toBeArray();
    expect($json['aliases'])->toContain($this->actor->actor_id);
});

it('returns proper content-type header', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:daniel@'.$domain,
        ]),
    );

    $response->assertStatus(status: 200);
    $response->assertHeader('Content-Type', 'application/jrd+json');
});

// ---------------------------------------------------------------------------
// Actor profile compliance (per Mastodon ActivityPub spec)
// ---------------------------------------------------------------------------

it('returns actor with required ActivityPub properties', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json)->toHaveKey('@context');
    expect($json)->toHaveKey('id');
    expect($json)->toHaveKey('type');
    expect($json)->toHaveKey('preferredUsername');
    expect($json)->toHaveKey('name');
    expect($json)->toHaveKey('url');
    expect($json)->toHaveKey('inbox');
    expect($json)->toHaveKey('outbox');
    expect($json)->toHaveKey('followers');
    expect($json)->toHaveKey('following');
    expect($json)->toHaveKey('publicKey');
});

it('returns inbox and outbox as OrderedCollection', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json['inbox'])
        ->toHaveKey('type', 'OrderedCollection')
        ->toHaveKey('id')
        ->toHaveKey('totalItems')
        ->toHaveKey('first');

    expect($json['outbox'])
        ->toHaveKey('type', 'OrderedCollection')
        ->toHaveKey('id')
        ->toHaveKey('totalItems')
        ->toHaveKey('first');
});

it('returns publicKey with correct structure', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json['publicKey'])
        ->toHaveKey('id')
        ->toHaveKey('type', 'Key')
        ->toHaveKey('owner')
        ->toHaveKey('publicKeyPem');

    expect($json['publicKey']['owner'])->toBe($this->actor->actor_id);
    expect($json['publicKey']['id'])->toBe($this->actor->key_id);
});

it('returns endpoint with sharedInbox', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json)->toHaveKey('endpoints');
    expect($json['endpoints'])->toHaveKey('sharedInbox');
    expect($json['endpoints']['sharedInbox'])->toBeString();
});

it('includes manuallyApprovesFollowers flag', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();
    expect($json)->toHaveKey('manuallyApprovesFollowers');
    expect($json['manuallyApprovesFollowers'])->toBeBool();
});

it('includes discoverable flag', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    // toot:discoverable must be present in @context
    expect($json['@context'])->toContain(['discoverable' => 'http://joinmastodon.org/ns#discoverable']);

    // discoverable must be present as a top-level property
    expect($json)->toHaveKey('discoverable');
    expect($json['discoverable'])->toBeBool();
});

it('includes published timestamp', function (): void {
    $this->actor->update(['published' => now()]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();
    expect($json)->toHaveKey('published');
    expect($json['published'])->not->toBeNull();

    // Verify it is valid ISO8601 by parsing it back
    $parsed = \Carbon\Carbon::parse($json['published']);
    expect($parsed->isValid())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Follow flow compliance (per Mastodon ActivityPub spec)
// ---------------------------------------------------------------------------

it('builds Accept activity wrapping original Follow', function (): void {
    $remoteActorUrl = 'https://mastodon.social/users/remoteuser';

    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => $remoteActorUrl,
        'object' => $this->actor->actor_id,
        'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
    ];

    $accept = (new ActivityBuilder())->accept(
        actor: $this->actor,
        originalPayload: $followPayload,
    );

    expect($accept['object'])->toBe($followPayload);
    expect($accept['object']['type'])->toBe('Follow');
    expect($accept['object']['actor'])->toBe($remoteActorUrl);
    expect($accept['object']['object'])->toBe($this->actor->actor_id);
});

it('Accept activity has to field pointing to remote actor', function (): void {
    $remoteActorUrl = 'https://mastodon.social/users/remoteuser';

    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => $remoteActorUrl,
        'object' => $this->actor->actor_id,
        'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
    ];

    $accept = (new ActivityBuilder())->accept(
        actor: $this->actor,
        originalPayload: $followPayload,
    );

    expect($accept)->toHaveKey('to');
    expect($accept['to'])->toBeArray();
    expect($accept['to'])->toContain($remoteActorUrl);
});

it('Accept activity has actor set to local actor', function (): void {
    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => 'https://mastodon.social/users/remoteuser',
        'object' => $this->actor->actor_id,
        'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
    ];

    $accept = (new ActivityBuilder())->accept(
        actor: $this->actor,
        originalPayload: $followPayload,
    );

    expect($accept['actor'])->toBe($this->actor->actor_id);
});

it('creates RemoteActor record from incoming Follow', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $remoteActorUrl = 'https://mastodon.social/users/newfollower';

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActorUrl,
            'object' => $this->actor->actor_id,
            'id' => 'https://mastodon.social/users/newfollower#follow/99999',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $remoteActor = RemoteActor::query()
        ->where('actor_url', $remoteActorUrl)
        ->first();

    expect($remoteActor)->not->toBeNull();
    expect($remoteActor->actor_url)->toBe($remoteActorUrl);
});

it('Accept activity has unique id', function (): void {
    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => 'https://mastodon.social/users/remoteuser',
        'object' => $this->actor->actor_id,
        'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
    ];

    $accept1 = (new ActivityBuilder())->accept(
        actor: $this->actor,
        originalPayload: $followPayload,
    );

    $accept2 = (new ActivityBuilder())->accept(
        actor: $this->actor,
        originalPayload: $followPayload,
    );

    expect($accept1['id'])->not->toBe($accept2['id']);
    expect($accept1['id'])->toBeString();
    expect($accept2['id'])->toBeString();
    expect($accept1['id'])->toContain($this->actor->actor_id);
});

// ---------------------------------------------------------------------------
// HTTP Signature compliance
// ---------------------------------------------------------------------------

it('rejects inbox POST without signature when enabled', function (): void {
    config()->set('activitypub.http_signatures.enabled', true);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://mastodon.social/users/remoteuser',
            'object' => $this->actor->actor_id,
            'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
        ],
    );

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Signature header required.']);
});

it('accepts inbox POST with valid signature format', function (): void {
    config()->set('activitypub.http_signatures.enabled', false);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://mastodon.social/users/remoteuser',
            'object' => $this->actor->actor_id,
            'id' => 'https://mastodon.social/users/remoteuser#follow/12345',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);
});
