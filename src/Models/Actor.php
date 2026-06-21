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
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
final class Actor extends Model implements ActorContract
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
    ];

    protected function casts(): array
    {
        return [
            'private_key_pem' => 'encrypted',
            'public_key_pem' => 'string',
            'manually_approves_followers' => 'boolean',
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
        $domain = rtrim(string: config('activitypub.domain'), characters: '/');

        $url = $domain.'/users/'.$this->username;

        if ($suffix !== null) {
            $url .= $suffix;
        }

        return $url;
    }
}
