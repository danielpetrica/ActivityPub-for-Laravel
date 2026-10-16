<?php

use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'primary',
        'name' => 'Primary',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.security.allowed_hosts', ['example.com']);

    Http::fake();
});

/**
 * @return array<string, mixed>
 */
function likePayload(string $actorUrl, string $activityId, string $objectId): array
{
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $activityId,
        'type' => 'Like',
        'actor' => $actorUrl,
        'object' => $objectId,
        'to' => [test()->actor->actor_id],
    ];
}

it('keeps repeated likes from the same remote actor idempotent', function (): void {
    $actorUrl = 'https://example.com/users/alice';
    $objectUrl = 'https://example.com/notes/1';

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: likePayload($actorUrl, 'https://example.com/users/alice#likes/1', $objectUrl),
    )->assertStatus(status: 202);

    // A redelivery of the same like under a fresh activity id: the handler's
    // updateOrCreate must find the existing row and not insert a duplicate.
    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: likePayload($actorUrl, 'https://example.com/users/alice#likes/2', $objectUrl),
    )->assertStatus(status: 202);

    expect(Interaction::query()
        ->where('object_id', '=', $objectUrl)
        ->where('type', '=', InteractionType::Like)
        ->count())->toBe(1);
});

it('persists likes from two distinct remote actors on the same object', function (): void {
    $objectUrl = 'https://example.com/notes/2';

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: likePayload('https://example.com/users/alice', 'https://example.com/users/alice#likes/3', $objectUrl),
    )->assertStatus(status: 202);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: likePayload('https://example.com/users/bob', 'https://example.com/users/bob#likes/3', $objectUrl),
    )->assertStatus(status: 202);

    $likeCount = Interaction::query()
        ->where('object_id', '=', $objectUrl)
        ->where('type', '=', InteractionType::Like)
        ->count();

    expect($likeCount)->toBe(2);

    // Attribution: the local recipient owns the interaction; the remote
    // engager is recorded separately, which is what keeps the two rows apart.
    expect(Interaction::query()
        ->where('object_id', '=', $objectUrl)
        ->distinct()
        ->count('remote_actor_id'))->toBe(2);
});

it('rejects a duplicate interaction at the database level', function (): void {
    $remote = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://example.com/users/carol',
        'inbox_url' => 'https://example.com/users/carol/inbox',
        'username' => 'carol',
        'domain' => 'example.com',
    ]);

    $attributes = [
        'type' => InteractionType::Like,
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remote->id,
        'object_type' => 'remote_url',
        'object_id' => 'https://example.com/notes/3',
        'status' => InteractionStatus::Active,
    ];

    Interaction::query()->create(attributes: $attributes);

    expect(fn () => Interaction::query()->create(attributes: $attributes))
        ->toThrow(QueryException::class);
});
