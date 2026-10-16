<?php

use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake([]);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'blocker',
        'name' => 'Blocker',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', true);
});

it('records a Block activity and delivers it to the target inbox', function (): void {
    $target = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/troll',
        'inbox_url' => 'https://mastodon.social/users/troll/inbox',
        'username' => 'troll',
        'domain' => 'mastodon.social',
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendBlock(actor: $this->actor, objectUrl: $target->actor_url);

    $record = Activity::query()
        ->where('type', 'Block')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();
    expect($record->payload['type'])->toBe('Block');
    expect($record->payload['object'])->toBe($target->actor_url);
    expect($record->payload['to'])->toBe([$target->actor_url]);
    expect($record->remote_actor_id)->toBe($target->id);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($target) {
        return $job->inboxUrl === $target->inbox_url;
    });
});

it('records a failure when the block target cannot be resolved', function (): void {
    config()->set('activitypub.federation.enabled', false);

    $unknownUrl = 'https://nowhere.invalid/users/ghost';

    Bus::fake();

    app(ActivityPubService::class)->sendBlock(actor: $this->actor, objectUrl: $unknownUrl);

    $record = Activity::query()
        ->where('type', 'Block')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();
    expect($record->fresh()->debug['error'] ?? null)->toContain('block target');

    Bus::assertNotDispatched(DeliverActivity::class);
});
