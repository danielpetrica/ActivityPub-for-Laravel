<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;

it('returns 400 when resource parameter is missing', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger'),
    );

    $response->assertStatus(status: 400);
});

it('returns 400 when resource is not an acct URI', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'https://example.com/user',
        ]),
    );

    $response->assertStatus(status: 400);
});

it('returns 400 when acct URI is malformed', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.webfinger', parameters: [
            'resource' => 'acct:invalid',
        ]),
    );

    $response->assertStatus(status: 400);
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
