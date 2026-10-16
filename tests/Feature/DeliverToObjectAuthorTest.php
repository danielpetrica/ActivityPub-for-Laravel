<?php

use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'liker',
        'name' => 'Liker',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', true);
    // Keep the suite hermetic: never resolve mastodon.social through real DNS.
    config()->set('activitypub.security.allowed_hosts', ['mastodon.social']);
});

it('delivers to the real object author read from a cached activity payload', function (): void {
    $alice = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $objectUrl = 'https://mastodon.social/notes/1';

    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => $objectUrl,
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => [
                'id' => $objectUrl,
                'type' => 'Note',
                'attributedTo' => $alice->actor_url,
            ],
        ],
        'status' => 'received',
        'is_incoming' => true,
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendLike(actor: $this->actor, objectUrl: $objectUrl);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($alice) {
        return $job->inboxUrl === $alice->inbox_url;
    });
});

it('fetches the object and reads attributedTo when nothing is cached', function (): void {
    $alice = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $objectUrl = 'https://mastodon.social/notes/2';

    Http::fake([
        $objectUrl => Http::response(json_encode([
            'id' => $objectUrl,
            'type' => 'Note',
            'attributedTo' => $alice->actor_url,
        ]), 200, ['Content-Type' => 'application/activity+json']),
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendLike(actor: $this->actor, objectUrl: $objectUrl);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($alice) {
        return $job->inboxUrl === $alice->inbox_url;
    });
});

it('matches the author with case-insensitive equality and not an arbitrary same-domain actor', function (): void {
    $alice = RemoteActor::query()->create([
        'actor_url' => 'https://Mastodon.Social/Users/Alice',
        'inbox_url' => 'https://Mastodon.Social/Users/Alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $mallory = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/mallory',
        'inbox_url' => 'https://mastodon.social/users/mallory/inbox',
        'username' => 'mallory',
        'domain' => 'mastodon.social',
    ]);

    $objectUrl = 'https://mastodon.social/notes/3';

    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => $objectUrl,
        'payload' => [
            'type' => 'Create',
            'object' => [
                'id' => $objectUrl,
                'type' => 'Note',
                'attributedTo' => 'https://mastodon.social/users/alice',
            ],
        ],
        'status' => 'received',
        'is_incoming' => true,
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendLike(actor: $this->actor, objectUrl: $objectUrl);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($alice) {
        return $job->inboxUrl === $alice->inbox_url;
    });

    Bus::assertNotDispatched(DeliverActivity::class, function ($job) use ($mallory) {
        return $job->inboxUrl === $mallory->inbox_url;
    });
});

it('records a failure when the object author cannot be resolved', function (): void {
    Http::fake([]);

    $objectUrl = 'https://mastodon.social/notes/4';

    Bus::fake();

    app(ActivityPubService::class)->sendLike(actor: $this->actor, objectUrl: $objectUrl);

    Bus::assertNotDispatched(DeliverActivity::class);

    $like = Activity::query()
        ->where('type', 'Like')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($like)->not->toBeNull();
    expect($like->fresh()->debug)->toBeArray();
    expect($like->fresh()->debug['error'] ?? null)->toContain('author');
});
