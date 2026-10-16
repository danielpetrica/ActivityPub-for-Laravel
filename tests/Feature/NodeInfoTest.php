<?php

use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;

function createNodeInfoActor(string $username = 'nodeuser'): Actor
{
    $keys = generateTestKeyPair();

    return Actor::query()->create(attributes: [
        'username' => $username,
        'name' => 'Node Info Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
}

it('advertises the NodeInfo 2.0 discovery document', function (): void {
    $response = $this->getJson(uri: route(name: 'activitypub.nodeinfo.discovery'));

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json['links'][0]['rel'])->toBe('http://nodeinfo.diaspora.software/ns/schema/2.0');
    expect($json['links'][0]['href'])->toBe(route(name: 'activitypub.nodeinfo'));
});

it('serves a NodeInfo 2.0 document with the profile media type', function (): void {
    config()->set('activitypub.version', '1.2.3');

    $response = $this->get(uri: route(name: 'activitypub.nodeinfo'));

    $response->assertStatus(status: 200);
    $response->assertHeader(
        'Content-Type',
        'application/json; profile="http://nodeinfo.diaspora.software/ns/schema/2.0#"',
    );

    $json = $response->json();

    expect($json['version'])->toBe('2.0');
    expect($json['software']['name'])->toBe('activitypub-for-laravel');
    // NodeInfo requires a lowercase, space-free software name.
    expect($json['software']['name'])->toBe(mb_strtolower($json['software']['name']));
    expect($json['software']['version'])->toBe('1.2.3');
    expect($json['protocols'])->toBe(['activitypub']);
});

it('reports localPosts, localComments and active user windows', function (): void {
    $actor = createNodeInfoActor();
    $actor->update(['last_active_at' => now()->subDays(10)]);

    Activity::query()->create(attributes: [
        'actor_id' => $actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => $actor->actor_id.'/statuses/1',
        'payload' => ['type' => 'Create', 'actor' => $actor->actor_id],
        'status' => 'delivered',
        'is_incoming' => false,
    ]);

    $json = $this->getJson(uri: route(name: 'activitypub.nodeinfo'))->json();

    expect($json['usage'])->toHaveKey('localPosts');
    expect($json['usage'])->toHaveKey('localComments');
    expect($json['usage']['localPosts'])->toBe(1);
    expect($json['usage']['localComments'])->toBeInt();

    expect($json['usage']['users'])->toHaveKey('total');
    expect($json['usage']['users'])->toHaveKey('activeMonth');
    expect($json['usage']['users'])->toHaveKey('activeHalfyear');
    expect($json['usage']['users']['total'])->toBe(1);
    expect($json['usage']['users']['activeMonth'])->toBe(1);
    expect($json['usage']['users']['activeHalfyear'])->toBe(1);
});

it('returns an lrdd link whose type matches the JRD payload', function (): void {
    $response = $this->get(uri: route(name: 'activitypub.host-meta'));

    $response->assertStatus(status: 200);
    $response->assertHeader('Content-Type', 'application/xrd+xml');

    $body = $response->getContent();

    expect($body)->toContain('rel="lrdd"');
    expect($body)->toContain('type="application/jrd+json"');
    expect($body)->not->toContain('application/xrd+xml" template');
    // Scheme and host are derived from the same configured domain.
    expect($body)->toContain('template="http://localhost/.well-known/webfinger?resource={uri}"');
});

it('derives the host-meta scheme from the same source as the host', function (): void {
    config()->set('app.url', 'https://app.example.com');
    config()->set('activitypub.domain', 'http://fedi.example.com');

    $response = $this->get(uri: route(name: 'activitypub.host-meta'));

    $response->assertOk();

    // The scheme must follow the ActivityPub domain (http), not app.url (https).
    expect($response->getContent())
        ->toContain('template="http://fedi.example.com/.well-known/webfinger?resource={uri}"');
});
