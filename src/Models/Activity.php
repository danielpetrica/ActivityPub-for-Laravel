<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Enums\ActivityStatus;
use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $actor_id
 * @property ActivityType $type
 * @property string|null $object_type
 * @property string|null $object_id
 * @property array $payload
 * @property string $status
 * @property CarbonInterface|null $delivered_at
 * @property bool $is_incoming
 * @property int|null $remote_actor_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Actor $actor
 * @property-read RemoteActor|null $remoteActor
 */
final class Activity extends Model
{
    protected $fillable = [
        'actor_id',
        'type',
        'object_type',
        'object_id',
        'payload',
        'status',
        'delivered_at',
        'remote_actor_id',
        'is_incoming',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'is_incoming' => 'boolean',
            'status' => ActivityStatus::class,
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(related: Actor::class);
    }

    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(related: RemoteActor::class);
    }
}
