<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inbound moderation report (ActivityStreams `Flag`).
 *
 * Fields are deliberately nullable: a remote server may report an object or
 * actor we cannot resolve, and the report must still be recorded so operators
 * can see it. Reporting never blocks inbox processing.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $remote_actor_id
 * @property string|null $object_type
 * @property string|null $object_id
 * @property string|null $reason
 * @property string $status
 * @property string|null $activity_uri
 * @property array<string, mixed>|null $payload
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Actor|null $actor
 * @property-read RemoteActor|null $remoteActor
 */
final class Report extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'actor_id',
        'remote_actor_id',
        'object_type',
        'object_id',
        'reason',
        'status',
        'activity_uri',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Actor, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /**
     * @return BelongsTo<RemoteActor, $this>
     */
    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(RemoteActor::class);
    }
}
