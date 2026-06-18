<?php

namespace App\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property string $destination_url
 * @property int $status_code
 * @property bool $is_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected $fillable = ['path', 'destination_url', 'status_code', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
