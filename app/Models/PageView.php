<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $viewable_type
 * @property int $viewable_id
 * @property string|null $ip_address
 * @property string|null $referer
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Model $viewable
 */
class PageView extends Model
{
    /** @use HasFactory<\Database\Factories\PageViewFactory> */
    use HasFactory;

    protected $guarded = [];

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}
