<?php

namespace DanielPetrica\LaravelActivityPub\Traits;

use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Database\Eloquent\Model;

trait FederatesContent
{
    public static function bootFederatesContent(): void
    {
        static::saved(callback: function (Model $model): void {
            if (! ($model instanceof FederatableContentContract)) {
                return;
            }

            if (! $model->shouldFederate()) {
                return;
            }

            $service = app(ActivityPubService::class);

            if ($model->wasRecentlyCreated) {
                $service->sendCreate(content: $model);
            } else {
                $service->sendUpdate(content: $model);
            }
        });

        static::deleted(callback: function (FederatableContentContract $model): void {
            if (! $model->shouldFederate()) {
                return;
            }

            app(ActivityPubService::class)->sendDelete(
                objectId: $model->getActivityPubId(),
                actor: $model->activityPubActor(),
            );
        });
    }
}
