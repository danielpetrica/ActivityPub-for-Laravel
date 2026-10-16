<?php

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'unfollower',
        'name' => 'Unfollower',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', true);
});

it('wraps the real recorded Follow id in the Undo activity', function (): void {
    $target = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/friend',
        'inbox_url' => 'https://mastodon.social/users/friend/inbox',
        'username' => 'friend',
        'domain' => 'mastodon.social',
    ]);

    $realFollowId = $this->actor->actor_id.'#follow/real-id-123';

    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'type' => 'Follow',
        'object_id' => $target->actor_url,
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $realFollowId,
            'type' => 'Follow',
            'actor' => $this->actor->actor_id,
            'object' => $target->actor_url,
        ],
        'status' => 'pending',
        'is_incoming' => false,
        'remote_actor_id' => $target->id,
    ]);

    $follower = Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $target->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendUnfollow(actor: $this->actor, follower: $follower);

    $undo = Activity::query()
        ->where('type', 'Undo')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($undo)->not->toBeNull();
    expect($undo->payload['object']['id'])->toBe($realFollowId);
    expect($undo->payload['to'])->toBe([$target->actor_url]);

    $this->assertDatabaseMissing('followers', ['id' => $follower->id]);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($target) {
        return $job->inboxUrl === $target->inbox_url;
    });
});
