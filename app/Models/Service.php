<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $intro_content
 * @property string|null $main_content
 * @property array|null $seo_metadata
 * @property bool $is_active
 * @property \Carbon\CarbonInterface $created_at
 * @property \Carbon\CarbonInterface $updated_at
 */
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'seo_metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function caseStudies(): BelongsToMany
    {
        return $this->belongsToMany(related: CaseStudy::class);
    }
}
