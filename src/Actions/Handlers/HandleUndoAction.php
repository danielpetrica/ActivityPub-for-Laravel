<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Events\FollowRemoved;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleUndoAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Undo';
    }

    /** @param array<string, mixed> $payload */
    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        $object = $payload['object'] ?? [];

        if ($remoteActor !== null && is_array($object) && ($object['type'] ?? null) === 'Follow') {
            Follower::query()
                ->where(column: 'actor_id', operator: '=', value: $actor->id)
                ->where(column: 'remote_actor_id', operator: '=', value: $remoteActor->id)
                ->delete();

            event(new FollowRemoved(
                localActorId: $actor->id,
                remoteActorUrl: $remoteActor->actor_url,
            ));
        }

        // Handle Undo(Like) and Undo(Announce)
        $innerObjectType = $object['type'] ?? null;
        $innerObjectUrl = $object['object'] ?? null;

        if ($remoteActor !== null && is_string($innerObjectUrl)) {
            $interactionType = match ($innerObjectType) {
                'Like' => InteractionType::Like,
                'Announce' => InteractionType::Announce,
                default => null,
            };

            if ($interactionType !== null) {
                Interaction::query()
                    ->where('remote_actor_id', $remoteActor->id)
                    ->where('object_type', 'remote_url')
                    ->where('object_id', $innerObjectUrl)
                    ->where('type', $interactionType)
                    ->where('status', InteractionStatus::Active)
                    ->update(['status' => InteractionStatus::Undone]);
            }
        }

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Undo,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );
    }
}
