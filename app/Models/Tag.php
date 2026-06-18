<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $image_path
 * @property string|null $description
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image
 * @property Carbon|null $og_image_generated_at
 * @property string|null $twitter_title
 * @property string|null $twitter_description
 * @property string|null $twitter_image
 * @property string|null $accent_color
 * @property string|null $canonical_url
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Post> $posts
 */
final class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use Searchable;

    protected $fillable = [
        'name', 'slug', 'seo_metadata', 'image_path', 'description',
        'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
        'og_image_generated_at', 'twitter_title', 'twitter_description', 'twitter_image',
        'accent_color', 'canonical_url',
    ];

    protected $casts = [
        'og_image_generated_at' => 'datetime',
        'seo_metadata' => 'array',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(related: Post::class);
    }

    public function announcements(): BelongsToMany
    {
        return $this->belongsToMany(related: Announcement::class);
    }

    public function primaryTagPosts(): HasMany
    {
        return $this->hasMany(related: Post::class, foreignKey: 'primary_tag_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description ?? '',
        ];
    }
}
