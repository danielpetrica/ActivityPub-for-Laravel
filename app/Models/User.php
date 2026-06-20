<?php

namespace App\Models;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
// use Illuminate\Contracts\Auth\MustVerifyEmail;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable implements ActorContract, FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@danielpetrica.com') && $this->hasVerifiedEmail();
    }

    public function getPreferredUsername(): string
    {
        return $this->username ?? strstr(haystack: $this->email, needle: '@', before_needle: true);
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->getPreferredUsername();
    }

    public function getSummary(): ?string
    {
        return null;
    }

    public function getIconUrl(): ?string
    {
        return null;
    }

    public function getHeaderImageUrl(): ?string
    {
        return null;
    }

    public function getActorId(): string
    {
        $domain = rtrim(string: config('activitypub.domain'), characters: '/');

        return $domain.'/users/'.$this->getPreferredUsername();
    }

    public function getInboxUrl(): string
    {
        return $this->getActorId().'/inbox';
    }

    public function getOutboxUrl(): string
    {
        return $this->getActorId().'/outbox';
    }

    public function getFollowersUrl(): string
    {
        return $this->getActorId().'/followers';
    }

    public function getFollowingUrl(): string
    {
        return $this->getActorId().'/following';
    }

    public function getPublicKey(): string
    {
        $actor = Actor::query()
            ->where(column: 'username', operator: '=', value: $this->getPreferredUsername())
            ->first();

        return $actor?->public_key_pem ?? '';
    }

    public function getKeyId(): string
    {
        return $this->getActorId().'#main-key';
    }
}
