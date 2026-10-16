<?php

use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Jobs\ProcessInboxActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedDomain;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
});

function postToInbox(array $payload): \Illuminate\Testing\TestResponse
{
    return test()->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'testuser']),
        data: $payload,
    );
}

it('rejects a blocked domain for a non-Follow activity before any fetch', function (): void {
    Http::fake();

    BlockedDomain::query()->create(['domain' => 'blocked.example.com']);

    $response = postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Like',
        'actor' => 'https://blocked.example.com/users/alice',
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ]);

    $response->assertStatus(status: 202);

    Http::assertNothingSent();

    expect(Activity::query()->where('actor_id', $this->actor->id)->where('is_incoming', true)->count())->toBe(0);
    expect(RemoteActor::query()->where('actor_url', 'https://blocked.example.com/users/alice')->exists())->toBeFalse();
});

it('rejects a blocked remote actor for a non-Follow activity', function (): void {
    Http::fake();

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/badactor',
        'inbox_url' => 'https://mastodon.social/users/badactor/inbox',
        'username' => 'badactor',
        'domain' => 'mastodon.social',
    ]);

    BlockedRemoteActor::query()->create(['remote_actor_id' => $remoteActor->id]);

    $response = postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Announce',
        'actor' => $remoteActor->actor_url,
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ]);

    $response->assertStatus(status: 202);

    Http::assertNothingSent();
    expect(Activity::query()->where('actor_id', $this->actor->id)->where('is_incoming', true)->count())->toBe(0);
});

it('normalizes blocked domains so casing and trailing dots cannot bypass the block', function (): void {
    Http::fake();

    BlockedDomain::query()->create(['domain' => 'Example.COM.']);

    // Stored normalized.
    expect(BlockedDomain::query()->value('domain'))->toBe('example.com');

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Like',
        'actor' => 'https://EXAMPLE.com/users/alice',
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(Activity::query()->where('actor_id', $this->actor->id)->where('is_incoming', true)->count())->toBe(0);
});

it('blocks the www variant of a blocked domain', function (): void {
    Http::fake();

    BlockedDomain::query()->create(['domain' => 'example.com']);

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Like',
        'actor' => 'https://www.example.com/users/alice',
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(Activity::query()->where('actor_id', $this->actor->id)->where('is_incoming', true)->count())->toBe(0);
});

it('blocks a punycode domain when the unicode domain is blocked', function (): void {
    if (! function_exists('idn_to_ascii')) {
        $this->markTestSkipped(reason: 'intl extension not available');
    }

    Http::fake();

    BlockedDomain::query()->create(['domain' => 'münchen.example']);

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Like',
        'actor' => 'https://xn--mnchen-3ya.example/users/alice',
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(Activity::query()->where('actor_id', $this->actor->id)->where('is_incoming', true)->count())->toBe(0);
});

it('processes a duplicate activity id only once', function (): void {
    Http::fake();

    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/remote#likes/1111',
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/42',
        'to' => [$this->actor->actor_id],
    ];

    postToInbox($payload)->assertStatus(status: 202);
    postToInbox($payload)->assertStatus(status: 202);

    expect(Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('is_incoming', true)
        ->where('activity_uri', 'https://remote.example.com/users/remote#likes/1111')
        ->count())->toBe(1);

    expect(Interaction::query()
        ->where('object_id', 'https://example.com/notes/42')
        ->count())->toBe(1);
});

it('deduplicates repeated Like deliveries into a single interaction', function (): void {
    Http::fake();

    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/remote#likes/2222',
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/99',
        'to' => [$this->actor->actor_id],
    ];

    postToInbox($payload)->assertStatus(status: 202);

    // Same like delivered under a different activity id (a genuine redelivery
    // by a server that mints new ids) must reactivate, not duplicate.
    $payload['id'] = 'https://remote.example.com/users/remote#likes/2223';
    postToInbox($payload)->assertStatus(status: 202);

    expect(Interaction::query()
        ->where('object_id', 'https://example.com/notes/99')
        ->where('type', 'Like')
        ->count())->toBe(1);
});

it('handles Undo whose object is a string activity id', function (): void {
    Http::fake();

    $likeId = 'https://remote.example.com/users/remote#likes/3333';

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $likeId,
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/77',
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(Interaction::query()->where('object_id', 'https://example.com/notes/77')->value('status'))
        ->toBe(InteractionStatus::Active);

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/remote#undo/3333',
        'type' => 'Undo',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => $likeId,
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(Interaction::query()->where('object_id', 'https://example.com/notes/77')->value('status'))
        ->toBe(InteractionStatus::Undone);
});

it('handles Undo(Block) sent as a string object id and lifts the block', function (): void {
    Http::fake();

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example.com/users/blocker',
        'inbox_url' => 'https://remote.example.com/users/blocker/inbox',
        'username' => 'blocker',
        'domain' => 'remote.example.com',
    ]);

    $blockId = 'https://remote.example.com/users/blocker#blocks/1';

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $blockId,
        'type' => 'Block',
        'actor' => $remoteActor->actor_url,
        'object' => $this->actor->actor_id,
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(BlockedRemoteActor::query()->where('remote_actor_id', $remoteActor->id)->exists())->toBeTrue();

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/blocker#undo/1',
        'type' => 'Undo',
        'actor' => $remoteActor->actor_url,
        'object' => $blockId,
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(BlockedRemoteActor::query()->where('remote_actor_id', $remoteActor->id)->exists())->toBeFalse();
});

it('handles Undo(Block) sent as an embedded Block object and lifts the block', function (): void {
    Http::fake();

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example.com/users/blocker2',
        'inbox_url' => 'https://remote.example.com/users/blocker2/inbox',
        'username' => 'blocker2',
        'domain' => 'remote.example.com',
    ]);

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/blocker2#blocks/2',
        'type' => 'Block',
        'actor' => $remoteActor->actor_url,
        'object' => $this->actor->actor_id,
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(BlockedRemoteActor::query()->where('remote_actor_id', $remoteActor->id)->exists())->toBeTrue();

    postToInbox([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/blocker2#undo/2',
        'type' => 'Undo',
        'actor' => $remoteActor->actor_url,
        'object' => [
            'type' => 'Block',
            'actor' => $remoteActor->actor_url,
            'object' => $this->actor->actor_id,
        ],
        'to' => [$this->actor->actor_id],
    ])->assertStatus(status: 202);

    expect(BlockedRemoteActor::query()->where('remote_actor_id', $remoteActor->id)->exists())->toBeFalse();
});

it('accepts an object-less activity without requiring @context', function (): void {
    Http::fake();

    $response = postToInbox([
        'type' => 'Delete',
        'actor' => 'https://remote.example.com/users/remote',
        'to' => [$this->actor->actor_id],
    ]);

    $response->assertStatus(status: 202);
});

it('still rejects an empty payload', function (): void {
    Http::fake();

    $response = postToInbox([]);

    $response->assertStatus(status: 422);
});

it('queues inbox processing through ProcessInboxActivity', function (): void {
    Queue::fake();

    app(\DanielPetrica\LaravelActivityPub\Actions\InboxProcessor::class)->queue(payload: [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/remote#likes/4444',
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/5',
        'to' => [$this->actor->actor_id],
    ]);

    Queue::assertPushed(ProcessInboxActivity::class, function (ProcessInboxActivity $job): bool {
        return $job->payload['id'] === 'https://remote.example.com/users/remote#likes/4444';
    });
});

it('processes inbox activities when the queued job runs', function (): void {
    Http::fake();

    $job = new ProcessInboxActivity(payload: [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => 'https://remote.example.com/users/remote#likes/5555',
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/6',
        'to' => [$this->actor->actor_id],
    ]);

    $job->handle();

    expect(Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('is_incoming', true)
        ->where('activity_uri', 'https://remote.example.com/users/remote#likes/5555')
        ->count())->toBe(1);
});
