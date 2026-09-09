<?php

namespace DanielPetrica\LaravelActivityPub\Jobs;

use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ForwardPostsToNewServerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public array $backoff = [30, 120, 600];

    public function uniqueId(): string
    {
        return 'forward-posts:'.$this->remoteActorId;
    }

    public function __construct(
        public int $remoteActorId,
        public int $actorId,
        public int $maxPosts = 3,
    ) {}

    public function handle(ActivityPubService $activityPubService): void
    {
        $remoteActor = RemoteActor::find($this->remoteActorId);
        $actor = Actor::find($this->actorId);

        if ($remoteActor === null || $actor === null) {
            return;
        }

        $models = config('activitypub.federatable_models', []);

        if (empty($models)) {
            Log::info('ForwardPostsToNewServerJob: no federatable models configured');
            return;
        }

        $posts = collect();

        foreach ($models as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();

            if (! ($model instanceof FederatableContentContract)) {
                continue;
            }

            // Query published posts, oldest first, limited by maxPosts
            $query = $model->newQuery()
                ->where(function ($query) use ($modelClass) {
                    // If the model has a 'published_at' column, use it
                    $instance = new $modelClass();
                    if (Schema::hasColumn($instance->getTable(), 'published_at')) {
                        $query->whereNotNull('published_at');
                    }
                })
                ->orderBy('created_at', 'asc')
                ->limit($this->maxPosts - $posts->count());

            $results = $query->get()
                ->filter(fn ($post) => $post->shouldFederate())
                ->values();

            $posts = $posts->merge($results);

            if ($posts->count() >= $this->maxPosts) {
                break;
            }
        }

        if ($posts->isEmpty()) {
            Log::info('ForwardPostsToNewServerJob: no published posts to forward', [
                'actor' => $actor->username,
                'remote_actor' => $remoteActor->actor_url,
            ]);
            return;
        }

        foreach ($posts->take($this->maxPosts) as $post) {
            try {
                $activityPubService->sendCreateForActor(
                    content: $post,
                    actor: $actor,
                );

                Log::info('ForwardPostsToNewServerJob: forwarded post', [
                    'post_id' => $post->getActivityPubId(),
                    'remote_actor' => $remoteActor->actor_url,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ForwardPostsToNewServerJob: failed to forward post', [
                    'post_id' => $post->getActivityPubId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ForwardPostsToNewServerJob: permanently failed', [
            'remoteActorId' => $this->remoteActorId,
            'actorId' => $this->actorId,
            'error' => $e->getMessage(),
        ]);
    }
}
