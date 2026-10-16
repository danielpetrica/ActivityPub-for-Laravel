<?php

use DanielPetrica\LaravelActivityPub\Http\Resources\ActorResource;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\Request;

it('returns 404 for a non-existent actor', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'nonexistent']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 404);
});

it('returns actor JSON-LD with Accept header', function (): void {
    $keys = generateTestKeyPair();
    $domain = parse_url(url: config('app.url'), component: PHP_URL_HOST);

    $actor = Actor::query()->create(attributes: [
        'username' => 'jane',
        'name' => 'Jane Doe',
        'summary' => '<p>A test actor.</p>',
        'icon_url' => 'https://example.com/avatar.jpg',
        'image_url' => 'https://example.com/banner.jpg',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'jane']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);
    $response->assertJsonStructure([
        '@context',
        'id',
        'type',
        'preferredUsername',
        'name',
        'url',
        'inbox',
        'outbox',
        'followers',
        'following',
        'manuallyApprovesFollowers',
        'endpoints' => [
            'sharedInbox',
        ],
        'publicKey' => [
            'id',
            'owner',
            'publicKeyPem',
        ],
    ]);

    $baseUrl = config('app.url');

    $response->assertJson([
        'type' => 'Person',
        'preferredUsername' => 'jane',
        'name' => 'Jane Doe',
        'summary' => '<p>A test actor.</p>',
        'id' => $baseUrl.'/users/jane',
        'url' => $baseUrl.'/users/jane',
    ]);

    // Verify URL structure. inbox/outbox/followers/following/featured are
    // plain collection IRIs (not inline collections with stale counts).
    $response->assertJson([
        'inbox' => $baseUrl.'/users/jane/inbox',
        'outbox' => $baseUrl.'/users/jane/outbox',
        'followers' => $baseUrl.'/users/jane/followers',
        'following' => $baseUrl.'/users/jane/following',
        'featured' => $baseUrl.'/users/jane/featured',
        'publicKey' => [
            'id' => $baseUrl.'/users/jane#main-key',
            'owner' => $baseUrl.'/users/jane',
        ],
    ]);
});

it('returns actor JSON-LD without explicit Accept header', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'bob',
        'name' => 'Bob',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'bob']),
    );

    $response->assertStatus(status: 200);
    $response->assertJson([
        'type' => 'Person',
        'preferredUsername' => 'bob',
    ]);
});

it('returns actor JSON-LD without Accept header returns correct Content-Type', function (): void {
    $keys = generateTestKeyPair();
    Actor::query()->create(attributes: [
        'username' => 'carol',
        'name' => 'Carol',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'carol']),
    );

    $response->assertStatus(status: 200);
    $response->assertHeader(headerName: 'Content-Type', value: 'application/activity+json');
});

it('omits optional fields when not set', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'minimal',
        'name' => 'Minimal',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'minimal']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);
    $response->assertJsonMissingPath(path: 'summary');
    $response->assertJsonMissingPath(path: 'icon');
    $response->assertJsonMissingPath(path: 'image');
    $response->assertJsonMissingPath(path: 'sensitive');
    $response->assertJsonMissingPath(path: 'alsoKnownAs');
});

it('derives icon and image media types from the URL extension', function (): void {
    $keys = generateTestKeyPair();
    Actor::query()->create(attributes: [
        'username' => 'imagemedia',
        'name' => 'Image Media',
        'icon_url' => 'https://cdn.example.com/avatar.png',
        'image_url' => 'https://cdn.example.com/banner.webp',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $json = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'imagemedia']),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($json['icon']['mediaType'])->toBe('image/png');
    expect($json['image']['mediaType'])->toBe('image/webp');
});

it('emits sensitive only when the actor opts in', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'sensitiveuser',
        'name' => 'Sensitive User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $actor->sensitive = true;

    $data = (new ActorResource(resource: $actor))
        ->toArray(request: Request::create(uri: '/'));

    expect($data['sensitive'])->toBeTrue();
});

it('emits alsoKnownAs when the actor exposes it', function (): void {
    $keys = generateTestKeyPair();
    $actor = Actor::query()->create(attributes: [
        'username' => 'aliasuser',
        'name' => 'Alias User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $actor->also_known_as = ['https://other.example.com/@aliasuser'];

    $data = (new ActorResource(resource: $actor))
        ->toArray(request: Request::create(uri: '/'));

    expect($data['alsoKnownAs'])->toBe(['https://other.example.com/@aliasuser']);
});
