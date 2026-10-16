<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;

it('returns 422 when resource parameter is missing', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger'),
    );

    $response->assertStatus(status: 422);
});

it('returns 422 when resource format is unsupported', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'not-a-resource',
        ]),
    );

    $response->assertStatus(status: 422);
});

it('returns 422 when acct URI is malformed', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:invalid',
        ]),
    );

    $response->assertStatus(status: 422);
});

it('returns 404 when domain does not match', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:user@other-domain.com',
        ]),
    );

    $response->assertStatus(status: 404);
});

it('returns 404 when actor does not exist', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:nonexistent@'.$domain,
        ]),
    );

    $response->assertStatus(status: 404);
});

it('returns valid JRD for an existing actor', function (): void {
    $parsedUrl = parse_url(url: config('activitypub.domain'));
    $domain = $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
        $domain .= ':'.$parsedUrl['port'];
    }

    $keys = generateTestKeyPair();

    $actor = Actor::query()->create(attributes: [
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:testuser@'.$domain,
        ]),
    );

    $response->assertStatus(status: 200);
    $response->assertJsonStructure([
        'subject',
        'aliases',
        'links',
    ]);
    $response->assertJson([
        'subject' => 'acct:testuser@'.$domain,
        'aliases' => [$actor->actor_id],
        'links' => [
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $actor->actor_id,
            ],
        ],
    ]);

    $response->assertHeader('Content-Type', 'application/jrd+json');
});

it('exposes profile-page and subscribe links', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'linkuser',
        'name' => 'Link User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:linkuser@localhost',
        ]),
    );

    $response->assertStatus(status: 200);

    $links = collect($response->json('links'))->keyBy('rel');

    expect($links)->toHaveKey('self');
    expect($links['self']['href'])->toBe($actor->actor_id);

    expect($links)->toHaveKey('http://webfinger.net/rel/profile-page');
    expect($links['http://webfinger.net/rel/profile-page']['type'])->toBe('text/html');
    expect($links['http://webfinger.net/rel/profile-page']['href'])->toBe($actor->actor_id);

    expect($links)->toHaveKey('http://ostatus.org/schema/1.0/subscribe');
    expect($links['http://ostatus.org/schema/1.0/subscribe']['template'])
        ->toBe('http://localhost/authorize_interaction?uri={uri}');
});

it('sets a permissive CORS header on WebFinger responses', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:anyone@localhost',
        ]),
    );

    $response->assertHeader('Access-Control-Allow-Origin', '*');
});

it('accepts an actor https URL as the resource', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'urluser',
        'name' => 'URL User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => $actor->actor_id,
        ]),
    );

    $response->assertStatus(status: 200);
    $response->assertJson(['subject' => 'acct:urluser@localhost']);
});

it('accepts a bare username@domain resource', function (): void {
    $keys = generateTestKeyPair();
    Actor::query()->create(attributes: [
        'username' => 'bareuser',
        'name' => 'Bare User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'bareuser@localhost',
        ]),
    );

    $response->assertStatus(status: 200);
    $response->assertJson(['subject' => 'acct:bareuser@localhost']);
});
