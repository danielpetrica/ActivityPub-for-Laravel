<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Events\ActivityDelivered;
use DanielPetrica\LaravelActivityPub\Events\ActivityDeliveryFailed;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    ) {}

    public function uniqueId(): string
    {
        return sha1($this->inboxUrl.'|'.$this->activityModelId);
    }

    public function handle(DeliveryClient $deliveryClient): void
    {
        $activityModel = Activity::findOrFail($this->activityModelId);
        $actor = Actor::findOrFail($this->actorId);
        $activity = $activityModel->payload;

        $result = $deliveryClient->deliver(
            inboxUrl: $this->inboxUrl,
            activity: $activity,
            actor: $actor,
        );

        if ($result === null) {
            Activity::query()
                ->where('id', '=', $this->activityModelId)
                ->update([
                    'debug' => ['error' => 'Failed to encode activity JSON'],
                ]);

            Log::debug('DeliverActivity: failed to encode activity JSON', [
                'inboxUrl' => $this->inboxUrl,
            ]);

            return;
        }

        $responseCode = $result['status'];
        $responseBody = $result['body'];

        if ($responseCode >= 200 && $responseCode < 300) {
            Activity::query()
                ->where(column: 'id', operator: '=', value: $this->activityModelId)
                ->update(values: [
                    'status' => 'delivered',
                    'delivered_at' => now(),
                    'debug' => ['response_code' => $responseCode],
                ]);

            event(new ActivityDelivered(
                activityId: $this->activityModelId,
                inboxUrl: $this->inboxUrl,
                actorId: $this->actorId,
            ));

            Log::debug('DeliverActivity: delivered successfully', [
                'inboxUrl' => $this->inboxUrl,
            ]);
        } else {
            Activity::query()
                ->where('id', '=', $this->activityModelId)
                ->update([
                    'debug' => [
                        'response_code' => $responseCode,
                        'response_body' => Str::limit($responseBody, 1000),
                        'attempt' => $this->attempts(),
                    ],
                ]);

            Log::debug('DeliverActivity: delivery failed', [
                'inboxUrl' => $this->inboxUrl,
                'status' => $responseCode,
            ]);

            $this->release(delay: 60);
        }
    }

    public function failed(\Throwable $e): void
    {
        Activity::query()
            ->where('id', '=', $this->activityModelId)
            ->update([
                'status' => 'failed',
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

        Log::warning('DeliverActivity: permanently failed', [
            'inboxUrl' => $this->inboxUrl,
            'activityModelId' => $this->activityModelId,
            'error' => $e->getMessage(),
        ]);
    }
}
