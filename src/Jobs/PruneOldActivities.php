<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Models\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class PruneOldActivities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $daysOld = 30,
    ) {}

    public function handle(): void
    {
        Activity::query()
            ->where(column: 'created_at', operator: '<', value: now()->subDays(days: $this->daysOld))
            ->where(column: 'status', operator: '=', value: 'delivered')
            ->orderBy('id')
            ->chunkById(1000, fn ($activities) => (new Activity)->newQuery()->whereIn('id', $activities->pluck('id'))->delete());
    }
}
