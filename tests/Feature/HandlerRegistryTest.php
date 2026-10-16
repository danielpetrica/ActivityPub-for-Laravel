<?php

use DanielPetrica\LaravelActivityPub\Actions\Handlers\ActivityHandler;
use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Handlers\HandlerRegistry;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/**
 * A handler that records its invocation as an Activity row keyed by a marker,
 * so tests can observe exactly which handler the InboxProcessor dispatched.
 */
final class MarkerHandler implements ActivityHandler
{
    public function __construct(
        private string $type,
        private string $marker,
    ) {}

    public function handles(): string
    {
        return $this->type;
    }

    public function handle(Actor $actor, array $payload): void
    {
        Activity::query()->create(attributes: [
            'actor_id' => $actor->id,
            'type' => ActivityType::Create,
            'object_id' => $this->marker,
            'payload' => $payload,
            'status' => 'received',
            'delivered_at' => now(),
            'is_incoming' => true,
        ]);
    }
}

beforeEach(function (): void {
    Bus::fake();
    Http::fake(['remote.example.com/*' => Http::response(body: '', status: 404)]);

    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'registryuser',
        'name' => 'Registry User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

it('bootstraps the core handlers from the container tag', function (): void {
    $registry = app(HandlerRegistry::class);

    expect($registry->hasType('Follow'))->toBeTrue()
        ->and($registry->hasType('Like'))->toBeTrue()
        ->and($registry->hasType('Create'))->toBeTrue()
        ->and($registry->hasType('Accept'))->toBeTrue();
});

it('lets a consumer register a handler for a new activity type', function (): void {
    $registry = app(HandlerRegistry::class);
    $registry->register(new MarkerHandler('Ping', 'custom-ping'));

    app(InboxProcessor::class)->process(payload: [
        'type' => 'Ping',
        'actor' => 'https://remote.example.com/users/sender',
        'object' => $this->actor->actor_id,
        'to' => [$this->actor->actor_id],
    ]);

    expect(Activity::query()->where(column: 'object_id', operator: '=', value: 'custom-ping')->exists())
        ->toBeTrue();
});

it('lets a consumer replace a core handler', function (): void {
    $registry = app(HandlerRegistry::class);
    $replacement = new MarkerHandler('Create', 'replaced-create');
    $registry->replace($replacement);

    expect($registry->firstForType('Create'))->toBe($replacement);

    app(InboxProcessor::class)->process(payload: [
        'type' => 'Create',
        'actor' => 'https://remote.example.com/users/sender',
        'object' => $this->actor->actor_id,
        'to' => [$this->actor->actor_id],
    ]);

    expect(Activity::query()->where(column: 'object_id', operator: '=', value: 'replaced-create')->exists())
        ->toBeTrue();
});

it('orders handlers for the same type by priority', function (): void {
    $registry = app(HandlerRegistry::class);
    $low = new MarkerHandler('Ping', 'low');
    $high = new MarkerHandler('Ping', 'high');

    $registry->register($low, priority: 0);
    $registry->register($high, priority: 10);

    expect($registry->firstForType('Ping'))->toBe($high)
        ->and($registry->forType('Ping'))->toBe([$high, $low]);
});

it('does not clobber a consumer replacement when bootstrapping the tag', function (): void {
    $registry = app(HandlerRegistry::class);

    $replacement = new MarkerHandler('Like', 'replaced-like');
    $registry->replace($replacement);

    // The singleton bootstrap calls registerIfTypeAbsent() for every
    // container-tagged core handler. Exercise that exact call for the replaced
    // type: the consumer replacement must survive, not be re-added alongside
    // or overwritten by the core handler.
    $registry->registerIfTypeAbsent(new MarkerHandler('Like', 'core-like'));

    expect($registry->firstForType('Like'))->toBe($replacement);
});
