<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Helpers\SsrfGuard;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;

final class HandleCreateAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Create';
    }

    public function handle(Actor $actor, array $payload): void
    {
        $object = $payload['object'] ?? null;
        if (is_string($object) && SsrfGuard::isSafeUrl(url: $object)) {
            $fetched = $this->remoteActorResolver->fetchActorData(actorUri: $object);
            if ($fetched !== null) {
                $payload['object'] = $fetched;
            }
        }

        $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);

        $this->activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Create,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: true,
        );
    }
}
