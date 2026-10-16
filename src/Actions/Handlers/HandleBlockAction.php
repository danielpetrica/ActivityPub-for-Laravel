<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Events\BlockReceived;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\Following;
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
            ->where('actor_id', '=', $actor->id)
            ->where('remote_actor_id', '=', $remoteActor->id)
            ->delete();

        // We may also have been following the blocker; an inbound Block ends
        // that relationship too, not just their follow of us.
        Following::query()
            ->where('actor_id', '=', $actor->id)
            ->where('remote_actor_id', '=', $remoteActor->id)
            ->delete();

        // Persist the remote block so every subsequent activity from this
        // actor is rejected centrally by InboxProcessor, for all types.
        BlockedRemoteActor::query()->updateOrCreate(
            ['remote_actor_id' => $remoteActor->id],
            ['reason' => 'Remote actor sent a Block activity'],
        );

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
