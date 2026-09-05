<?php

namespace App\Classes\Business;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

final class CommentBusiness
{
    /**
     * Create a new comment for a post.
     */
    public static function create(Post $post, array $data): Comment
    {
        $comment = $post->comments()->create(attributes: [
            'user_id' => $data['user_id'] ?? null,
            'author_name' => $data['author_name'] ?? null,
            'email' => $data['email'] ?? null,
            'subscribe_to_updates' => $data['subscribe_to_updates'] ?? false,
            'comment' => $data['comment'],
            'is_approved' => false, // Always moderated
        ]);

        if ($comment->subscribe_to_updates && $comment->email) {
            NewsletterBusiness::subscribe(email: $comment->email);
        }

        return $comment;
    }

    /**
     * Get approved comments for a post.
     */
    public static function getApprovedForPost(Post $post): Collection
    {
        return $post->comments()
            ->with(relations: 'user')
            ->where(column: 'is_approved', operator: '=', value: true)
            ->oldest()
            ->get();
    }
}
