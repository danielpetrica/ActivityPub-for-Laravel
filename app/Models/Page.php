<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageView> $pageViews
 */
class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
        'status' => PostStatus::class,
        'show_title_and_feature_image' => 'bool',
    ];

    public function pageViews(): MorphMany
    {
        return $this->morphMany(related: PageView::class, name: 'viewable');
    }
}
