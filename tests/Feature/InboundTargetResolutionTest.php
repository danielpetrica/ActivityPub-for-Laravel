<?php

use DanielPetrica\LaravelActivityPub\Http\Controllers\InboxController;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;

/**
 * The first block of tests below targets the InboxController::resolveTargetActor
 * helper directly (via reflection). That helper only *reports* which local
 * actor an activity appears to be addressed to; the shared-inbox controller
 * discards the result and InboxProcessor performs its own independent
 * resolution. These cases therefore scope to the helper's addressing logic and
 * must not be read as proof that routing works end to end. The final test in
 * this file asserts the real end-to-end effect.
 */

function createInboundActor(string $username = 'target'): Actor
{
    $keys = generateTestKeyPair();

    return Actor::query()->create(attributes: [
        'username' => $username,
        'name' => 'Inbound Target',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function resolveTargetActor(array $payload): ?Actor
{
    $controller = app(InboxController::class);
    $method = new ReflectionMethod(InboxController::class, 'resolveTargetActor');
    $method->setAccessible(true);

    /** @var Actor|null $actor */
    $actor = $method->invoke($controller, $payload);

    return $actor;
}

it('resolves an actor addressed through the followers collection', function (): void {
    $actor = createInboundActor();

    $resolved = resolveTargetActor([
        'type' => 'Create',
        'to' => [$actor->followers_url],
    ]);

    expect($resolved?->id)->toBe($actor->id);
});

it('resolves an actor URL that has a trailing slash', function (): void {
    $actor = createInboundActor();

    $resolved = resolveTargetActor([
        'type' => 'Create',
        'to' => [$actor->actor_id.'/'],
    ]);

    expect($resolved?->id)->toBe($actor->id);
});

it('resolves an actor addressed via bto or bcc', function (): void {
    $actor = createInboundActor();

    expect(resolveTargetActor(['type' => 'Create', 'bto' => [$actor->actor_id]])?->id)->toBe($actor->id);
    expect(resolveTargetActor(['type' => 'Create', 'bcc' => [$actor->followers_url]])?->id)->toBe($actor->id);
});

it('resolves an actor from an embedded object id', function (): void {
    $actor = createInboundActor();

    $resolved = resolveTargetActor([
        'type' => 'Create',
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'object' => ['type' => 'Note', 'id' => $actor->actor_id],
    ]);

    expect($resolved?->id)->toBe($actor->id);
});

it('returns null when only remote actors are addressed', function (): void {
    createInboundActor();

    $resolved = resolveTargetActor([
        'type' => 'Create',
        'to' => ['https://mastodon.social/users/someone'],
        'cc' => ['https://mastodon.social/users/someone/followers'],
    ]);

    expect($resolved)->toBeNull();
});

it('processes a Follow addressed to the followers collection on the shared inbox', function (): void {
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);

    $actor = createInboundActor();

    $response = $this->postJson(
        uri: route(name: 'activitypub.shared-inbox'),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://mastodon.social/users/follower',
            'object' => $actor->actor_id,
            'id' => 'https://mastodon.social/users/follower#follow/1',
            'to' => [$actor->followers_url],
        ],
    );

    $response->assertStatus(status: 202);

    $remoteActor = RemoteActor::query()
        ->where('actor_url', 'https://mastodon.social/users/follower')
        ->first();

    expect($remoteActor)->not->toBeNull();

    expect(Follower::query()
        ->where('actor_id', $actor->id)
        ->where('remote_actor_id', $remoteActor->id)
        ->exists())->toBeTrue();

    // End-to-end: the Follow addressed through the followers collection was
    // routed to, and recorded for, the addressed local actor.
    expect(Activity::query()
        ->where('actor_id', $actor->id)
        ->where('is_incoming', true)
        ->where('type', 'Follow')
        ->exists())->toBeTrue();
});
