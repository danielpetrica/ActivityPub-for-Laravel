<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $text
 * @property bool $is_active
 * @property bool $is_cross_site
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, Tag> $tags
 */
final class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'is_active',
        'is_cross_site',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_cross_site' => 'boolean',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(related: Tag::class);
    }
}
