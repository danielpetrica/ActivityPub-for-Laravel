<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $actor_id
 * @property int $remote_actor_id
 * @property FollowerStatus $status
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Actor $actor
 * @property-read RemoteActor $remoteActor
 */
final class Follower extends Model
{
    protected $fillable = [
        'actor_id',
        'remote_actor_id',
        'status',
    ];

    protected $casts = [
        'status' => FollowerStatus::class,
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(RemoteActor::class);
    }
}
