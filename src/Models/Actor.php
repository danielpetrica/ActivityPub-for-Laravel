<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $username
 * @property string|null $name
 * @property string|null $summary
 * @property string|null $icon_url
 * @property string|null $image_url
 * @property string|null $inbox_url
 * @property string|null $outbox_url
 * @property string|null $followers_url
 * @property string|null $following_url
 * @property string $public_key_pem
 * @property string $private_key_pem
 * @property bool $manually_approves_followers
 * @property \Carbon\CarbonInterface|null $last_active_at
 * @property bool $discoverable
 * @property \Carbon\CarbonInterface|null $published
 * @property array<int, string>|null $also_known_as
 * @property string|null $moved_to
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Actor extends Model implements ActorContract
{
    protected $fillable = [
        'username',
        'name',
        'summary',
        'icon_url',
        'image_url',
        'inbox_url',
        'outbox_url',
        'followers_url',
        'following_url',
        'public_key_pem',
        'private_key_pem',
        'manually_approves_followers',
        'last_active_at',
        'discoverable',
        'published',
        'also_known_as',
        'moved_to',
    ];

    protected function casts(): array
    {
        return [
            'private_key_pem' => 'encrypted',
            'public_key_pem' => 'string',
            'manually_approves_followers' => 'boolean',
            'last_active_at' => 'datetime',
            'discoverable' => 'boolean',
            'published' => 'datetime',
            'also_known_as' => 'array',
        ];
    }

    public function getPreferredUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->username;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getIconUrl(): ?string
    {
        return $this->icon_url;
    }

    public function getHeaderImageUrl(): ?string
    {
        return $this->image_url;
    }

    /**
     * The public profile URL for this actor.
     *
     * The actor IRI doubles as the content-negotiated profile document (an
     * HTML `Accept` header returns the human-readable page), so it is the
     * default value for both the actor `url` field and the WebFinger
     * `profile-page` link. Override to return a distinct HTML page URL.
     */
    public function getProfileUrl(): string
    {
        return $this->actor_id;
    }

    public function getActorId(): string
    {
        return $this->actor_id;
    }

    public function getInboxUrl(): string
    {
        return $this->inbox_url;
    }

    public function getOutboxUrl(): string
    {
        return $this->outbox_url;
    }

    public function getFollowersUrl(): string
    {
        return $this->followers_url;
    }

    public function getFollowingUrl(): string
    {
        return $this->following_url;
    }

    public function getPublicKey(): string
    {
        return $this->public_key_pem;
    }

    public function getKeyId(): string
    {
        return $this->key_id;
    }

    public function getPrivateKeyPem(): ?string
    {
        return $this->private_key_pem;
    }

    /**
     * ActivityStreams `alsoKnownAs`: the additional actor IRIs this account is
     * known by. Consumed by ActorResource automatically. Always a list of
     * non-empty strings.
     *
     * @return array<int, string>
     */
    public function getActivityPubAlsoKnownAs(): array
    {
        $value = $this->also_known_as;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (string $entry): bool => $entry !== '',
        ));
    }

    /**
     * Alias so consumer models exposing `getAlsoKnownAs()` keep working.
     *
     * @return array<int, string>
     */
    public function getAlsoKnownAs(): array
    {
        return $this->getActivityPubAlsoKnownAs();
    }

    public function getActivityPubMovedTo(): ?string
    {
        return is_string($this->moved_to) && $this->moved_to !== '' ? $this->moved_to : null;
    }

    public function getInboxUrlAttribute(?string $value): string
    {
        return $value ?? $this->buildUrl(suffix: '/inbox');
    }

    public function getOutboxUrlAttribute(?string $value): string
    {
        return $value ?? $this->buildUrl(suffix: '/outbox');
    }

    public function getFollowersUrlAttribute(?string $value): string
    {
        return $value ?? $this->buildUrl(suffix: '/followers');
    }

    public function getFollowingUrlAttribute(?string $value): string
    {
        return $value ?? $this->buildUrl(suffix: '/following');
    }

    public function getActorIdAttribute(): string
    {
        return $this->buildUrl();
    }

    public function getKeyIdAttribute(): string
    {
        return $this->actor_id.'#main-key';
    }

    protected function buildUrl(?string $suffix = null): string
    {
        $domain = rtrim(config('activitypub.domain'), '/');

        $url = $domain.'/users/'.$this->username;

        if ($suffix !== null) {
            $url .= $suffix;
        }

        return $url;
    }
}
