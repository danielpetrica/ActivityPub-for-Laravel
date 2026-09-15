<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleLikeAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Like';
    }

    /** @param array<string, mixed> $payload */
    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Like,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );

        $objectUrl = $payload['object'] ?? null;
        if (is_string($objectUrl)) {
            Interaction::create([
                'type' => InteractionType::Like,
                'actor_id' => $actor->id,
                'remote_actor_id' => $remoteActor->id,
                'object_type' => 'remote_url',
                'object_id' => $objectUrl,
                'status' => InteractionStatus::Active,
            ]);
        }
    }
}
