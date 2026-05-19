<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property \Illuminate\Support\Carbon|null $og_image_generated_at
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
 * @property int|null $primary_tag_id
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Tag|null $primaryTag
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Like> $likes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageView> $pageViews
 */
class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'content' => 'array',
        'status' => PostStatus::class,
        'published_at' => 'datetime',
        'og_image_generated_at' => 'datetime',
        'show_title_and_feature_image' => 'bool',
        'seo_metadata' => 'array',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(related: Tag::class);
    }

    public function primaryTag(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(related: Tag::class, foreignKey: 'primary_tag_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(related: Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(related: Like::class);
    }

    public function pageViews(): MorphMany
    {
        return $this->morphMany(related: PageView::class, name: 'viewable');
    }
}
