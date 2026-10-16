<?php

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;

function seedOutboxActivities(Actor $actor, int $count, bool $isIncoming = false): void
{
    for ($i = 1; $i <= $count; $i++) {
        Activity::query()->create(attributes: [
            'actor_id' => $actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => $actor->actor_id.'/statuses/'.$i,
            'payload' => [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $actor->actor_id.'#Create/'.$i,
                'type' => 'Create',
                'actor' => $actor->actor_id,
                'object' => [
                    'type' => 'Note',
                    'id' => $actor->actor_id.'/statuses/'.$i,
                    'content' => 'Activity number '.$i,
                ],
            ],
            'status' => 'delivered',
            'is_incoming' => $isIncoming,
        ]);
    }
}

function createCollectionActor(string $username = 'collector'): Actor
{
    $keys = generateTestKeyPair();

    return Actor::query()->create(attributes: [
        'username' => $username,
        'name' => 'Collection Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
}

it('serves the outbox as a top-level OrderedCollection without inline items', function (): void {
    $actor = createCollectionActor();
    seedOutboxActivities(actor: $actor, count: 25);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor.outbox', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    expect($json['type'])->toBe('OrderedCollection');
    expect($json['id'])->toBe($actor->outbox_url);
    expect($json['totalItems'])->toBe(25);
    expect($json['first'])->toBe($actor->outbox_url.'?page=1');
    expect($json['last'])->toBe($actor->outbox_url.'?page=2');
    expect($json)->not->toHaveKey('orderedItems');
});

it('serves a non-empty outbox page 2 with correct links', function (): void {
    $actor = createCollectionActor(username: 'pager');
    seedOutboxActivities(actor: $actor, count: 25);

    $pageOne = $this->getJson(
        uri: route(name: 'activitypub.actor.outbox', parameters: ['actor' => $actor->username]).'?page=1',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    $pageTwo = $this->getJson(
        uri: route(name: 'activitypub.actor.outbox', parameters: ['actor' => $actor->username]).'?page=2',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($pageOne['type'])->toBe('OrderedCollectionPage');
    expect($pageOne['id'])->toBe($actor->outbox_url.'?page=1');
    expect($pageOne['partOf'])->toBe($actor->outbox_url);
    expect($pageOne['totalItems'])->toBe(25);
    expect($pageOne['orderedItems'])->toHaveCount(20);
    expect($pageOne['next'])->toBe($actor->outbox_url.'?page=2');
    expect($pageOne)->not->toHaveKey('prev');

    expect($pageTwo['type'])->toBe('OrderedCollectionPage');
    expect($pageTwo['id'])->toBe($actor->outbox_url.'?page=2');
    expect($pageTwo['partOf'])->toBe($actor->outbox_url);
    expect($pageTwo['orderedItems'])->toHaveCount(5);
    expect($pageTwo['orderedItems'])->not->toBeEmpty();
    expect($pageTwo['prev'])->toBe($actor->outbox_url.'?page=1');
    expect($pageTwo)->not->toHaveKey('next');

    // Pages must not repeat the same slice.
    $pageOneIds = array_column($pageOne['orderedItems'], 'id');
    $pageTwoIds = array_column($pageTwo['orderedItems'], 'id');
    expect(array_intersect($pageOneIds, $pageTwoIds))->toBeEmpty();
});

it('paginates the incoming inbox collection', function (): void {
    $actor = createCollectionActor(username: 'inboxer');
    seedOutboxActivities(actor: $actor, count: 21, isIncoming: true);

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.inbox', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['type'])->toBe('OrderedCollection');
    expect($top['totalItems'])->toBe(21);
    expect($top['last'])->toBe($actor->inbox_url.'?page=2');
    expect($top)->not->toHaveKey('orderedItems');

    $pageTwo = $this->getJson(
        uri: route(name: 'activitypub.actor.inbox', parameters: ['actor' => $actor->username]).'?page=2',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($pageTwo['orderedItems'])->toHaveCount(1);
    expect($pageTwo['prev'])->toBe($actor->inbox_url.'?page=1');
});

it('clamps an out-of-range page to the last page', function (): void {
    $actor = createCollectionActor(username: 'clamped');
    seedOutboxActivities(actor: $actor, count: 5);

    $json = $this->getJson(
        uri: route(name: 'activitypub.actor.outbox', parameters: ['actor' => $actor->username]).'?page=99',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($json['id'])->toBe($actor->outbox_url.'?page=1');
    expect($json['orderedItems'])->toHaveCount(5);
});

it('echoes application/ld+json with the activitystreams profile when requested', function (): void {
    $actor = createCollectionActor(username: 'ldjson');
    seedOutboxActivities(actor: $actor, count: 1);

    $response = $this->get(
        uri: route(name: 'activitypub.actor.outbox', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"'],
    );

    $response->assertStatus(status: 200);

    expect($response->headers->get('Content-Type'))
        ->toContain('application/ld+json')
        ->toContain('profile="https://www.w3.org/ns/activitystreams"');
});

/**
 * @return array<int, RemoteActor>
 */
function seedRemoteActors(int $count, string $prefix): array
{
    $actors = [];

    for ($i = 1; $i <= $count; $i++) {
        $actors[] = RemoteActor::query()->create(attributes: [
            'actor_url' => 'https://remote.example/users/'.$prefix.$i,
            'inbox_url' => 'https://remote.example/users/'.$prefix.$i.'/inbox',
            'username' => $prefix.$i,
            'domain' => 'remote.example',
        ]);
    }

    return $actors;
}

it('reports correct totalItems and paginates the followers collection', function (): void {
    $actor = createCollectionActor(username: 'followers');

    foreach (seedRemoteActors(count: 25, prefix: 'follower') as $remoteActor) {
        Follower::query()->create(attributes: [
            'actor_id' => $actor->id,
            'remote_actor_id' => $remoteActor->id,
            'status' => FollowerStatus::Accepted,
        ]);
    }

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.followers', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['type'])->toBe('OrderedCollection');
    expect($top['totalItems'])->toBe(25);
    expect($top['first'])->toBe($actor->followers_url.'?page=1');
    expect($top['last'])->toBe($actor->followers_url.'?page=2');
    expect($top)->not->toHaveKey('orderedItems');

    $pageTwo = $this->getJson(
        uri: route(name: 'activitypub.actor.followers', parameters: ['actor' => $actor->username]).'?page=2',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($pageTwo['type'])->toBe('OrderedCollectionPage');
    expect($pageTwo['orderedItems'])->toHaveCount(5);
    expect($pageTwo['prev'])->toBe($actor->followers_url.'?page=1');
});

it('reports correct totalItems and paginates the following collection', function (): void {
    $actor = createCollectionActor(username: 'following');

    foreach (seedRemoteActors(count: 25, prefix: 'following') as $remoteActor) {
        Following::query()->create(attributes: [
            'actor_id' => $actor->id,
            'remote_actor_id' => $remoteActor->id,
            'status' => FollowerStatus::Accepted,
        ]);
    }

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.following', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['type'])->toBe('OrderedCollection');
    expect($top['totalItems'])->toBe(25);
    expect($top['last'])->toBe($actor->following_url.'?page=2');

    $pageTwo = $this->getJson(
        uri: route(name: 'activitypub.actor.following', parameters: ['actor' => $actor->username]).'?page=2',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($pageTwo['orderedItems'])->toHaveCount(5);
    expect($pageTwo['prev'])->toBe($actor->following_url.'?page=1');
});
