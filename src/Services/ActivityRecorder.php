<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Enums\ActivityStatus;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persistence-only helper for the activity log.
 *
 * Owns every write to the `activities` table (recording, delivery state and
 * resolution diagnostics) so orchestration can stay in ActivityPubService and
 * delivery in ActivityDispatcher.
 */
final class ActivityRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Actor $localActor,
        ActivityType $type,
        ?RemoteActor $remoteActor,
        array $payload,
        bool $isIncoming = false,
    ): Activity {
        // Track last activity for outbound actions
        if (! $isIncoming) {
            $localActor->update(['last_active_at' => now()]);
        }

        $object = $payload['object'] ?? [];
        $objectId = is_string($object) ? $object : ($object['id'] ?? null);
        $objectType = is_array($object) ? ($object['type'] ?? null) : null;

        return Activity::query()->create([
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

    /**
     * Record (or advance) the per-inbox delivery state for an activity and
     * recompute the aggregate activity status from every sibling row.
     *
     * This is the shared primitive used by both delivery paths so the queued
     * (`DeliverActivity`) and synchronous (`ActivityDispatcher`) paths leave the
     * same observable state in `activity_deliveries`.
     */
    public function recordInboxDelivery(
        int $activityId,
        string $inboxUrl,
        string $status,
        ?int $responseCode = null,
        ?string $error = null,
        ?CarbonInterface $deliveredAt = null,
    ): void {
        $delivery = ActivityDelivery::query()->firstOrCreate(
            [
                'activity_id' => $activityId,
                'inbox_url' => $inboxUrl,
            ],
            [
                'status' => ActivityDelivery::STATUS_PENDING,
                'attempts' => 0,
            ],
        );

        $delivery->attempts = $delivery->attempts + 1;
        $delivery->status = $status;
        $delivery->response_code = $responseCode;
        $delivery->last_error = $error;

        if ($deliveredAt !== null) {
            $delivery->delivered_at = $deliveredAt;
        }

        $delivery->save();

        $this->syncAggregateStatus(activityId: $activityId);
    }

    /**
     * Recompute the aggregate Activity status from every per-inbox delivery
     * row. The activity is delivered only once every inbox has delivered.
     */
    public function syncAggregateStatus(int $activityId): void
    {
        DB::transaction(function () use ($activityId): void {
            $activity = Activity::query()->lockForUpdate()->find($activityId);

            if ($activity === null) {
                return;
            }

            $deliveries = ActivityDelivery::query()
                ->where('activity_id', '=', $activityId)
                ->get();

            if ($deliveries->isEmpty()) {
                return;
            }

            if ($deliveries->contains('status', ActivityDelivery::STATUS_PENDING)) {
                Activity::query()
                    ->where('id', '=', $activityId)
                    ->update([
                        'status' => ActivityStatus::Pending->value,
                        'delivered_at' => null,
                    ]);

                return;
            }

            if ($deliveries->contains('status', ActivityDelivery::STATUS_FAILED)) {
                Activity::query()
                    ->where('id', '=', $activityId)
                    ->update([
                        'status' => ActivityStatus::Failed->value,
                        'delivered_at' => null,
                    ]);

                return;
            }

            $latestDelivery = $deliveries->sortByDesc('delivered_at')->first();

            Activity::query()
                ->where('id', '=', $activityId)
                ->update([
                    'status' => ActivityStatus::Delivered->value,
                    'delivered_at' => $latestDelivery->delivered_at ?? now(),
                ]);
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordResolutionFailure(?int $activityId, string $reason, array $context = []): void
    {
        Log::warning('ActivityPub delivery resolution failed: '.$reason, $context);

        if ($activityId === null) {
            return;
        }

        Activity::query()
            ->where('id', '=', $activityId)
            ->update([
                'debug' => array_merge(['error' => $reason], $context),
            ]);
    }
}
