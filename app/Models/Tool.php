<?php

namespace App\Models;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $html_content
 * @property array|null $seo_metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PageView> $pageViews
 */
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
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
