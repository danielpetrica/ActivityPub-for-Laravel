<?php

namespace App\Models;

use App\Enums\PostStatus;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;
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
final class Post extends Model implements FederatableContentContract
{
    use FederatesContent;

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

    public function shouldFederate(): bool
    {
        return config('activitypub.federation.enabled', false) && $this->status === PostStatus::Published;
    }

    public function activityPubActor(): ActorContract
    {
        $user = User::query()->first();

        if ($user === null) {
            throw new \RuntimeException(message: 'No user found for ActivityPub actor.');
        }

        return $user;
    }

    public function getActivityPubId(): string
    {
        return $this->activityPubActor()->getActorId().'/posts/'.$this->slug;
    }

    public function getActivityPubType(): string
    {
        return 'Article';
    }

    public function getActivityPubName(): ?string
    {
        return $this->title;
    }

    public function getActivityPubContent(): string
    {
        $html = '';
        $content = $this->content;

        if (is_array($content)) {
            foreach ($content as $node) {
                if (isset($node['content'])) {
                    foreach ($node['content'] as $child) {
                        if (isset($child['text'])) {
                            $html .= $child['text'].' ';
                        }
                    }
                }
            }
        }

        $text = trim(string: strip_tags(string: $html));
        $text = mb_substr(string: $text, start: 0, length: 500);

        if (mb_strlen(string: strip_tags(string: $html)) > 500) {
            $text .= '...';
        }

        $url = $this->getActivityPubUrl();
        $text .= "\n\n".'Read the full article at: '.$url;

        return $text;
    }

    public function getActivityPubSummary(): ?string
    {
        return $this->excerpt;
    }

    public function getActivityPubUrl(): string
    {
        return route(name: 'static.post', parameters: ['slug' => $this->slug]);
    }

    public function getActivityPubPublishedAt(): string
    {
        return ($this->published_at ?? $this->created_at)->toIso8601String();
    }

    public function getActivityPubAttributedTo(): string
    {
        return $this->activityPubActor()->getActorId();
    }

    public function getActivityPubTo(): string
    {
        return 'https://www.w3.org/ns/activitystreams#Public';
    }

    public function getActivityPubCc(): ?string
    {
        return $this->activityPubActor()->getFollowersUrl();
    }

    public function getActivityPubAttachments(): array
    {
        $attachments = [];

        if ($this->feature_image_path !== null) {
            $imageUrl = $this->feature_image_path;

            if (! str_starts_with(haystack: $imageUrl, needle: 'http')) {
                $imageUrl = config('app.url').'/'.$imageUrl;
            }

            $attachments[] = [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => $imageUrl,
                'name' => $this->feature_image_alt ?? $this->title,
            ];
        }

        return $attachments;
    }

    public function getActivityPubTags(): array
    {
        return $this->tags->map(function (Tag $tag) {
            return [
                'type' => 'Hashtag',
                'href' => route(name: 'static.tag', parameters: ['slug' => $tag->slug]),
                'name' => '#'.$tag->name,
            ];
        })->toArray();
    }
}
