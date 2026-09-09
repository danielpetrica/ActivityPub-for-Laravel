<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Events\BlockReceived;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleBlockAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Block';
    }

    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        if ($remoteActor === null) {
            return;
        }

        Follower::query()
            ->where(column: 'actor_id', operator: '=', value: $actor->id)
            ->where(column: 'remote_actor_id', operator: '=', value: $remoteActor->id)
            ->delete();

        event(new BlockReceived(
            localActorId: $actor->id,
            remoteActorUrl: $remoteActor->actor_url,
        ));

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Block,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );
    }
}
