<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Http\Resources\ActorResource;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Jobs\ForwardPostsToNewServerJob;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ActivityPubService
{
    private array $localActorCache = [];

    public function __construct(
        private WebFingerService $webFingerService,
        private RemoteActorResolver $remoteActorResolver,
        private ActivityBuilderContract $activityBuilder,
        private ActivityDispatcher $dispatcher,
        private ActivityRecorder $recorder,
    ) {}

    public function sendCreate(FederatableContentContract $content): void
    {
        $actor = $content->activityPubActor();
        $object = $this->activityBuilder->buildObject(
            content: $content,
            mentions: $this->resolveMentions(content: $content->getActivityPubContent()),
        );
        $activity = $this->activityBuilder->buildActivity(type: 'Create', actor: $actor, object: $object);

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor !== null) {
            $record = $this->recordActivity(
                localActor: $localActor,
                type: ActivityType::Create,
                remoteActor: null,
                payload: $activity,
            );

            $this->dispatcher->dispatchToFollowers(localActor: $localActor, activityId: $record->id);
            $this->deliverToMentions(actor: $actor, activity: $activity, activityId: $record->id, content: $content->getActivityPubContent());
        }
    }

    public function sendUpdate(FederatableContentContract $content): void
    {
        $actor = $content->activityPubActor();
        $object = $this->activityBuilder->buildObject(
            content: $content,
            mentions: $this->resolveMentions(content: $content->getActivityPubContent()),
        );
        $activity = $this->activityBuilder->buildActivity(type: 'Update', actor: $actor, object: $object);

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor !== null) {
            $record = $this->recordActivity(
                localActor: $localActor,
                type: ActivityType::Update,
                remoteActor: null,
                payload: $activity,
            );

            $this->dispatcher->dispatchToFollowers(localActor: $localActor, activityId: $record->id);
            $this->deliverToMentions(actor: $actor, activity: $activity, activityId: $record->id, content: $content->getActivityPubContent());
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

            $this->dispatcher->dispatchToFollowers(localActor: $localActor, activityId: $record->id);
        }
    }

    public function sendCreateForActor(FederatableContentContract $content, Actor $actor, ?RemoteActor $target = null, ?string $to = null): Activity
    {
        $object = $this->activityBuilder->buildObject(
            content: $content,
            mentions: $this->resolveMentions(content: $content->getActivityPubContent()),
        );

        if ($to !== null) {
            $object['to'] = [$to];
        }

        $activity = $this->activityBuilder->buildActivity(type: 'Create', actor: $actor, object: $object);

        $record = $this->recordActivity(
            localActor: $actor,
            type: ActivityType::Create,
            remoteActor: $target,
            payload: $activity,
        );

        if ($target !== null) {
            $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
            if (config('activitypub.federation.enabled')) {
                $this->dispatcher->dispatchToInbox(
                    inboxUrl: $inboxUrl,
                    activityId: $record->id,
                    actorId: $actor->id,
                );
            }
        } else {
            $this->dispatcher->dispatchToFollowers(localActor: $actor, activityId: $record->id);
        }

        $this->deliverToMentions(actor: $actor, activity: $activity, activityId: $record->id, content: $content->getActivityPubContent());

        return $record;
    }

    /**
     * @return array{record: Activity, results: list<array{actor_url: string, inbox_url: string, response_code: int|null}>}
     */
    public function sendCreateForActorSync(FederatableContentContract $content, Actor $actor): array
    {
        $object = $this->activityBuilder->buildObject(
            content: $content,
            mentions: $this->resolveMentions(content: $content->getActivityPubContent()),
        );
        $activity = $this->activityBuilder->buildActivity(type: 'Create', actor: $actor, object: $object);

        $record = $this->recordActivity(
            localActor: $actor,
            type: ActivityType::Create,
            remoteActor: null,
            payload: $activity,
        );

        $results = $this->deliverSync(actor: $actor, activity: $activity, activityId: $record->id);

        $this->deliverToMentions(actor: $actor, activity: $activity, activityId: $record->id, content: $content->getActivityPubContent());

        return [
            'record' => $record,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $activity
     * @return list<array{actor_url: string, inbox_url: string, response_code: int|null}>
     */
    protected function deliverSync(ActorContract $actor, array $activity, ?int $activityId = null): array
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return [];
        }

        return $this->dispatcher->dispatchToFollowersSync(
            localActor: $localActor,
            activity: $activity,
            activityId: $activityId,
        );
    }

    public function handleInbox(array $payload): void
    {
        app(InboxProcessor::class)->process(payload: $payload);
    }

    public function resolveRemoteActor(string $actorUri): ?RemoteActor
    {
        $existing = RemoteActor::query()
            ->where('actor_url', '=', $actorUri)
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

    public function recordActivity(
        Actor $localActor,
        ActivityType $type,
        ?RemoteActor $remoteActor,
        array $payload,
        bool $isIncoming = false,
    ): Activity {
        return $this->recorder->record(
            localActor: $localActor,
            type: $type,
            remoteActor: $remoteActor,
            payload: $payload,
            isIncoming: $isIncoming,
        );
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
        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $record->id,
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

        // Wrap the real Follow activity id when we have it recorded, so the
        // remote server can match the Undo to the original Follow.
        $followId = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('type', '=', ActivityType::Follow)
            ->where('remote_actor_id', '=', $target->id)
            ->where('is_incoming', '=', false)
            ->latest()
            ->value('payload');

        $followId = is_array($followId) ? ($followId['id'] ?? null) : null;

        $activity = $this->activityBuilder->undoFollow(
            actor: $actor,
            objectUrl: $target->actor_url,
            followId: is_string($followId) ? $followId : null,
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Undo,
            remoteActor: $target,
            payload: $activity,
        );

        $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $record->id,
            actorId: $localActor->id,
        );

        $follower->delete();
    }

    /**
     * Send a Block activity to the target actor's inbox.
     */
    public function sendBlock(ActorContract $actor, string $objectUrl): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->block(actor: $actor, objectUrl: $objectUrl);

        $target = $this->findRemoteActorByUrl(actorUrl: $objectUrl)
            ?? $this->resolveRemoteActor(actorUri: $objectUrl);

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Block,
            remoteActor: $target,
            payload: $activity,
        );

        if ($target === null) {
            $this->recordResolutionFailure(
                activityId: $record->id,
                reason: 'Could not resolve the inbox of the block target',
                context: ['target_url' => $objectUrl],
            );

            return;
        }

        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $record->id,
            actorId: $localActor->id,
        );
    }

    /**
     * Send an `Undo(Block)` activity to the target actor's inbox, lifting a
     * block previously delivered with sendBlock().
     */
    public function sendUnblock(ActorContract $actor, RemoteActor $remoteActor): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->undoBlock(
            actor: $actor,
            objectUrl: $remoteActor->actor_url,
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Undo,
            remoteActor: $remoteActor,
            payload: $activity,
        );

        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $inboxUrl = $remoteActor->shared_inbox_url ?? $remoteActor->inbox_url;
        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $record->id,
            actorId: $localActor->id,
        );
    }

    /**
     * Forward the newest posts to a newly confirmed follower.
     *
     * Only the first accepted follower from a given remote domain triggers the
     * job (the job itself is also unique per remote actor). Callers must only
     * invoke this for an accepted follower: a pending request on a locked
     * account must not leak posts before it is approved.
     */
    public function forwardRecentPostsToNewFollower(Actor $actor, RemoteActor $remoteActor): void
    {
        $existingFollowersFromDomain = Follower::query()
            ->where('actor_id', '=', $actor->id)
            ->where('status', '=', FollowerStatus::Accepted)
            ->whereHas('remoteActor', function ($query) use ($remoteActor): void {
                $query->where('domain', '=', $remoteActor->domain);
            })
            ->count();

        if ($existingFollowersFromDomain <= 1) {
            ForwardPostsToNewServerJob::dispatch(
                remoteActorId: $remoteActor->id,
                actorId: $actor->id,
            );
        }
    }

    /**
     * Approve a pending follower on a locked (`manually_approves_followers`)
     * account.
     *
     * Sends an `Accept` wrapping the original incoming `Follow`, advances the
     * follower to Accepted and returns true. Returns false when the local actor
     * or the remote follower cannot be resolved.
     */
    public function approveFollower(ActorContract $actor, Follower $follower): bool
    {
        $localActor = $this->resolveLocalActor(actor: $actor);
        $target = RemoteActor::query()->find($follower->remote_actor_id);

        if ($localActor === null || $target === null) {
            return false;
        }

        $activity = $this->activityBuilder->accept(
            actor: $actor,
            originalPayload: $this->originalFollowPayload(localActor: $localActor, target: $target),
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Accept,
            remoteActor: $target,
            payload: $activity,
        );

        $this->deliverToRemoteActor(record: $record, target: $target, localActor: $localActor);

        $follower->update(['status' => FollowerStatus::Accepted]);

        // A follower that was pending on a locked account only becomes a real
        // follower once approved; forward the recent posts at that point.
        $this->forwardRecentPostsToNewFollower(actor: $localActor, remoteActor: $target);

        return true;
    }

    /**
     * Reject a pending follower on a locked account.
     *
     * Sends a `Reject` wrapping the original incoming `Follow`, removes the
     * follower row and returns true. Returns false when the local actor or the
     * remote follower cannot be resolved.
     */
    public function rejectFollower(ActorContract $actor, Follower $follower): bool
    {
        $localActor = $this->resolveLocalActor(actor: $actor);
        $target = RemoteActor::query()->find($follower->remote_actor_id);

        if ($localActor === null || $target === null) {
            return false;
        }

        $activity = $this->activityBuilder->reject(
            actor: $actor,
            originalPayload: $this->originalFollowPayload(localActor: $localActor, target: $target),
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Reject,
            remoteActor: $target,
            payload: $activity,
        );

        $this->deliverToRemoteActor(record: $record, target: $target, localActor: $localActor);

        $follower->delete();

        return true;
    }

    /**
     * Emit an `Update(Person)` for a changed local actor profile and deliver it
     * to every accepted follower.
     *
     * This is the documented trigger for profile-change federation: call it
     * after persisting profile fields (the bundled Fediverse profile screen
     * does). It is deliberately a method rather than a blanket model observer
     * so that unrelated actor writes (`last_active_at` on every outbound
     * activity) never fan out an Update.
     */
    public function updateActorProfile(Actor $actor): void
    {
        $object = (new ActorResource(resource: $actor))->toArray(request: Request::create('/'));

        $activity = $this->activityBuilder->updateActor(actor: $actor, object: $object);

        $record = $this->recordActivity(
            localActor: $actor,
            type: ActivityType::Update,
            remoteActor: null,
            payload: $activity,
        );

        $this->dispatcher->dispatchToFollowers(localActor: $actor, activityId: $record->id);
    }

    /**
     * Send a `Move` activity migrating this actor to a new IRI.
     *
     * The activity is delivered to every accepted follower and, when the target
     * is already known locally, to the target's inbox as well.
     *
     * @param  array<int, string>  $alsoKnownAs
     */
    public function sendMove(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): void
    {
        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        $activity = $this->activityBuilder->move(
            actor: $actor,
            targetUrl: $targetUrl,
            alsoKnownAs: $alsoKnownAs,
        );

        $record = $this->recordActivity(
            localActor: $localActor,
            type: ActivityType::Move,
            remoteActor: null,
            payload: $activity,
        );

        $this->dispatcher->dispatchToFollowers(localActor: $localActor, activityId: $record->id);

        // Deliver directly to the destination when we already know its inbox.
        // Never triggers an outbound fetch here; the Follow/WebFinger path owns
        // remote resolution.
        $target = $this->findRemoteActorByUrl(actorUrl: $targetUrl);

        if ($target !== null) {
            $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;
            $this->dispatcher->dispatchToInbox(
                inboxUrl: $inboxUrl,
                activityId: $record->id,
                actorId: $localActor->id,
            );
        }
    }

    /**
     * Recover the most recent incoming Follow payload from this actor so an
     * Accept/Reject wraps the real activity. Falls back to a minimal,
     * structurally valid Follow when the original has been pruned.
     *
     * @return array<string, mixed>
     */
    protected function originalFollowPayload(Actor $localActor, RemoteActor $target): array
    {
        $payload = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('type', '=', ActivityType::Follow)
            ->where('remote_actor_id', '=', $target->id)
            ->where('is_incoming', '=', true)
            ->latest()
            ->value('payload');

        if (is_array($payload)) {
            return $payload;
        }

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $target->actor_url,
            'object' => $localActor->actor_id,
        ];
    }

    /**
     * Deliver a recorded Accept/Reject to the target's preferred inbox.
     */
    protected function deliverToRemoteActor(Activity $record, RemoteActor $target, Actor $localActor): void
    {
        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $inboxUrl = $target->shared_inbox_url ?? $target->inbox_url;

        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $record->id,
            actorId: $localActor->id,
        );
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

        $this->dispatcher->dispatchToFollowers(localActor: $localActor, activityId: $record->id);
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

        $mentions = $this->resolveMentions(content: $content);
        $activity = $this->activityBuilder->attachMentions(activity: $activity, mentions: $mentions);

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

        $this->deliverToMentions(
            actor: $actor,
            activity: $activity,
            activityId: $record->id,
            content: $content,
        );
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    protected function deliverToObjectAuthor(string $objectUrl, array $activity, Actor $localActor, ?int $activityId = null): void
    {
        $authorUrl = $this->resolveObjectAuthorUrl(objectUrl: $objectUrl);

        if ($authorUrl === null) {
            $this->recordResolutionFailure(
                activityId: $activityId,
                reason: 'Could not determine the author of the target object',
                context: ['object_url' => $objectUrl],
            );

            return;
        }

        $remoteActor = $this->findRemoteActorByUrl(actorUrl: $authorUrl)
            ?? $this->resolveRemoteActor(actorUri: $authorUrl);

        if ($remoteActor === null) {
            $this->recordResolutionFailure(
                activityId: $activityId,
                reason: 'Could not resolve the inbox of the target object author',
                context: ['object_url' => $objectUrl, 'author_url' => $authorUrl],
            );

            return;
        }

        $inboxUrl = $remoteActor->shared_inbox_url ?? $remoteActor->inbox_url;

        $this->dispatcher->dispatchToInbox(
            inboxUrl: $inboxUrl,
            activityId: $activityId,
            actorId: $localActor->id,
        );
    }

    /**
     * Resolve the real author of an object: prefer cached/known data, then
     * fall back to fetching the object and reading its `attributedTo`.
     */
    protected function resolveObjectAuthorUrl(string $objectUrl): ?string
    {
        $cacheKey = 'activitypub:object-author:'.md5($objectUrl);

        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $authorUrl = null;

        // Prefer a known object: incoming/outgoing activities store the object
        // payload, so read `attributedTo` from any recorded payload we hold.
        $knownActivities = Activity::query()
            ->where('object_id', '=', $objectUrl)
            ->whereNotNull('payload')
            ->latest()
            ->limit(20)
            ->get();

        foreach ($knownActivities as $knownActivity) {
            $authorUrl = $this->extractAttributedTo(payload: $knownActivity->payload);

            if ($authorUrl !== null) {
                break;
            }
        }

        if ($authorUrl === null) {
            $data = $this->remoteActorResolver->fetchActorData(actorUri: $objectUrl);

            if (is_array($data)) {
                $authorUrl = $this->extractAttributedTo(payload: $data);
            }
        }

        if ($authorUrl !== null) {
            Cache::put($cacheKey, $authorUrl, now()->addHours(6));
        }

        return $authorUrl;
    }

    /**
     * Extract a single actor URL from an object's `attributedTo`, which may be
     * a string, an array of strings, or an array of embedded objects.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractAttributedTo(array $payload): ?string
    {
        $object = $payload['object'] ?? null;

        $attributedTo = is_array($object)
            ? ($object['attributedTo'] ?? null)
            : null;

        $attributedTo ??= $payload['attributedTo'] ?? null;

        if (is_string($attributedTo) && $attributedTo !== '') {
            return $attributedTo;
        }

        if (! is_array($attributedTo)) {
            return null;
        }

        foreach ($attributedTo as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }

            if (is_array($candidate) && isset($candidate['id']) && is_string($candidate['id']) && $candidate['id'] !== '') {
                return $candidate['id'];
            }
        }

        return null;
    }

    /**
     * Find a cached remote actor by URL using exact then case-insensitive
     * equality (never an unescaped LIKE pattern).
     */
    protected function findRemoteActorByUrl(string $actorUrl): ?RemoteActor
    {
        $exact = RemoteActor::query()
            ->where('actor_url', '=', $actorUrl)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        return RemoteActor::query()
            ->whereRaw('lower(actor_url) = ?', [strtolower($actorUrl)])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function recordResolutionFailure(?int $activityId, string $reason, array $context = []): void
    {
        $this->recorder->recordResolutionFailure(
            activityId: $activityId,
            reason: $reason,
            context: $context,
        );
    }

    /**
     * Resolve `@user@host` mentions in a piece of content.
     *
     * Defensive by design: an unresolvable mention is skipped, never thrown.
     *
     * @return array<int, array{href: string, name: string, inbox: string|null}>
     */
    protected function resolveMentions(string $content): array
    {
        if ($content === '') {
            return [];
        }

        if (! preg_match_all(
            '/(?<![A-Za-z0-9_@])@([A-Za-z0-9_]+)@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/',
            $content,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $mentions = [];
        $seen = [];

        foreach ($matches as $match) {
            $username = $match[1];
            $host = strtolower($match[2]);
            $acct = $username.'@'.$host;

            if (isset($seen[$acct])) {
                continue;
            }

            $seen[$acct] = true;

            try {
                $resolved = $this->resolveMentionTarget(username: $username, host: $host, acct: $acct);
            } catch (\Throwable $e) {
                Log::debug('ActivityPub mention resolution failed', [
                    'acct' => $acct,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($resolved !== null) {
                $mentions[] = $resolved;
            }
        }

        return $mentions;
    }

    /**
     * @return array{href: string, name: string, inbox: string|null}|null
     */
    protected function resolveMentionTarget(string $username, string $host, string $acct): ?array
    {
        $remoteActor = RemoteActor::query()
            ->where('username', '=', $username)
            ->whereRaw('lower(domain) = ?', [strtolower($host)])
            ->first();

        $href = $remoteActor?->actor_url;

        if ($href === null) {
            $link = $this->webFingerService->resolve(resource: 'acct:'.$acct);

            $href = is_array($link) ? ($link['href'] ?? null) : null;
        }

        if (! is_string($href) || $href === '') {
            return null;
        }

        $remoteActor ??= $this->findRemoteActorByUrl(actorUrl: $href)
            ?? $this->resolveRemoteActor(actorUri: $href);

        $sharedInbox = $remoteActor?->getAttribute('shared_inbox_url');

        return [
            'href' => $href,
            'name' => '@'.$acct,
            'inbox' => is_string($sharedInbox) && $sharedInbox !== '' ? $sharedInbox : $remoteActor?->inbox_url,
        ];
    }

    /**
     * Deliver an activity to the personal inbox of every resolvable mention.
     *
     * @param  array<string, mixed>  $activity
     */
    protected function deliverToMentions(ActorContract $actor, array $activity, ?int $activityId, string $content): void
    {
        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $mentions = $this->resolveMentions(content: $content);

        if ($mentions === []) {
            return;
        }

        $localActor = $this->resolveLocalActor(actor: $actor);

        if ($localActor === null) {
            return;
        }

        foreach ($mentions as $mention) {
            if (($mention['inbox'] ?? null) === null) {
                continue;
            }

            $this->dispatcher->dispatchToInbox(
                inboxUrl: $mention['inbox'],
                activityId: $activityId,
                actorId: $localActor->id,
            );
        }
    }

    protected function resolveLocalActor(ActorContract $actor): ?Actor
    {
        $username = $actor->getPreferredUsername();

        if (isset($this->localActorCache[$username])) {
            return $this->localActorCache[$username];
        }

        $model = $this->actorModelClass();

        $found = $model::query()
            ->where('username', '=', $username)
            ->first();

        if ($found !== null) {
            $this->localActorCache[$username] = $found;
        }

        return $found;
    }

    /**
     * Resolve the configured actor model class, falling back to the bundled
     * Actor model when the configured class does not extend it.
     *
     * @return class-string<Actor>
     */
    protected function actorModelClass(): string
    {
        $model = config('activitypub.actor_model', Actor::class);

        if (is_string($model) && is_a($model, Actor::class, true)) {
            return $model;
        }

        return Actor::class;
    }
}
