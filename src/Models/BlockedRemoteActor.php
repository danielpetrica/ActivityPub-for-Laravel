<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BlockedRemoteActor extends Model
{
    protected $fillable = [
        'remote_actor_id',
        'reason',
    ];

    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(RemoteActor::class);
    }
}
