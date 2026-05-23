<?php

namespace App\Models;

use App\Enums\LinkPosition;
use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LinkPosition $position
 * @property string $label
 * @property string $url
 * @property int $sort_order
 * @property bool $is_external
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'position' => LinkPosition::class,
            'is_external' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
