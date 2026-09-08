<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Events\FollowReceived;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\ForwardPostsToNewServerJob;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleFollowAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private ActivityBuilderContract $activityBuilder,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Follow';
    }

    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        if ($remoteActor === null) {
            return;
        }

        Follower::query()->updateOrCreate(
            attributes: [
                'actor_id' => $actor->id,
                'remote_actor_id' => $remoteActor->id,
            ],
            values: [
                'status' => FollowerStatus::Accepted,
            ],
        );

        event(new FollowReceived(
            localActorId: $actor->id,
            remoteActorUrl: $remoteActor->actor_url,
        ));

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Follow,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );

        $acceptActivity = $this->activityBuilder->accept(
            actor: $actor,
            originalPayload: $payload,
        );

        $acceptRecord = $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Accept,
            remoteActor: $remoteActor,
            payload: $acceptActivity,
        );

        DeliverActivity::dispatch(
            inboxUrl: $remoteActor->inbox_url,
            activityModelId: $acceptRecord->id,
            actorId: $actor->id,
        );

        // Forward posts to new server on first follower from domain
        $existingFollowersFromDomain = Follower::query()
            ->where('actor_id', $actor->id)
            ->whereHas('remoteActor', function ($query) use ($remoteActor) {
                $query->where('domain', $remoteActor->domain);
            })
            ->count();

        if ($existingFollowersFromDomain <= 1) {
            ForwardPostsToNewServerJob::dispatch(
                remoteActorId: $remoteActor->id,
                actorId: $actor->id,
            );
        }
    }
}
