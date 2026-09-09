<?php

namespace DanielPetrica\LaravelActivityPub\Traits;

use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Database\Eloquent\Model;

trait FederatesContent
{
    public static function bootFederatesContent(): void
    {
        // Capture federatable state BEFORE the save
        static::saving(function (Model $model): void {
            if (! ($model instanceof FederatableContentContract)) {
                return;
            }

            $model->setAttribute('_was_federatable_before_save', $model->shouldFederate() ? 1 : 0);
        });

        static::saved(function (Model $model): void {
            if (! ($model instanceof FederatableContentContract)) {
                return;
            }

            if (! $model->shouldFederate()) {
                return;
            }

            $service = app(ActivityPubService::class);

            // Send Create if:
            // - Model was just created, OR
            // - Model was NOT federatable before this save (draft → published)
            $wasFederatable = (bool) $model->getAttribute('_was_federatable_before_save');

            if ($model->wasRecentlyCreated || ! $wasFederatable) {
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

    public function isActivityPubPinned(): bool
    {
        return false;
    }
}
