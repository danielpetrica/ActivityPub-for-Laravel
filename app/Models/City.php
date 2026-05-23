<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $province
 * @property string $region
 * @property bool $is_capital
 * @property string|null $description
 * @property float|null $latitude
 * @property float|null $longitude
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_capital' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
