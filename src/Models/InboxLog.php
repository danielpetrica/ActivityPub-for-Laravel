<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_url
 * @property string|null $key_id
 * @property string|null $ip
 * @property string $method
 * @property string $path
 * @property int $status_code
 * @property string $reason
 * @property string|null $body_summary
 * @property array|null $headers
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 * @property-read Actor $actor
 */
final class InboxLog extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_url',
        'key_id',
        'ip',
        'method',
        'path',
        'status_code',
        'reason',
        'body_summary',
        'headers',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
