<?php

use DanielPetrica\LaravelActivityPub\Enums\ActivityStatus;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityDispatcher;
use Illuminate\Support\Facades\Http;

function createSyncParityActivity(Actor $actor): Activity
{
    return Activity::query()->create(attributes: [
        'actor_id' => $actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://localhost/notes/sync-parity',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => 'https://localhost/users/'.$actor->username.'#create/sync-parity',
            'type' => 'Create',
            'actor' => 'https://localhost/users/'.$actor->username,
            'object' => ['type' => 'Note', 'content' => 'Hello'],
        ],
        'status' => 'pending',
        'is_incoming' => false,
    ]);
}

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'syncparity',
        'name' => 'Sync Parity',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://remote.example.com/users/follower',
        'inbox_url' => 'https://remote.example.com/users/follower/inbox',
        'username' => 'follower',
        'domain' => 'remote.example.com',
        'public_key_pem' => $keys['public'],
    ]);

    Follower::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    config()->set('activitypub.federation.enabled', true);
    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);
});

it('records a per-inbox row and marks the activity delivered on the sync path', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('', 202)]);

    $activity = createSyncParityActivity($this->actor);

    $results = app(ActivityDispatcher::class)->dispatchToFollowersSync(
        localActor: $this->actor,
        activity: $activity->payload,
        activityId: $activity->id,
    );

    expect($results)->toHaveCount(1)
        ->and($results[0]['inbox_url'])->toBe('https://remote.example.com/users/follower/inbox')
        ->and($results[0]['response_code'])->toBe(202);

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->activity_id)->toBe($activity->id)
        ->and($delivery->inbox_url)->toBe('https://remote.example.com/users/follower/inbox')
        ->and($delivery->status)->toBe(ActivityDelivery::STATUS_DELIVERED)
        ->and($delivery->response_code)->toBe(202)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->delivered_at)->not->toBeNull();

    expect($activity->fresh()->status)->toBe(ActivityStatus::Delivered);
});

it('keeps a transient 5xx sync delivery pending for a later reschedule', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('server error', 503)]);

    $activity = createSyncParityActivity($this->actor);

    $results = app(ActivityDispatcher::class)->dispatchToFollowersSync(
        localActor: $this->actor,
        activity: $activity->payload,
        activityId: $activity->id,
    );

    expect($results[0]['response_code'])->toBe(503);

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_PENDING)
        ->and($delivery->response_code)->toBe(503)
        ->and($delivery->last_error)->not->toBeNull();

    expect($activity->fresh()->status)->toBe(ActivityStatus::Pending);
});

it('marks a terminal 4xx sync delivery failed', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('gone', 410)]);

    $activity = createSyncParityActivity($this->actor);

    app(ActivityDispatcher::class)->dispatchToFollowersSync(
        localActor: $this->actor,
        activity: $activity->payload,
        activityId: $activity->id,
    );

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_FAILED)
        ->and($delivery->response_code)->toBe(410)
        ->and($delivery->last_error)->toContain('410');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Failed);
});

it('records a failed per-inbox row when the inbox URL is refused', function (): void {
    // Plain HTTP is refused by the SSRF guard, so DeliveryClient returns null.
    $this->remoteActor->update(['inbox_url' => 'http://insecure.example.com/users/x/inbox']);

    $activity = createSyncParityActivity($this->actor);

    $results = app(ActivityDispatcher::class)->dispatchToFollowersSync(
        localActor: $this->actor,
        activity: $activity->payload,
        activityId: $activity->id,
    );

    expect($results[0]['response_code'])->toBeNull();

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_FAILED)
        ->and($delivery->response_code)->toBeNull();

    expect($activity->fresh()->status)->toBe(ActivityStatus::Failed);
});

it('leaves no per-inbox rows when no activity is being tracked', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('', 202)]);

    app(ActivityDispatcher::class)->dispatchToFollowersSync(
        localActor: $this->actor,
        activity: ['type' => 'Create'],
    );

    expect(ActivityDelivery::query()->count())->toBe(0);
});
