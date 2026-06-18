<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $viewable_type
 * @property int $viewable_id
 * @property string|null $ip_address
 * @property string|null $referer
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $viewable
 */
final class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    protected $fillable = ['viewable_type', 'viewable_id', 'ip_address', 'referer', 'user_agent'];

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}
