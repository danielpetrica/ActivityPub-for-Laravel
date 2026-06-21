<?php

namespace DanielPetrica\LaravelActivityPub\Traits;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\KeyPairService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesLocalActor
{
    protected function resolveLocalActor(): Actor
    {
        $user = auth()->user();

        if (! ($user instanceof ActorContract)) {
            throw new ModelNotFoundException(
                message: 'The authenticated user must implement '.ActorContract::class,
            );
        }

        return Actor::query()
            ->where(column: 'username', operator: '=', value: $user->getPreferredUsername())
            ->firstOr(function () use ($user): Actor {
                $keyPair = app(KeyPairService::class)->generate();

                return Actor::query()->create(attributes: [
                    'username' => $user->getPreferredUsername(),
                    'name' => $user->getDisplayName(),
                    'public_key_pem' => $keyPair['public'],
                    'private_key_pem' => $keyPair['private'],
                    'manually_approves_followers' => false,
                ]);
            });
    }
}
