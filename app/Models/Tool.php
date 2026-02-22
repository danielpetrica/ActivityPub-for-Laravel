<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $html_content
 * @property array|null $seo_metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageView> $pageViews
 */
class Tool extends Model
{
    /** @use HasFactory<\Database\Factories\ToolFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'seo_metadata' => 'array',
    ];

    public function pageViews(): MorphMany
    {
        return $this->morphMany(related: PageView::class, name: 'viewable');
    }
}
