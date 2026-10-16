<?php

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake();

    config()->set('activitypub.security.allowed_hosts', ['mastodon.social']);
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', true);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'unblocker',
        'name' => 'Unblocker',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

it('records and delivers an Undo(Block) to the target inbox', function (): void {
    $target = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://mastodon.social/users/troll',
        'inbox_url' => 'https://mastodon.social/users/troll/inbox',
        'username' => 'troll',
        'domain' => 'mastodon.social',
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendUnblock(actor: $this->actor, remoteActor: $target);

    $record = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Undo')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->payload['type'])->toBe('Undo')
        ->and($record->payload['object']['type'])->toBe('Block')
        ->and($record->payload['object']['object'])->toBe($target->actor_url)
        ->and($record->payload['to'])->toBe([$target->actor_url])
        ->and($record->remote_actor_id)->toBe($target->id);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($target): bool {
        return $job->inboxUrl === $target->inbox_url;
    });
});

it('removes both the follower and following relationship on an inbound Block', function (): void {
    $blocker = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://mastodon.social/users/blocker',
        'inbox_url' => 'https://mastodon.social/users/blocker/inbox',
        'username' => 'blocker',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $blocker->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Following::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $blocker->id,
        'status' => FollowerStatus::Accepted,
    ]);

    app(InboxProcessor::class)->process(payload: [
        'type' => 'Block',
        'id' => 'https://mastodon.social/users/blocker#blocks/1',
        'actor' => $blocker->actor_url,
        'object' => $this->actor->actor_id,
        'to' => [$this->actor->actor_id],
    ]);

    expect(Follower::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('remote_actor_id', '=', $blocker->id)
        ->exists())->toBeFalse();

    expect(Following::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('remote_actor_id', '=', $blocker->id)
        ->exists())->toBeFalse();

    expect(BlockedRemoteActor::query()
        ->where('remote_actor_id', '=', $blocker->id)
        ->exists())->toBeTrue();
});
