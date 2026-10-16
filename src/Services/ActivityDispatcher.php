<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use Closure;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single home for every outbound delivery path.
 *
 * Follower fan-out (queued and synchronous), single-inbox and multi-inbox
 * delivery all funnel through here. The `DeliverActivity` dispatch contract
 * (`inboxUrl` / `activityModelId` / `actorId`), the shared-inbox grouping and
 * the `chunk(200)` batching are preserved from the previous implementation.
 */
final class ActivityDispatcher
{
    public function __construct(
        private DeliveryClient $deliveryClient,
        private ActivityRecorder $recorder,
    ) {}

    /**
     * Queue one delivery per unique shared inbox of every accepted follower.
     */
    public function dispatchToFollowers(Actor $localActor, ?int $activityId): void
    {
        if (! config('activitypub.federation.enabled')) {
            return;
        }

        $dispatchBatch = function ($followers) use ($localActor, $activityId): void {
            $groups = [];

            foreach ($followers as $follower) {
                $inboxUrl = $this->inboxUrlFor(remoteActor: $follower->remoteActor);
                $groups[$inboxUrl] = true;
            }

            foreach (array_keys($groups) as $inboxUrl) {
                $this->dispatchToInbox(
                    inboxUrl: $inboxUrl,
                    activityId: $activityId,
                    actorId: $localActor->id,
                );
            }
        };

        $this->eachAcceptedFollower(actorId: $localActor->id, callback: $dispatchBatch);
    }

    /**
     * Synchronously deliver to every accepted follower, de-duplicating shared
     * inboxes and reusing the cached response code for repeated inboxes.
     *
     * @param  array<string, mixed>  $activity
     * @return list<array{actor_url: string, inbox_url: string, response_code: int|null}>
     */
    public function dispatchToFollowersSync(Actor $localActor, array $activity, ?int $activityId = null): array
    {
        $results = [];

        if (! config('activitypub.federation.enabled')) {
            return $results;
        }

        $syncBatch = function ($followers) use ($activity, $localActor, $activityId, &$results): void {
            $sent = [];

            foreach ($followers as $follower) {
                $inboxUrl = $this->inboxUrlFor(remoteActor: $follower->remoteActor);

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

        $this->eachAcceptedFollower(actorId: $localActor->id, callback: $syncBatch);

        return $results;
    }

    /**
     * Queue a single inbox delivery.
     */
    public function dispatchToInbox(string $inboxUrl, ?int $activityId, int $actorId): void
    {
        DeliverActivity::dispatch(
            inboxUrl: $inboxUrl,
            activityModelId: $activityId,
            actorId: $actorId,
        );
    }

    /**
     * Queue one delivery per inbox in the given list.
     *
     * @param  array<int, string>  $inboxUrls
     */
    public function dispatchToInboxes(array $inboxUrls, ?int $activityId, int $actorId): void
    {
        foreach ($inboxUrls as $inboxUrl) {
            $this->dispatchToInbox(
                inboxUrl: $inboxUrl,
                activityId: $activityId,
                actorId: $actorId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $activity
     */
    private function deliverSingleSync(string $inboxUrl, array $activity, Actor $actor, ?int $activityId): ?int
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

            if ($activityId !== null) {
                $this->recorder->recordInboxDelivery(
                    activityId: $activityId,
                    inboxUrl: $inboxUrl,
                    status: ActivityDelivery::STATUS_FAILED,
                    responseCode: null,
                    error: 'Failed to encode activity JSON, or inbox URL refused by SSRF guard.',
                );
            }

            return null;
        }

        $responseCode = $result['status'];

        // Mirror the queued path's observable state: record a per-inbox row and
        // derive the aggregate activity status from every sibling row, instead
        // of marking the activity delivered on the first successful inbox.
        if ($activityId !== null) {
            $this->recordSyncDelivery(
                activityId: $activityId,
                inboxUrl: $inboxUrl,
                responseCode: $responseCode,
                body: $result['body'],
            );
        }

        return $responseCode;
    }

    /**
     * Persist the outcome of one synchronous delivery attempt using the same
     * status semantics as the queued `DeliverActivity` job: 2xx delivered,
     * 429/5xx transient (stays pending for a later reschedule), anything else
     * terminal failure.
     */
    private function recordSyncDelivery(int $activityId, string $inboxUrl, int $responseCode, string $body): void
    {
        if ($responseCode >= 200 && $responseCode < 300) {
            $this->recorder->recordInboxDelivery(
                activityId: $activityId,
                inboxUrl: $inboxUrl,
                status: ActivityDelivery::STATUS_DELIVERED,
                responseCode: $responseCode,
                error: null,
                deliveredAt: now(),
            );

            return;
        }

        if ($responseCode === 429 || $responseCode >= 500) {
            $this->recorder->recordInboxDelivery(
                activityId: $activityId,
                inboxUrl: $inboxUrl,
                status: ActivityDelivery::STATUS_PENDING,
                responseCode: $responseCode,
                error: 'Transient failure with status '.$responseCode.': '.Str::limit($body, 1000),
            );

            return;
        }

        $this->recorder->recordInboxDelivery(
            activityId: $activityId,
            inboxUrl: $inboxUrl,
            status: ActivityDelivery::STATUS_FAILED,
            responseCode: $responseCode,
            error: 'Delivery failed with status '.$responseCode.': '.Str::limit($body, 1000),
        );
    }

    /**
     * Preferred inbox for a remote actor, honouring its shared inbox.
     */
    private function inboxUrlFor(RemoteActor $remoteActor): string
    {
        $sharedInbox = $remoteActor->getAttribute('shared_inbox_url');

        return is_string($sharedInbox) && $sharedInbox !== ''
            ? $sharedInbox
            : $remoteActor->inbox_url;
    }

    private function eachAcceptedFollower(int $actorId, Closure $callback): void
    {
        Follower::query()
            ->with('remoteActor')
            ->where('actor_id', '=', $actorId)
            ->where('status', '=', FollowerStatus::Accepted)
            ->chunk(200, $callback);
    }
}
