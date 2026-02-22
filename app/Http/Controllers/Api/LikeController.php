<?php

namespace App\Http\Controllers\Api;

use App\Models\Like;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LikeController
{
    /**
     * Store a like for a post.
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        // Simple prevention of multiple likes from same IP/Post in a short window
        $exists = Like::query()
            ->where(column: 'post_id', operator: '=', value: $post->id)
            ->where(column: 'ip_address', operator: '=', value: $request->ip())
            ->where(column: 'created_at', operator: '>=', value: now()->subDay())
            ->exists();

        if ($exists) {
            return response()->json(
                data: ['message' => 'You already liked this post today.'],
                status: 422
            );
        }

        $post->likes()->create(attributes: [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(data: [
            'message' => 'Post liked!',
            'likes_count' => $post->likes()->count(),
        ]);
    }
}
