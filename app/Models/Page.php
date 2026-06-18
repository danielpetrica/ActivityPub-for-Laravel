<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property array $content
 * @property PostStatus $status
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property Carbon|null $og_image_generated_at
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $twitter_image
 * @property string|null $canonical_url
 * @property string|null $feature_image_path
 * @property string|null $feature_image_alt
 * @property string|null $feature_image_caption
 * @property string|null $codeinjection_head
 * @property string|null $codeinjection_foot
 * @property bool $show_title_and_feature_image
 * @property string|null $excerpt
 * @property string|null $ghost_uuid
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PageView> $pageViews
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use Searchable;

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
        'status' => PostStatus::class,
        'og_image_generated_at' => 'datetime',
        'show_title_and_feature_image' => 'bool',
    ];

    public function pageViews(): MorphMany
    {
        return $this->morphMany(related: PageView::class, name: 'viewable');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt ?? '',
            'meta_description' => $this->meta_description ?? '',
        ];
    }
}
