<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class FetchRemoteActor implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>|null  $data  Pre-fetched data (optional)
     */
    public function __construct(
        public string $actorUri,
        public ?array $data = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->actorUri;
    }

    public function handle(): void
    {
        app(RemoteActorResolver::class)->resolve(
            actorUri: $this->actorUri,
            preFetchedData: $this->data,
        );
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchRemoteActor: permanently failed', [
            'actorUri' => $this->actorUri,
            'error' => $e->getMessage(),
        ]);
    }
}
