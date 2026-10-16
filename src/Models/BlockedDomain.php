<?php

namespace DanielPetrica\LaravelActivityPub\Models;

use DanielPetrica\LaravelActivityPub\Helpers\DomainNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

final class BlockedDomain extends Model
{
    protected $fillable = [
        'domain',
        'reason',
    ];

    /**
     * Normalize the domain on write so lookups cannot be bypassed by casing,
     * trailing dots or Unicode/Punycode differences.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function domain(): Attribute
    {
        return Attribute::make(
            null,
            static fn (?string $value): ?string => DomainNormalizer::normalize($value),
        );
    }
}
