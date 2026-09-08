<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use Illuminate\Database\Eloquent\Model;

final class BlockedDomain extends Model
{
    protected $fillable = [
        'domain',
        'reason',
    ];
}
