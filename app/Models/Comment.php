<?php

namespace App\Models;

use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property int|null $remote_actor_id
 * @property string|null $remote_activity_id
 * @property string|null $author_name
 * @property string $comment
 * @property bool $is_approved
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Post $post
 * @property-read User|null $user
 * @property-read RemoteActor|null $remoteActor
 */
final class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $fillable = ['post_id', 'user_id', 'remote_actor_id', 'remote_activity_id', 'author_name', 'comment', 'is_approved'];

    protected $casts = [
        'is_approved' => 'boolean',
        'remote_actor_id' => 'integer',
        'remote_activity_id' => 'string',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(related: Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }

    public function remoteActor(): BelongsTo
    {
        return $this->belongsTo(related: RemoteActor::class);
    }
}
