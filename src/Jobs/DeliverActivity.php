<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class DeliverActivity implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public array $backoff = [30, 120, 600];

    public int $tries = 3;

    public int $maxExceptions = 3;

    /**
     * @param  array<string, mixed>  $activity
     */
    public function __construct(
        public string $inboxUrl,
        public array $activity,
        public Actor $actor,
        public ?int $activityId = null,
    ) {}

    public function uniqueId(): string
    {
        return sha1($this->inboxUrl.'|'.($this->activityId ?? $this->activity['id'] ?? ''));
    }

    public function handle(DeliveryClient $deliveryClient): void
    {
        $responseCode = $deliveryClient->deliver(
            inboxUrl: $this->inboxUrl,
            activity: $this->activity,
            actor: $this->actor,
        );

        if ($responseCode === null) {
            Log::debug('DeliverActivity: failed to encode activity JSON', [
                'inboxUrl' => $this->inboxUrl,
            ]);

            return;
        }

        if ($responseCode >= 200 && $responseCode < 300) {
            if ($this->activityId !== null) {
                Activity::query()
                    ->where(column: 'id', operator: '=', value: $this->activityId)
                    ->update(values: [
                        'status' => 'delivered',
                        'delivered_at' => now(),
                    ]);
            }

            Log::debug('DeliverActivity: delivered successfully', [
                'inboxUrl' => $this->inboxUrl,
            ]);
        } else {
            Log::debug('DeliverActivity: delivery failed', [
                'inboxUrl' => $this->inboxUrl,
                'status' => $responseCode,
            ]);

            $this->release(delay: 60);
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($this->activityId !== null) {
            Activity::query()
                ->where('id', '=', $this->activityId)
                ->update(['status' => 'failed']);
        }

        Log::warning('DeliverActivity: permanently failed', [
            'inboxUrl' => $this->inboxUrl,
            'activityId' => $this->activityId,
            'error' => $e->getMessage(),
        ]);
    }
}
