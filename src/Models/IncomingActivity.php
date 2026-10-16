<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Reserved incoming activity ids, scoped to the local actor they were
 * addressed to. The unique(actor_id, activity_uri) key is what makes the
 * insert-or-ignore claim atomic across concurrent workers.
 *
 * @property int $id
 * @property int $actor_id
 * @property string $activity_uri
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
final class IncomingActivity extends Model
{
    protected $fillable = [
        'actor_id',
        'activity_uri',
    ];
}
