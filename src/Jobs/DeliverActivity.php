<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Events\ActivityDelivered;
use DanielPetrica\LaravelActivityPub\Events\ActivityDeliveryFailed;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityRecorder;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

final class DeliverActivity implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $uniqueFor = 3600;

    public array $backoff = [30, 120, 600];

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $inboxUrl,
        public int $activityModelId,
        public int $actorId,
    ) {
        $connection = config('activitypub.queue.connection');
        $queue = config('activitypub.queue.queue', 'default');

        if ($connection !== null) {
            $this->onConnection($connection);
        }

        $this->onQueue($queue);
    }

    public function uniqueId(): string
    {
        return sha1($this->inboxUrl.'|'.$this->activityModelId);
    }

    public function handle(DeliveryClient $deliveryClient): void
    {
        $activityModel = Activity::findOrFail($this->activityModelId);
        $actor = Actor::findOrFail($this->actorId);

        $delivery = $this->deliveryRecord();
        $delivery->attempts = max($delivery->attempts + 1, $this->attempts());
        $delivery->save();

        $result = $deliveryClient->deliver(
            inboxUrl: $this->inboxUrl,
            activity: $activityModel->payload,
            actor: $actor,
        );

        if ($result === null) {
            // Encoding failures and SSRF refusals are terminal: retrying cannot help.
            $this->markDelivery(
                delivery: $delivery,
                status: ActivityDelivery::STATUS_FAILED,
                responseCode: null,
                error: 'Failed to encode activity JSON, or inbox URL refused by SSRF guard.',
            );

            Log::debug('DeliverActivity: permanent delivery failure', [
                'inboxUrl' => $this->inboxUrl,
            ]);

            $this->syncAggregateStatus();

            $this->fail(new RuntimeException('Unable to deliver activity to '.$this->inboxUrl));

            return;
        }

        $responseCode = $result['status'];
        $responseBody = $result['body'];

        if ($responseCode >= 200 && $responseCode < 300) {
            $this->markDelivery(
                delivery: $delivery,
                status: ActivityDelivery::STATUS_DELIVERED,
                responseCode: $responseCode,
                error: null,
                deliveredAt: now(),
            );

            Activity::query()
                ->where('id', '=', $this->activityModelId)
                ->update([
                    'debug' => ['response_code' => $responseCode],
                ]);

            event(new ActivityDelivered(
                activityId: $this->activityModelId,
                inboxUrl: $this->inboxUrl,
                actorId: $this->actorId,
            ));

            $this->syncAggregateStatus();

            Log::debug('DeliverActivity: delivered successfully', [
                'inboxUrl' => $this->inboxUrl,
            ]);

            return;
        }

        $error = Str::limit($responseBody, 1000);

        Activity::query()
            ->where('id', '=', $this->activityModelId)
            ->update([
                'debug' => [
                    'response_code' => $responseCode,
                    'response_body' => $error,
                    'attempt' => $this->attempts(),
                ],
            ]);

        if ($this->isTransient($responseCode)) {
            // Keep the per-inbox row pending and throw so the queue retries with
            // the configured backoff. Do NOT fail the job here.
            $this->markDelivery(
                delivery: $delivery,
                status: ActivityDelivery::STATUS_PENDING,
                responseCode: $responseCode,
                error: "Transient failure with status {$responseCode}: {$error}",
            );

            $this->syncAggregateStatus();

            Log::debug('DeliverActivity: transient delivery failure, releasing for retry', [
                'inboxUrl' => $this->inboxUrl,
                'status' => $responseCode,
            ]);

            throw new RuntimeException("DeliverActivity: transient delivery failure with status {$responseCode}");
        }

        // Terminal 4xx/3xx: retrying will not help, so fail permanently.
        $this->markDelivery(
            delivery: $delivery,
            status: ActivityDelivery::STATUS_FAILED,
            responseCode: $responseCode,
            error: "Delivery failed with status {$responseCode}: {$error}",
        );

        $this->syncAggregateStatus();

        Log::debug('DeliverActivity: permanent delivery failure', [
            'inboxUrl' => $this->inboxUrl,
            'status' => $responseCode,
        ]);

        $this->fail(new RuntimeException("Delivery failed with status {$responseCode}"));
    }

    /**
     * The queue invokes this only once the job has genuinely exhausted its
     * retries (or was failed manually). Recoverable attempts never reach here.
     */
    public function failed(\Throwable $e): void
    {
        $delivery = ActivityDelivery::query()
            ->where('activity_id', '=', $this->activityModelId)
            ->where('inbox_url', '=', $this->inboxUrl)
            ->first();

        if ($delivery !== null) {
            $delivery->status = ActivityDelivery::STATUS_FAILED;
            $delivery->last_error = $e->getMessage();
            $delivery->attempts = max($delivery->attempts, $this->attempts());
            $delivery->save();
        }

        Activity::query()
            ->where('id', '=', $this->activityModelId)
            ->update([
                'debug' => [
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                ],
            ]);

        event(new ActivityDeliveryFailed(
            activityId: $this->activityModelId,
            inboxUrl: $this->inboxUrl,
            actorId: $this->actorId,
            error: $e->getMessage(),
        ));

        $this->syncAggregateStatus();

        Log::warning('DeliverActivity: permanently failed', [
            'inboxUrl' => $this->inboxUrl,
            'activityModelId' => $this->activityModelId,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Whether the HTTP response may succeed on a later attempt.
     */
    private function isTransient(int $responseCode): bool
    {
        return $responseCode === 429 || $responseCode >= 500;
    }

    private function deliveryRecord(): ActivityDelivery
    {
        return ActivityDelivery::query()->firstOrCreate(
            [
                'activity_id' => $this->activityModelId,
                'inbox_url' => $this->inboxUrl,
            ],
            [
                'status' => ActivityDelivery::STATUS_PENDING,
                'attempts' => 0,
            ],
        );
    }

    private function markDelivery(
        ActivityDelivery $delivery,
        string $status,
        ?int $responseCode,
        ?string $error,
        ?CarbonInterface $deliveredAt = null,
    ): void {
        $delivery->status = $status;
        $delivery->response_code = $responseCode;
        $delivery->last_error = $error;

        if ($deliveredAt !== null) {
            $delivery->delivered_at = $deliveredAt;
        }

        $delivery->save();
    }

    /**
     * Recompute the aggregate Activity status from every sibling per-inbox
     * delivery row. The activity is delivered only once all inboxes are.
     *
     * Delegates to ActivityRecorder so the queued and synchronous delivery
     * paths share one aggregation implementation.
     */
    private function syncAggregateStatus(): void
    {
        app(ActivityRecorder::class)->syncAggregateStatus(activityId: $this->activityModelId);
    }
}
