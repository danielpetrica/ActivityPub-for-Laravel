<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $actor_url
 * @property string $inbox_url
 * @property string|null $public_key_pem
 * @property string $username
 * @property string $domain
 * @property string|null $name
 * @property string|null $icon_url
 * @property string|null $moved_to
 * @property array<int, string>|null $also_known_as
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Collection<int, Follower> $followers
 */
final class RemoteActor extends Model
{
    protected $table = 'remote_actors';

    protected $fillable = [
        'actor_url',
        'inbox_url',
        'shared_inbox_url',
        'public_key_pem',
        'username',
        'domain',
        'name',
        'icon_url',
        'moved_to',
        'also_known_as',
    ];

    protected $casts = [
        'public_key_pem' => 'encrypted',
        'also_known_as' => 'array',
    ];

    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class);
    }
}
