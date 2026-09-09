<?php

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'daniel',
        'name' => 'Daniel Petrica',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
});

it('builds Accept activity with to field for Mastodon', function (): void {
    $actor = $this->actor;

    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => 'https://infosec.exchange/users/mastodonuser',
        'object' => $actor->actor_id,
        'id' => 'https://infosec.exchange/users/mastodonuser#follow/12345',
    ];

    $accept = (new ActivityBuilder())->accept(
        actor: $actor,
        originalPayload: $followPayload,
    );

    expect($accept)
        ->toHaveKey('@context', 'https://www.w3.org/ns/activitystreams')
        ->toHaveKey('type', 'Accept')
        ->toHaveKey('actor', $actor->actor_id)
        ->toHaveKey('object', $followPayload)
        ->toHaveKey('to')
        ->and($accept['to'])->toBeArray()
        ->and($accept['to'])->toContain('https://infosec.exchange/users/mastodonuser');
});

it('builds Accept with nested actor object from Mastodon payload', function (): void {
    $actor = $this->actor;

    $followPayload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => [
            'type' => 'Person',
            'id' => 'https://infosec.exchange/users/mastodonuser',
            'preferredUsername' => 'mastodonuser',
        ],
        'object' => $actor->actor_id,
    ];

    $accept = (new ActivityBuilder())->accept(
        actor: $actor,
        originalPayload: $followPayload,
    );

    expect($accept['to'])->toContain('https://infosec.exchange/users/mastodonuser');
});

it('records incoming Follow and builds Accept with to field', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://infosec.exchange/users/mastodonuser',
            'object' => $this->actor->actor_id,
            'id' => 'https://infosec.exchange/users/mastodonuser#follow/99999',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    // Follower record created
    $this->assertDatabaseHas(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'status' => 'accepted',
    ]);

    // Incoming Follow recorded
    $this->assertDatabaseHas(table: 'activities', data: [
        'actor_id' => $this->actor->id,
        'type' => 'Follow',
        'is_incoming' => true,
    ]);

    // Accept activity recorded with correct structure
    $acceptRecord = Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('type', 'Accept')
        ->where('is_incoming', false)
        ->first();

    expect($acceptRecord)->not->toBeNull();
    expect($acceptRecord->payload['to'])->toContain('https://infosec.exchange/users/mastodonuser');
    expect($acceptRecord->payload['type'])->toBe('Accept');
    expect($acceptRecord->payload['object']['type'])->toBe('Follow');
    expect($acceptRecord->payload['object']['actor'])->toBe('https://infosec.exchange/users/mastodonuser');
});

it('dispatches DeliverActivity when federation is enabled', function (): void {
    Bus::fake();
    config()->set('activitypub.federation.enabled', true);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://infosec.exchange/users/mastodonuser',
        'inbox_url' => 'https://infosec.exchange/users/mastodonuser/inbox',
        'username' => 'mastodonuser',
        'domain' => 'infosec.exchange',
    ]);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActor->actor_url,
            'object' => $this->actor->actor_id,
            'id' => 'https://infosec.exchange/users/mastodonuser#follow/88888',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($remoteActor) {
        return $job->inboxUrl === $remoteActor->inbox_url;
    });
});

it('always dispatches Accept delivery even when federation is disabled', function (): void {
    Bus::fake();
    config()->set('activitypub.federation.enabled', false);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://infosec.exchange/users/mastodonuser',
            'object' => $this->actor->actor_id,
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    Bus::assertDispatched(DeliverActivity::class);
});

it('returns actor profile with OrderedCollection inbox and outbox', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);

    $json = $response->json();

    // inbox must be an OrderedCollection
    expect($json['inbox'])
        ->toHaveKey('id', $this->actor->inbox_url)
        ->toHaveKey('type', 'OrderedCollection')
        ->toHaveKey('totalItems')
        ->toHaveKey('first');

    // outbox must be an OrderedCollection
    expect($json['outbox'])
        ->toHaveKey('id', $this->actor->outbox_url)
        ->toHaveKey('type', 'OrderedCollection')
        ->toHaveKey('totalItems')
        ->toHaveKey('first');
});

it('returns followers and following as proper collection URLs', function (): void {
    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'daniel']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $json = $response->json();

    expect($json['followers'])->toBe($this->actor->followers_url);
    expect($json['following'])->toBe($this->actor->following_url);
});

it('delivers Accept to remote inbox with correct structure', function (): void {
    Bus::fake();
    config()->set('activitypub.federation.enabled', true);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/testuser',
        'inbox_url' => 'https://mastodon.social/users/testuser/inbox',
        'username' => 'testuser',
        'domain' => 'mastodon.social',
    ]);

    // Simulate incoming Follow
    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActor->actor_url,
            'object' => $this->actor->actor_id,
            'id' => 'https://mastodon.social/users/testuser#follow/77777',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    // Find the Accept activity that was recorded
    $acceptActivity = Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('type', 'Accept')
        ->where('is_incoming', false)
        ->first();

    expect($acceptActivity)->not->toBeNull();
    expect($acceptActivity->payload['to'])->toContain($remoteActor->actor_url);
    expect($acceptActivity->payload['type'])->toBe('Accept');
    expect($acceptActivity->status->value)->toBe('pending');

    // Verify the Accept wraps the original Follow
    $object = $acceptActivity->payload['object'];
    expect($object['type'])->toBe('Follow');
    expect($object['actor'])->toBe($remoteActor->actor_url);
    expect($object['object'])->toBe($this->actor->actor_id);
});

it('creates Follower with accepted status on incoming Follow', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://hachyderm.io/users/newfollower',
            'object' => $this->actor->actor_id,
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $remoteActor = RemoteActor::where('actor_url', 'https://hachyderm.io/users/newfollower')->first();
    expect($remoteActor)->not->toBeNull();

    $follower = Follower::where('actor_id', $this->actor->id)
        ->where('remote_actor_id', $remoteActor->id)
        ->first();

    expect($follower)->not->toBeNull();
    expect($follower->status)->toBe(FollowerStatus::Accepted);
});

it('removes follower on Undo Follow', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/unfollower',
        'inbox_url' => 'https://mastodon.social/users/unfollower/inbox',
        'username' => 'unfollower',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => $remoteActor->actor_url,
            'object' => [
                'type' => 'Follow',
                'actor' => $remoteActor->actor_url,
                'object' => $this->actor->actor_id,
            ],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
    ]);
});

it('removes follower and Following on Reject', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/rejector',
        'inbox_url' => 'https://mastodon.social/users/rejector/inbox',
        'username' => 'rejector',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Following::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Pending,
    ]);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Reject',
            'actor' => $remoteActor->actor_url,
            'object' => [
                'type' => 'Follow',
                'actor' => $remoteActor->actor_url,
                'object' => $this->actor->actor_id,
            ],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
    ]);

    $this->assertDatabaseMissing(table: 'following', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
    ]);
});
