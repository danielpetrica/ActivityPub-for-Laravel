<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-inbox delivery state for an outgoing {@see Activity}.
 *
 * A single Activity fanned out to N inboxes produces N ActivityDelivery rows.
 * The Activity's aggregate status is derived from these rows by DeliverActivity.
 *
 * @property int $id
 * @property int $activity_id
 * @property string $inbox_url
 * @property string $status
 * @property int $attempts
 * @property int|null $response_code
 * @property string|null $last_error
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Activity $activity
 */
final class ActivityDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $table = 'activity_deliveries';

    protected $fillable = [
        'activity_id',
        'inbox_url',
        'status',
        'attempts',
        'response_code',
        'last_error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'response_code' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
