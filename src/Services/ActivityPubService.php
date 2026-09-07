<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\Log;

final class ActivityPubService
{
    private array $localActorCache = [];

    public function __construct(
        private HttpSignatureService $httpSignatureService,
        private WebFingerService $webFingerService,
        private RemoteActorResolver $remoteActorResolver,
        private DeliveryClient $deliveryClient,
        private ActivityBuilderContract $activityBuilder,
    ) {}

    public function sendCreate(FederatableContentContract $content): void
    {
        $actor = $content->activityPubActor();
        $object = $this->buildObject(content: $content);
        $activity = $this->buildActivity(type: 'Create', actor: $actor, object: $object);

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor !== null) {
            $record = $this->recordActivity(
                localActor: $localActor,
                type: ActivityType::Create,
                remoteActor: null,
                payload: $activity,
            );

            $this->deliverToFollowers(actor: $actor, activity: $activity, activityId: $record->id);
        }
    }

    public function sendUpdate(FederatableContentContract $content): void
    {
        $actor = $content->activityPubActor();
        $object = $this->buildObject(content: $content);
        $activity = $this->buildActivity(type: 'Update', actor: $actor, object: $object);

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor !== null) {
            $record = $this->recordActivity(
                localActor: $localActor,
                type: ActivityType::Update,
                remoteActor: null,
                payload: $activity,
            );

            $this->deliverToFollowers(actor: $actor, activity: $activity, activityId: $record->id);
        }
    }

    public function sendDelete(string $objectId, ActorContract $actor): void
    {
        $activity = $this->activityBuilder->delete(actor: $actor, objectId: $objectId);

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor !== null) {
            $record = $this->recordActivity(
                localActor: $localActor,
                type: ActivityType::Delete,
                remoteActor: null,
                payload: $activity,
            );

            $this->deliverToFollowers(actor: $actor, activity: $activity, activityId: $record->id);
        }
    }

    public function sendCreateForActor(FederatableContentContract $content, Actor $actor): Activity
    {
        $object = $this->buildObject(content: $content);
        $activity = $this->buildActivity(type: 'Create', actor: $actor, object: $object);

        $record = $this->recordActivity(
            localActor: $actor,
            type: ActivityType::Create,
            remoteActor: null,
            payload: $activity,
        );

        $this->deliverToFollowers(actor: $actor, activity: $activity, activityId: $record->id);

        return $record;
    }

    /**
     * @return array{record: Activity, results: list<array{actor_url: string, inbox_url: string, response_code: int|null}>}
     */
    public function sendCreateForActorSync(FederatableContentContract $content, Actor $actor): array
    {
        $object = $this->buildObject(content: $content);
        $activity = $this->buildActivity(type: 'Create', actor: $actor, object: $object);

        $record = $this->recordActivity(
            localActor: $actor,
            type: ActivityType::Create,
            remoteActor: null,
            payload: $activity,
        );

        $results = $this->deliverSync(actor: $actor, activity: $activity, activityId: $record->id);

        return [
            'record' => $record,
            'results' => $results,
        ];
    }

    /**
     * @return list<array{actor_url: string, inbox_url: string, response_code: int|null}>
     */
    protected function deliverSync(ActorContract $actor, array $activity, ?int $activityId = null): array
    {
        $results = [];

        if (! config('activitypub.federation.enabled')) {
            return $results;
        }

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return $results;
        }

        $syncBatch = function ($followers) use ($activity, $localActor, $activityId, &$results) {
            $sent = [];
            foreach ($followers as $follower) {
                $inboxUrl = $follower->remoteActor->shared_inbox_url ?? $follower->remoteActor->inbox_url;
                if (isset($sent[$inboxUrl])) {
                    $results[] = [
                        'actor_url' => $follower->remoteActor->actor_url,
                        'inbox_url' => $inboxUrl,
                        'response_code' => $sent[$inboxUrl],
                    ];
                    continue;
                }
                $responseCode = $this->deliverSingleSync(
                    inboxUrl: $inboxUrl,
                    activity: $activity,
                    actor: $localActor,
                    activityId: $activityId,
                );
                $sent[$inboxUrl] = $responseCode;
                $results[] = [
                    'actor_url' => $follower->remoteActor->actor_url,
                    'inbox_url' => $inboxUrl,
                    'response_code' => $responseCode,
                ];
            }
        };

        Follower::query()
            ->with(relations: 'remoteActor')
            ->where(column: 'actor_id', operator: '=', value: $localActor->id)
            ->where(column: 'status', operator: '=', value: FollowerStatus::Accepted)
            ->chunk(200, $syncBatch);

        return $results;
    }

    protected function deliverSingleSync(string $inboxUrl, array $activity, Actor $actor, ?int $activityId = null): ?int
    {
        $result = $this->deliveryClient->deliver(
            inboxUrl: $inboxUrl,
            activity: $activity,
            actor: $actor,
        );

        if ($result === null) {
            Log::debug('deliverSingleSync: failed to encode activity JSON', [
                'inboxUrl' => $inboxUrl,
            ]);

            return null;
        }

        $responseCode = $result['status'];

        if ($responseCode >= 200 && $responseCode < 300 && $activityId !== null) {
            Activity::query()
                ->where(column: 'id', operator: '=', value: $activityId)
                ->update(values: [
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
        }

        return $responseCode;
    }

    public function handleInbox(array $payload): void
    {
        app(InboxProcessor::class)->process(payload: $payload);
    }

    public function resolveRemoteActor(string $actorUri): ?RemoteActor
    {
        $existing = RemoteActor::query()
            ->where(column: 'actor_url', operator: '=', value: $actorUri)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $url = $this->webFingerService->resolve(resource: $actorUri);

        if ($url === null) {
            return null;
        }

        $data = $this->remoteActorResolver->fetchActorData(actorUri: $actorUri);

        if ($data === null) {
            return null;
        }

        FetchRemoteActor::dispatch(
            actorUri: $actorUri,
            data: $data,
        );

        return $this->remoteActorResolver->upsertFromData(actorUri: $actorUri, data: $data);
    }

    public function resolveWebFinger(string $resource): ?array
    {
        return $this->webFingerService->resolve(resource: $resource);
    }

    protected function buildObject(FederatableContentContract $content): array
    {
        $object = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $content->getActivityPubId(),
            'type' => $content->getActivityPubType(),
            'attributedTo' => $content->getActivityPubAttributedTo(),
            'name' => $content->getActivityPubName(),
            'content' => $content->getActivityPubContent(),
            'summary' => $content->getActivityPubSummary(),
            'url' => $content->getActivityPubUrl(),
            'published' => $content->getActivityPubPublishedAt(),
            'to' => [$content->getActivityPubTo()],
            'cc' => [$content->getActivityPubCc()],
        ];

        $object = array_filter($object, fn ($value) => $value !== null);

        $attachments = $content->getActivityPubAttachments();

        if ($attachments !== []) {
            $object['attachment'] = $attachments;
        }

        $tags = $content->getActivityPubTags();

        if ($tags !== []) {
            $object['tag'] = $tags;
        }

        return $object;
    }

    protected function buildActivity(string $type, ActorContract $actor, array $object): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actor->getActorId().'#'.$type.'/'.time(),
            'type' => $type,
            'actor' => $actor->getActorId(),
            'object' => $object,
            'to' => [$object['to'] ?? 'https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$object['cc'] ?? $actor->getFollowersUrl()],
        ];
    }

    protected function deliverToFollowers(ActorContract $actor, array $activity, ?int $activityId = null): void
    {
        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $dispatchBatch = function ($followers) use ($activity, $localActor, $activityId) {
            $groups = [];
            foreach ($followers as $follower) {
                $inboxUrl = $follower->remoteActor->shared_inbox_url ?? $follower->remoteActor->inbox_url;
                $groups[$inboxUrl] = true;
            }
            foreach (array_keys($groups) as $inboxUrl) {
                DeliverActivity::dispatch(
                    inboxUrl: $inboxUrl,
                    activityModelId: $activityId,
                    actorId: $localActor->id,
                );
            }
        };

        Follower::query()
            ->with(relations: 'remoteActor')
            ->where(column: 'actor_id', operator: '=', value: $localActor->id)
            ->where(column: 'status', operator: '=', value: FollowerStatus::Accepted)
            ->chunk(200, $dispatchBatch);
    }

    public function recordActivity(
        Actor $localActor,
        ActivityType $type,
        ?RemoteActor $remoteActor,
        array $payload,
        bool $isIncoming = false,
    ): Activity {
        $object = $payload['object'] ?? [];
        $objectId = is_string($object) ? $object : ($object['id'] ?? null);
        $objectType = is_array($object) ? ($object['type'] ?? null) : null;

        return Activity::query()->create(attributes: [
            'actor_id' => $localActor->id,
            'type' => $type,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'payload' => $payload,
            'status' => $isIncoming ? 'received' : 'pending',
            'delivered_at' => $isIncoming ? now() : null,
            'remote_actor_id' => $remoteActor?->id,
            'is_incoming' => $isIncoming,
        ]);
    }

    public function sendFollow(ActorContract $actor, RemoteActor $target): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->follow(actor: $actor, objectUrl: $target->actor_url);

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Follow,
            remoteActor: $target,
            payload: $activity,
        );

        $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
        DeliverActivity::dispatch(
            inboxUrl: $inboxUrl,
            activityModelId: $record->id,
            actorId: $localActor->id,
        );
    }

    public function sendUnfollow(ActorContract $actor, Follower $follower): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);
        $target = $follower->remoteActor;

        if ($localActor === null || $target === null) {
            return;
        }

        $activity = $this->activityBuilder->undoFollow(actor: $actor, objectUrl: $target->actor_url);

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Undo,
            remoteActor: $target,
            payload: $activity,
        );

        $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
        DeliverActivity::dispatch(
            inboxUrl: $inboxUrl,
            activityModelId: $record->id,
            actorId: $localActor->id,
        );

        $follower->delete();
    }

    public function sendLike(ActorContract $actor, string $objectUrl): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->like(actor: $actor, objectUrl: $objectUrl);

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Like,
            remoteActor: null,
            payload: $activity,
        );

        $this->deliverToObjectAuthor(
            objectUrl: $objectUrl,
            activity: $activity,
            localActor: $localActor,
            activityId: $record->id,
        );
    }

    public function sendAnnounce(ActorContract $actor, string $objectUrl): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->announce(actor: $actor, objectUrl: $objectUrl);

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Announce,
            remoteActor: null,
            payload: $activity,
        );

        $this->deliverToFollowers(actor: $actor, activity: $activity, activityId: $record->id);
    }

    public function sendNote(ActorContract $actor, string $content, string $inReplyToUrl): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->createNote(
            actor: $actor,
            content: $content,
            inReplyToUrl: $inReplyToUrl,
            to: ['https://www.w3.org/ns/activitystreams#Public'],
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Create,
            remoteActor: null,
            payload: $activity,
        );

        $this->deliverToObjectAuthor(
            objectUrl: $inReplyToUrl,
            activity: $activity,
            localActor: $localActor,
            activityId: $record->id,
        );
    }

    protected function deliverToObjectAuthor(string $objectUrl, array $activity, Actor $localActor, ?int $activityId = null): void
    {
        $objectParts = parse_url(url: $objectUrl);
        $objectDomain = $objectParts['host'] ?? null;

        if ($objectDomain === null) {
            return;
        }

        $remoteActor = RemoteActor::query()
            ->where(column: 'domain', operator: '=', value: $objectDomain)
            ->where(column: 'actor_url', operator: 'LIKE', value: $objectDomain.'%')
            ->first();

        if ($remoteActor === null) {
            return;
        }

        $inboxUrl = $remoteActor->shared_inbox_url ?? $remoteActor->inbox_url;
        DeliverActivity::dispatch(
            inboxUrl: $inboxUrl,
            activityModelId: $activityId,
            actorId: $localActor->id,
        );
    }

    protected function resolveLocalActor(ActorContract $actor): ?Actor
    {
        $username = $actor->getPreferredUsername();

        if (isset($this->localActorCache[$username])) {
            return $this->localActorCache[$username];
        }

        $found = Actor::query()
            ->where(column: 'username', operator: '=', value: $username)
            ->first();

        if ($found !== null) {
            $this->localActorCache[$username] = $found;
        }

        return $found;
    }
}
