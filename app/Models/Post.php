<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $primary_tag_id
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tag|null $primaryTag
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Like> $likes
 * @property-read Collection<int, PageView> $pageViews
 */
final class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use Searchable;

    protected $fillable = [
        'title', 'slug', 'content', 'status', 'seo_metadata', 'published_at',
        'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
        'og_image_generated_at', 'twitter_title', 'twitter_description', 'twitter_image',
        'canonical_url', 'feature_image_path', 'feature_image_alt', 'feature_image_caption',
        'codeinjection_head', 'codeinjection_foot', 'show_title_and_feature_image',
        'excerpt', 'ghost_uuid', 'primary_tag_id',
    ];

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

    public function primaryTag(): BelongsTo
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
