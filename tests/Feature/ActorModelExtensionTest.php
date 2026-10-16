<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActorProfileContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatedActorContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;

/**
 * Custom actor model used to prove the actor_model config seam is real.
 *
 * It reuses the bundled `actors` table but is a distinct class, so any code
 * path that still hardcodes the Actor class will not return it.
 */
final class SwappableActor extends Actor
{
    protected $table = 'actors';
}

it('binds the actor contracts from the configured actor model', function (): void {
    config()->set('activitypub.actor_model', SwappableActor::class);

    expect(app(ActorContract::class))->toBeInstanceOf(SwappableActor::class)
        ->and(app(ActorProfileContract::class))->toBeInstanceOf(SwappableActor::class)
        ->and(app(FederatedActorContract::class))->toBeInstanceOf(SwappableActor::class);
});

it('resolves the {actor:username} route binding through the configured model', function (): void {
    config()->set('activitypub.actor_model', SwappableActor::class);

    $keys = generateTestKeyPair();

    SwappableActor::query()->create(attributes: [
        'username' => 'swapped',
        'name' => 'Swapped Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $response = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => 'swapped']),
        headers: ['Accept' => 'application/activity+json'],
    );

    $response->assertStatus(status: 200);
    $response->assertJson([
        'preferredUsername' => 'swapped',
        'name' => 'Swapped Actor',
    ]);
});

it('falls back to the bundled Actor model for an invalid actor_model config', function (): void {
    config()->set('activitypub.actor_model', stdClass::class);

    expect(app(ActorContract::class))->toBeInstanceOf(Actor::class);
});
