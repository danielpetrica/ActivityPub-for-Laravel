<?php

use DanielPetrica\LaravelActivityPub\Actions\Handlers\ActivityHandler;
use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Handlers\HandlerRegistry;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\IncomingActivity;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'primary',
        'name' => 'Primary',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->secondActor = Actor::query()->create(attributes: [
        'username' => 'secondary',
        'name' => 'Secondary',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);

    Http::fake();
});

/**
 * @return array<string, mixed>
 */
function inboxLikePayload(Actor $actor, string $activityId, string $objectId): array
{
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $activityId,
        'type' => 'Like',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => $objectId,
        'to' => [$actor->actor_id],
    ];
}

it('processes the same activity id independently for each addressed local actor', function (): void {
    $activityId = 'https://remote.example.com/users/remote#likes/9001';
    $objectId = 'https://example.com/notes/9001';

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: inboxLikePayload($this->actor, $activityId, $objectId),
    )->assertStatus(status: 202);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'secondary']),
        data: inboxLikePayload($this->secondActor, $activityId, $objectId),
    )->assertStatus(status: 202);

    // The global activity_uri alone must not dedupe across actors: each local
    // actor reserves and records the activity for itself.
    expect(IncomingActivity::query()
        ->where('activity_uri', '=', $activityId)
        ->count())->toBe(2);

    expect(Activity::query()
        ->where('is_incoming', '=', true)
        ->where('activity_uri', '=', $activityId)
        ->count())->toBe(2);

    expect(Interaction::query()->where('object_id', '=', $objectId)->count())->toBe(2);
});

it('ignores a byte-identical redelivery for the same actor', function (): void {
    $activityId = 'https://remote.example.com/users/remote#likes/9002';
    $objectId = 'https://example.com/notes/9002';
    $payload = inboxLikePayload($this->actor, $activityId, $objectId);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: $payload,
    )->assertStatus(status: 202);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => 'primary']),
        data: $payload,
    )->assertStatus(status: 202);

    expect(IncomingActivity::query()
        ->where('actor_id', '=', $this->actor->id)
        ->count())->toBe(1);

    expect(Activity::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('is_incoming', '=', true)
        ->where('activity_uri', '=', $activityId)
        ->count())->toBe(1);

    // The handler only ran once, so only one interaction exists.
    expect(Interaction::query()->where('object_id', '=', $objectId)->count())->toBe(1);
});

it('releases the reservation when a handler throws so a redelivery is reprocessed', function (): void {
    $handler = new class implements ActivityHandler
    {
        public int $calls = 0;

        public function handles(): string
        {
            return 'TestThrowingActivity';
        }

        /** @param array<string, mixed> $payload */
        public function handle(Actor $actor, array $payload): void
        {
            $this->calls++;

            if ($this->calls === 1) {
                throw new RuntimeException('Handler failed on the first attempt.');
            }
        }
    };

    app(HandlerRegistry::class)->replace(handler: $handler);

    $activityId = 'https://remote.example.com/users/remote#test/1';
    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => $activityId,
        'type' => 'TestThrowingActivity',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => 'https://example.com/notes/1',
        'to' => [$this->actor->actor_id],
    ];

    $processor = app(InboxProcessor::class);

    expect(fn () => $processor->process(payload: $payload))
        ->toThrow(RuntimeException::class);

    // The failed delivery must not keep the claim, otherwise the retry is
    // silently dropped as a duplicate.
    expect(IncomingActivity::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('activity_uri', '=', $activityId)
        ->exists())->toBeFalse();

    // The redelivery now runs the handler again and keeps the reservation.
    $processor->process(payload: $payload);

    expect($handler->calls)->toBe(2)
        ->and(IncomingActivity::query()
            ->where('actor_id', '=', $this->actor->id)
            ->where('activity_uri', '=', $activityId)
            ->exists())->toBeTrue();
});
