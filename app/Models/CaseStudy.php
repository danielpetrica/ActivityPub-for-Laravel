<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property array|null $images
 * @property bool $is_active
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 */
class CaseStudy extends Model
{
    /** @use HasFactory<\Database\Factories\CaseStudyFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(related: Service::class);
    }
}
