<?php

use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'unitactor',
        'name' => 'Unit Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->otherActor = Actor::query()->create(attributes: [
        'username' => 'unitother',
        'name' => 'Unit Other',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function makeInteraction(array $overrides = []): Interaction
{
    return Interaction::query()->create(array_merge([
        'type' => InteractionType::Like,
        'actor_id' => null,
        'object_type' => 'App\\Models\\Post',
        'object_id' => '1',
        'status' => InteractionStatus::Active,
    ], $overrides));
}

it('casts interaction type and status to their enums', function (): void {
    $interaction = makeInteraction();

    expect($interaction->type)->toBe(InteractionType::Like)
        ->and($interaction->status)->toBe(InteractionStatus::Active)
        ->and($interaction->isUndone())->toBeFalse();
});

it('reports isUndone for undone interactions', function (): void {
    $interaction = makeInteraction(['status' => InteractionStatus::Undone]);

    expect($interaction->isUndone())->toBeTrue()
        ->and($interaction->status)->toBe(InteractionStatus::Undone);
});

it('excludes undone interactions with the active scope', function (): void {
    $active = makeInteraction();
    makeInteraction(['status' => InteractionStatus::Undone]);

    $results = Interaction::query()->active()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->id)->toBe($active->id);
});

it('filters by interaction type with the ofType scope', function (): void {
    $like = makeInteraction(['type' => InteractionType::Like]);
    makeInteraction(['type' => InteractionType::Announce]);

    $results = Interaction::query()->ofType(InteractionType::Like)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->id)->toBe($like->id);
});

it('filters by object type and id with the forObject scope', function (): void {
    $matching = makeInteraction(['object_type' => 'App\\Models\\Post', 'object_id' => '7']);
    makeInteraction(['object_type' => 'App\\Models\\Post', 'object_id' => '8']);
    makeInteraction(['object_type' => 'remote_url', 'object_id' => '7']);

    $results = Interaction::query()
        ->forObject(objectType: 'App\\Models\\Post', objectId: '7')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->id)->toBe($matching->id);
});

it('filters by actor id with the forActor scope', function (): void {
    $mine = makeInteraction(['actor_id' => $this->actor->id]);
    makeInteraction(['actor_id' => $this->otherActor->id]);
    makeInteraction(['actor_id' => null]);

    $results = Interaction::query()->forActor(actorId: $this->actor->id)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->id)->toBe($mine->id);
});
