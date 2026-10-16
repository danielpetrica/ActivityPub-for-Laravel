<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Events\FlagReceived;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Report;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Support\Facades\Log;

/**
 * Handles an inbound `Flag` activity (a report).
 *
 * The handler is defensive by design: the reporter, the flagged object and the
 * reason may all be missing or unresolvable, and none of that may break inbox
 * processing. It records the flag in the activity log and persists a `Report`
 * row (when the reports table is available), then emits an event and logs.
 */
final class HandleFlagAction implements ActivityHandler
{
    public function __construct(
        private ActivityPubService $activityPubService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function handles(): string
    {
        return 'Flag';
    }

    /** @param array<string, mixed> $payload */
    public function handle(Actor $actor, array $payload): void
    {
        $remoteActor = null;

        try {
            $remoteActor = $this->remoteActorResolver->resolveFromPayload(payload: $payload);
        } catch (\Throwable $e) {
            Log::warning('HandleFlagAction: could not resolve reporting actor', [
                'error' => $e->getMessage(),
            ]);
        }

        [$objectType, $objectId] = $this->extractObject(payload: $payload);
        $reason = $this->extractReason(payload: $payload);
        $activityUri = is_string($payload['id'] ?? null) && $payload['id'] !== '' ? $payload['id'] : null;

        try {
            $this->activityPubService->recordActivity(
                localActor: $actor,
                type: ActivityType::Flag,
                remoteActor: $remoteActor,
                payload: $payload,
                isIncoming: true,
            );
        } catch (\Throwable $e) {
            Log::warning('HandleFlagAction: could not record Flag activity', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Report::query()->create([
                'actor_id' => $actor->id,
                'remote_actor_id' => $remoteActor?->id,
                'object_type' => $objectType,
                'object_id' => $objectId,
                'reason' => $reason,
                'status' => Report::STATUS_OPEN,
                'activity_uri' => $activityUri,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HandleFlagAction: could not persist Report row', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('HandleFlagAction: inbound report received', [
            'local_actor_id' => $actor->id,
            'reporter' => $remoteActor?->actor_url,
            'object_id' => $objectId,
            'object_type' => $objectType,
        ]);

        try {
            event(new FlagReceived(
                localActorId: $actor->id,
                remoteActorUrl: $remoteActor?->actor_url,
                objectId: $objectId,
                objectType: $objectType,
                reason: $reason,
            ));
        } catch (\Throwable $e) {
            Log::warning('HandleFlagAction: listener threw while handling FlagReceived', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract the flagged target from `object`, which may be a string IRI, an
     * embedded object, or an array of either. Returns
     * `[objectType, objectId]` with both null when nothing usable is present.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string|null, 1: string|null}
     */
    protected function extractObject(array $payload): array
    {
        $object = $payload['object'] ?? null;

        if (is_string($object) && $object !== '') {
            return [null, $object];
        }

        if (! is_array($object)) {
            return [null, null];
        }

        // A list of targets: use the first resolvable entry.
        if (array_is_list($object)) {
            foreach ($object as $entry) {
                [$type, $id] = $this->extractObject(payload: ['object' => $entry]);

                if ($id !== null) {
                    return [$type, $id];
                }
            }

            return [null, null];
        }

        $type = is_string($object['type'] ?? null) ? $object['type'] : null;
        $id = is_string($object['id'] ?? null) ? $object['id'] : null;

        return [$type, $id];
    }

    /** @param array<string, mixed> $payload */
    protected function extractReason(array $payload): ?string
    {
        $content = $payload['content'] ?? null;

        if (is_string($content) && $content !== '') {
            return $content;
        }

        $summary = $payload['summary'] ?? null;

        return is_string($summary) && $summary !== '' ? $summary : null;
    }
}
