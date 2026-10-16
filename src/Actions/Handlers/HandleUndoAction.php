<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Events\FollowRemoved;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
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

        $object = $payload['object'] ?? null;

        $innerType = null;
        $innerObjectUrl = null;
        $matchedActivity = null;

        if (is_array($object)) {
            $innerType = is_string($object['type'] ?? null) ? $object['type'] : null;
            $innerObjectUrl = is_string($object['object'] ?? null) ? $object['object'] : null;
        } elseif (is_string($object)) {
            // Some servers undo by activity id only. Match the stored incoming
            // activity so we can recover its type, actor and target object.
            $matchedActivity = $this->findIncomingActivity(activityUri: $object);

            if ($matchedActivity !== null) {
                $innerType = $matchedActivity->type->value;
                $innerObjectUrl = is_string($matchedActivity->object_id) ? $matchedActivity->object_id : null;

                if ($remoteActor === null && $matchedActivity->remote_actor_id !== null) {
                    $remoteActor = RemoteActor::query()->find($matchedActivity->remote_actor_id);
                }
            }
        }

        // Undo(Block): lift the stored remote block.
        if ($innerType === 'Block') {
            $this->removeBlock(remoteActor: $remoteActor, matchedActivity: $matchedActivity);
        }

        if ($remoteActor !== null && $innerType === 'Follow') {
            Follower::query()
                ->where('actor_id', '=', $actor->id)
                ->where('remote_actor_id', '=', $remoteActor->id)
                ->delete();

            event(new FollowRemoved(
                localActorId: $actor->id,
                remoteActorUrl: $remoteActor->actor_url,
            ));
        }

        // Undo(Like) and Undo(Announce): deactivate the stored interaction.
        if ($remoteActor !== null && is_string($innerObjectUrl) && $innerObjectUrl !== '') {
            $interactionType = match ($innerType) {
                'Like' => InteractionType::Like,
                'Announce' => InteractionType::Announce,
                default => null,
            };

            if ($interactionType !== null) {
                Interaction::query()
                    ->where('remote_actor_id', '=', $remoteActor->id)
                    ->where('object_type', '=', 'remote_url')
                    ->where('object_id', '=', $innerObjectUrl)
                    ->where('type', '=', $interactionType)
                    ->where('status', '=', InteractionStatus::Active)
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

    protected function findIncomingActivity(string $activityUri): ?Activity
    {
        return Activity::query()
            ->where('activity_uri', '=', $activityUri)
            ->where('is_incoming', '=', true)
            ->latest()
            ->first();
    }

    protected function removeBlock(?RemoteActor $remoteActor, ?Activity $matchedActivity): void
    {
        $remoteActorId = $remoteActor !== null ? $remoteActor->id : null;

        if ($remoteActorId === null) {
            $remoteActorId = $matchedActivity?->remote_actor_id;
        }

        if ($remoteActorId === null) {
            return;
        }

        BlockedRemoteActor::query()
            ->where('remote_actor_id', '=', $remoteActorId)
            ->delete();
    }
}
