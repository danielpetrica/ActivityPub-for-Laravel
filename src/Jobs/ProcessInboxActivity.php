<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the inbox pipeline off the HTTP request. Retry hygiene mirrors
 * {@see DeliverActivity}: bounded tries, exponential backoff and a hard
 * timeout so a poisoned payload cannot occupy a worker forever.
 */
final class ProcessInboxActivity implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
    ) {
        $connection = config('activitypub.queue.connection');
        $queue = config('activitypub.queue.queue', 'default');

        if ($connection !== null) {
            $this->onConnection($connection);
        }

        $this->onQueue(is_string($queue) ? $queue : 'default');
    }

    public function uniqueId(): string
    {
        $id = $this->payload['id'] ?? null;

        if (is_string($id) && $id !== '') {
            return sha1($id);
        }

        return sha1(json_encode($this->payload) ?: 'inbox');
    }

    public function handle(): void
    {
        app(InboxProcessor::class)->process(payload: $this->payload);
    }
}
