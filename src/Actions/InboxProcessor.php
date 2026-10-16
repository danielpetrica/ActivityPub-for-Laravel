<?php

namespace DanielPetrica\LaravelActivityPub\Actions;

use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Events\InboxActivityReceived;
use DanielPetrica\LaravelActivityPub\Handlers\HandlerRegistry;
use DanielPetrica\LaravelActivityPub\Helpers\DomainNormalizer;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\ProcessInboxActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedDomain;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\IncomingActivity;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class InboxProcessor
{
    /** @var array<string, Actor> */
    private array $localActorCache = [];

    public function __construct(
        private HandlerRegistry $handlerRegistry,
    ) {}

    /**
     * Queue inbox processing so handler and remote resolution work runs off
     * the HTTP request. `process()` stays synchronous for tests and for
     * callers that need the outcome inline.
     *
     * @param  array<string, mixed>  $payload
     */
    public function queue(array $payload): void
    {
        ProcessInboxActivity::dispatch(payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : null;
        $actorUrl = $payload['actor'] ?? null;

        $actor = $this->resolveLocalActor(payload: $payload);

        if ($actor === null) {
            Log::debug(
                'InboxProcessor: Could not resolve local actor from payload',
                ['payload' => $payload],
            );

            return;
        }

        // Central, normalized block enforcement. This runs before any remote
        // resolution/fetch and applies to every activity type, not just Follow.
        if (is_string($actorUrl) && $this->isBlocked(actorUrl: $actorUrl, payload: $payload)) {
            $this->handleBlockedActivity(actor: $actor, payload: $payload, actorUrl: $actorUrl, type: $type);

            return;
        }

        // Optional domain allowlist. Configured centrally here (not per
        // handler) so an allowlisted-only instance enforces it for every type.
        if (is_string($actorUrl) && ! $this->isAllowedSender(actorUrl: $actorUrl)) {
            Log::info(
                'InboxProcessor: sender domain not on allowlist; activity ignored',
                ['type' => $type, 'actor' => $actorUrl],
            );

            return;
        }

        // Per-actor/per-domain quotas bound how much a single hostile instance
        // can accumulate. Checked before dedupe so the counters are the gate.
        if (is_string($actorUrl) && $this->isOverQuota(actorUrl: $actorUrl, type: $type)) {
            Log::warning(
                'InboxProcessor: inbox quota exceeded; activity ignored',
                ['type' => $type, 'actor' => $actorUrl],
            );

            return;
        }

        $activityUri = is_string($payload['id'] ?? null) && $payload['id'] !== '' ? $payload['id'] : null;

        $handler = $type !== null ? $this->handlerRegistry->firstForType($type) : null;

        // Reserve the activity id for this local actor *before* the handler
        // runs. The unique(actor_id, activity_uri) key makes the claim atomic,
        // so concurrent redeliveries cannot double-run the handler. The scope
        // is per local actor, so the same activity addressed to a second actor
        // is still processed for that actor.
        if ($handler !== null && $activityUri !== null && ! $this->reserveActivity(
            actorId: $actor->id,
            activityUri: $activityUri,
        )) {
            Log::debug(
                'InboxProcessor: duplicate activity delivery ignored',
                ['activity_uri' => $activityUri, 'type' => $type, 'actor_id' => $actor->id],
            );

            return;
        }

        $inboxRemoteActor = $this->resolveRemoteActorFromPayload(payload: $payload);
        event(new InboxActivityReceived(
            activityType: $type,
            localActorId: $actor->id,
            remoteActorUrl: $inboxRemoteActor?->actor_url,
        ));

        if ($handler !== null) {
            $latestActivityId = $this->latestActivityId(actorId: $actor->id);

            try {
                $handler->handle(actor: $actor, payload: $payload);
            } catch (\Throwable $e) {
                // The reservation exists to stop concurrent redeliveries from
                // double-processing an activity. If the handler failed, the
                // activity was not actually processed, so release the claim and
                // let the queued retry run it again instead of silently
                // dropping the delivery.
                if ($activityUri !== null) {
                    $this->releaseActivity(
                        actorId: $actor->id,
                        activityUri: $activityUri,
                    );
                }

                throw $e;
            }

            if ($activityUri !== null) {
                $this->stampActivityUri(
                    actorId: $actor->id,
                    afterId: $latestActivityId,
                    activityUri: $activityUri,
                );
            }

            return;
        }

        Log::debug(
            'InboxProcessor: Unknown activity type',
            ['type' => $type],
        );
    }

    /**
     * Whether the sender is blocked by domain or by remote actor. Performs no
     * outbound HTTP: actor blocks are matched against already stored rows.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isBlocked(string $actorUrl, array $payload): bool
    {
        // Undo(Block) must be allowed through, otherwise a remote actor could
        // never lift a block it previously placed on us.
        if ($this->isUndoBlock(payload: $payload)) {
            return false;
        }

        $domain = DomainNormalizer::hostFromUrl(url: $actorUrl);

        if ($domain !== null && $this->isBlockedDomain(domain: $domain)) {
            return true;
        }

        return $this->isBlockedActor(actorUrl: $actorUrl);
    }

    protected function isBlockedDomain(string $domain): bool
    {
        $candidates = DomainNormalizer::candidates(domain: $domain);

        if ($candidates === []) {
            return false;
        }

        return BlockedDomain::query()
            ->whereIn('domain', $candidates)
            ->exists();
    }

    protected function isBlockedActor(string $actorUrl): bool
    {
        $remoteActorId = RemoteActor::query()
            ->where('actor_url', '=', $actorUrl)
            ->value('id');

        if ($remoteActorId === null) {
            return false;
        }

        return BlockedRemoteActor::query()
            ->where('remote_actor_id', '=', $remoteActorId)
            ->exists();
    }

    /**
     * Whether the sender is permitted under optional allowlist mode.
     *
     * When `activitypub.inbox.allowlist.enabled` is false (the default) every
     * sender is allowed. When enabled but the configured list is empty, the
     * check is treated as "not configured" and allows traffic rather than
     * silently black-holing an instance that enabled the flag by accident.
     */
    protected function isAllowedSender(string $actorUrl): bool
    {
        if (! config('activitypub.inbox.allowlist.enabled', false)) {
            return true;
        }

        $allowed = config('activitypub.inbox.allowlist.domains', []);

        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $normalizedAllowed = [];

        foreach ($allowed as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            foreach (DomainNormalizer::candidates(domain: $domain) as $candidate) {
                $normalizedAllowed[$candidate] = true;
            }
        }

        $host = DomainNormalizer::hostFromUrl(url: $actorUrl);

        if ($host === null) {
            return false;
        }

        foreach (DomainNormalizer::candidates(domain: $host) as $candidate) {
            if (isset($normalizedAllowed[$candidate])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the sender has exceeded a configured inbox/interaction quota.
     *
     * Counters use a fixed time bucket (minute/day) in the cache. The check
     * fails open: a missing or broken cache store never blocks federation,
     * while the HTTP-level rate limiter still bounds raw request volume.
     */
    protected function isOverQuota(string $actorUrl, ?string $type): bool
    {
        if (! config('activitypub.inbox.quotas.enabled', true)) {
            return false;
        }

        $limits = config('activitypub.inbox.quotas', []);

        if (! is_array($limits)) {
            $limits = [];
        }

        try {
            $minute = (string) intdiv(time(), 60);
            $day = now()->toDateString();

            $perActorMinute = $this->intConfig($limits['per_actor_per_minute'] ?? null, 120);

            if ($perActorMinute > 0 && $this->quotaHit(
                key: 'activitypub:quota:actor-minute:'.md5($actorUrl).':'.$minute,
                limit: $perActorMinute,
                ttlSeconds: 120,
            )) {
                return true;
            }

            $domain = DomainNormalizer::hostFromUrl(url: $actorUrl) ?? $actorUrl;
            $perDomainMinute = $this->intConfig($limits['per_domain_per_minute'] ?? null, 600);

            if ($perDomainMinute > 0 && $this->quotaHit(
                key: 'activitypub:quota:domain-minute:'.md5($domain).':'.$minute,
                limit: $perDomainMinute,
                ttlSeconds: 120,
            )) {
                return true;
            }

            $perActorDay = $this->intConfig($limits['per_actor_per_day'] ?? null, 5000);

            if ($perActorDay > 0 && $this->quotaHit(
                key: 'activitypub:quota:actor-day:'.md5($actorUrl).':'.$day,
                limit: $perActorDay,
                ttlSeconds: 90000,
            )) {
                return true;
            }

            if ($this->isInteractionType(type: $type)) {
                $interactions = $this->intConfig($limits['interactions_per_actor_per_day'] ?? null, 1000);

                if ($interactions > 0 && $this->quotaHit(
                    key: 'activitypub:quota:actor-interactions:'.md5($actorUrl).':'.$day,
                    limit: $interactions,
                    ttlSeconds: 90000,
                )) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning(
                'InboxProcessor: quota check failed; allowing activity',
                ['error' => $e->getMessage()],
            );

            return false;
        }

        return false;
    }

    /**
     * Increment a windowed counter and report whether its limit is exceeded.
     */
    protected function quotaHit(string $key, int $limit, int $ttlSeconds): bool
    {
        Cache::add($key, 0, $ttlSeconds);

        $count = (int) Cache::increment($key);

        return $count > $limit;
    }

    protected function intConfig(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function isInteractionType(?string $type): bool
    {
        return in_array($type, ['Like', 'Announce'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function isUndoBlock(array $payload): bool
    {
        if (($payload['type'] ?? null) !== 'Undo') {
            return false;
        }

        $object = $payload['object'] ?? null;

        if (is_array($object)) {
            return ($object['type'] ?? null) === 'Block';
        }

        if (is_string($object)) {
            $stored = Activity::query()
                ->where('activity_uri', '=', $object)
                ->where('is_incoming', '=', true)
                ->first();

            return $stored !== null && $stored->type === ActivityType::Block;
        }

        return false;
    }

    /**
     * Reject/ignore a blocked activity. Follows get an explicit Reject so the
     * remote server learns the request was declined; everything else is
     * simply dropped.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleBlockedActivity(Actor $actor, array $payload, string $actorUrl, ?string $type): void
    {
        Log::info(
            'InboxProcessor: blocked activity ignored',
            ['type' => $type, 'actor' => $actorUrl],
        );

        if ($type !== 'Follow') {
            return;
        }

        $domain = parse_url($actorUrl, PHP_URL_HOST);
        $domain = is_string($domain) ? $domain : 'unknown';

        $remoteActor = RemoteActor::query()->firstOrCreate(
            ['actor_url' => $actorUrl],
            [
                'inbox_url' => $actorUrl.'/inbox',
                'username' => basename($actorUrl),
                'domain' => $domain,
            ],
        );

        $activityBuilder = app(ActivityBuilderContract::class);
        $activityPubService = app(ActivityPubService::class);

        $rejectActivity = $activityBuilder->reject(
            actor: $actor,
            originalPayload: $payload,
        );

        $rejectRecord = $activityPubService->recordActivity(
            localActor: $actor,
            type: ActivityType::Reject,
            remoteActor: $remoteActor,
            payload: $rejectActivity,
        );

        DeliverActivity::dispatch(
            inboxUrl: $remoteActor->inbox_url,
            activityModelId: $rejectRecord->id,
            actorId: $actor->id,
        );
    }

    /**
     * Atomically reserve an incoming activity id for a local actor.
     *
     * Returns false when the (actor_id, activity_uri) pair is already reserved
     * by an earlier or concurrent delivery. insertOrIgnore relies on the
     * unique index, so the check-and-insert is atomic across workers.
     */
    protected function reserveActivity(int $actorId, string $activityUri): bool
    {
        $inserted = IncomingActivity::query()->insertOrIgnore([
            'actor_id' => $actorId,
            'activity_uri' => $activityUri,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted > 0;
    }

    /**
     * Release a reservation claimed by {@see reserveActivity()} so a
     * redelivery of an activity whose handler threw can be reprocessed.
     */
    protected function releaseActivity(int $actorId, string $activityUri): void
    {
        IncomingActivity::query()
            ->where('actor_id', '=', $actorId)
            ->where('activity_uri', '=', $activityUri)
            ->delete();
    }

    protected function latestActivityId(int $actorId): int
    {
        return (int) Activity::query()
            ->where('actor_id', '=', $actorId)
            ->max('id');
    }

    protected function stampActivityUri(int $actorId, int $afterId, string $activityUri): void
    {
        try {
            Activity::query()
                ->where('actor_id', '=', $actorId)
                ->where('is_incoming', '=', true)
                ->where('id', '>', $afterId)
                ->update(['activity_uri' => $activityUri]);
        } catch (QueryException $e) {
            // A concurrent worker already stamped this activity id. The unique
            // index did its job; treat this delivery as a duplicate.
            Log::debug(
                'InboxProcessor: concurrent duplicate activity detected',
                ['activity_uri' => $activityUri],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveLocalActor(array $payload): ?Actor
    {
        $object = $payload['object'] ?? null;

        $candidates = [];

        if (is_string($object)) {
            $candidates[] = basename($object);
        }

        if (is_array($object)) {
            $candidates = array_merge($candidates, array_filter([
                is_string($object['object'] ?? null) ? basename($object['object']) : null,
                is_string($object['id'] ?? null) ? basename($object['id']) : null,
            ]));
        }

        $urls = array_merge(
            (array) ($payload['to'] ?? []),
            (array) ($payload['cc'] ?? []),
        );

        foreach ($urls as $url) {
            if (is_string($url)) {
                $candidates[] = basename($url);
            }
        }

        $candidates = array_unique(array_filter($candidates));

        foreach ($candidates as $basename) {
            if (isset($this->localActorCache[$basename])) {
                return $this->localActorCache[$basename];
            }
        }

        $actor = $this->actorModelClass()::query()
            ->whereIn('username', $candidates)
            ->first();

        if ($actor !== null) {
            $this->localActorCache[$actor->username] = $actor;

            return $actor;
        }

        return null;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRemoteActorFromPayload(array $payload): ?RemoteActor
    {
        return app(RemoteActorResolver::class)->resolveFromPayload(payload: $payload);
    }
}
