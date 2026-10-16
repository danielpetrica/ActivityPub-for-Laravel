<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property InteractionType $type
 * @property int|null $actor_id
 * @property int|null $remote_actor_id
 * @property string $object_type
 * @property string $object_id
 * @property InteractionStatus $status
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Actor|null $actor
 * @property-read RemoteActor|null $remoteActor
 */
final class Interaction extends Model
{
    protected $fillable = [
        'type',
        'actor_id',
        'remote_actor_id',
        'object_type',
        'object_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'status' => InteractionStatus::class,
        ];
    }

    /** @phpstan-ignore missingType.generics */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @phpstan-ignore missingType.generics */
    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(RemoteActor::class);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Interaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '=', InteractionStatus::Active);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Interaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType(Builder $query, InteractionType $type): Builder
    {
        return $query->where('type', '=', $type);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Interaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForObject(Builder $query, string $objectType, string $objectId): Builder
    {
        return $query
            ->where('object_type', '=', $objectType)
            ->where('object_id', '=', $objectId);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<Interaction> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForActor(Builder $query, int $actorId): Builder
    {
        return $query->where('actor_id', '=', $actorId);
    }

    public function isUndone(): bool
    {
        return $this->status === InteractionStatus::Undone;
    }
}
