<?php

namespace DanielPetrica\LaravelActivityPub\Console\Commands;

use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class DeliverContentCommand extends Command
{
    protected $signature = 'activitypub:deliver-content
        {model : Fully qualified model class, e.g. App\\Models\\Post}
        {id : The record ID of the content to federate}
        {--actor= : Actor username to deliver as (defaults to content\'s actor)}
        {--debug : Deliver synchronously and show per-follower HTTP response codes}';

    protected $description = 'Manually deliver content to all followers of an actor.';

    public function handle(ActivityPubService $activityPubService): int
    {
        $modelClass = $this->argument('model');
        $id = $this->argument('id');

        if (! class_exists($modelClass)) {
            $this->components->error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        $content = $modelClass::query()->find($id);

        if ($content === null) {
            $this->components->error("No record found with ID [{$id}] in [{$modelClass}].");

            return self::FAILURE;
        }

        if (! ($content instanceof FederatableContentContract)) {
            $this->components->error(sprintf(
                'Model [%s] must implement %s.',
                $modelClass,
                FederatableContentContract::class,
            ));

            return self::FAILURE;
        }

        $actorUsername = $this->option('actor');

        if ($actorUsername !== null) {
            $actor = Actor::query()
                ->where(column: 'username', operator: '=', value: $actorUsername)
                ->first();
        } else {
            $actorContract = $content->activityPubActor();

            $actor = Actor::query()
                ->where(column: 'username', operator: '=', value: $actorContract->getPreferredUsername())
                ->first();
        }

        if ($actor === null) {
            throw new ModelNotFoundException(
                message: 'Could not resolve a local Actor for this content.',
            );
        }

        if (! config('activitypub.federation.enabled')) {
            $this->components->warn('Federation is disabled. Set ACTIVITYPUB_FEDERATION_ENABLED=true to deliver.');

            return self::SUCCESS;
        }

        if ($this->option('debug')) {
            $result = $activityPubService->sendCreateForActorSync(
                content: $content,
                actor: $actor,
            );

            $total = count($result['results']);

            $this->components->info(sprintf(
                'Delivered "%s" #%s as @%s — %d follower(s).',
                $modelClass,
                $id,
                $actor->username,
                $total,
            ));

            if ($total > 0) {
                $this->table(
                    headers: ['Actor', 'Inbox URL', 'Response Code'],
                    rows: array_map(
                        callback: fn (array $row): array => [
                            $row['actor_url'],
                            $row['inbox_url'],
                            $row['response_code'] ?? 'error',
                        ],
                        array: $result['results'],
                    ),
                );
            }

            return self::SUCCESS;
        }

        $record = $activityPubService->sendCreateForActor(
            content: $content,
            actor: $actor,
        );

        $this->components->info(sprintf(
            'Dispatched delivery of "%s" #%s as @%s (Activity #%d).',
            $modelClass,
            $id,
            $actor->username,
            $record->id,
        ));

        return self::SUCCESS;
    }
}
