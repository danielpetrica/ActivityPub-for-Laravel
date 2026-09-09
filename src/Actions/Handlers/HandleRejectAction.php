<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleRejectAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Reject';
    }

    public function handle(Actor $actor, array $payload): void
    {
        $object = $payload['object'] ?? [];

        if (is_array($object) && ($object['type'] ?? null) === 'Follow') {
            $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

            if ($remoteActor !== null) {
                Follower::query()
                    ->where(column: 'actor_id', operator: '=', value: $actor->id)
                    ->where(column: 'remote_actor_id', operator: '=', value: $remoteActor->id)
                    ->delete();

                Following::query()
                    ->where('actor_id', '=', $actor->id)
                    ->where('remote_actor_id', '=', $remoteActor->id)
                    ->delete();
            }
        }

        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Reject,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );
    }
}
