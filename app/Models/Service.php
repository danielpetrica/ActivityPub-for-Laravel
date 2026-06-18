<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ServiceFactory;
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
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
final class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'intro_content', 'main_content', 'seo_metadata', 'is_active'];

    protected $casts = [
        'seo_metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function caseStudies(): BelongsToMany
    {
        return $this->belongsToMany(related: CaseStudy::class);
    }
}
