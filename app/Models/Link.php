<?php

namespace App\Models;

use App\Enums\LinkPosition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property LinkPosition $position
 * @property string $label
 * @property string $url
 * @property int $sort_order
 * @property bool $is_external
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class Link extends Model
{
    /** @use HasFactory<\Database\Factories\LinkFactory> */
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
