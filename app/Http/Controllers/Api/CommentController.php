<?php

namespace App\Http\Controllers\Api;

use App\Classes\Business\CommentBusiness;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

final class CommentController
{
    /**
     * Get approved comments for a post.
     */
    public function index(Post $post): JsonResponse
    {
        $comments = CommentBusiness::getApprovedForPost(post: $post);

        return response()->json(data: [
            'data' => $comments->map(fn ($comment) => [
                'id' => $comment->id,
                'author' => $comment->author_name ?? $comment->user?->name ?? 'Guest',
                'comment' => $comment->comment,
                'created_at' => $comment->created_at->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Store a new comment.
     */
    public function store(StoreCommentRequest $request): JsonResponse
    {
        $post = Post::findOrFail(id: $request->validated(key: 'post_id'));

        $comment = CommentBusiness::create(
            post: $post,
            data: array_merge($request->validated(), [
                'user_id' => $request->user()?->id,
            ])
        );

        return response()->json(
            data: [
                'message' => 'Comment submitted and awaiting moderation.',
                'data' => $comment,
            ],
            status: 201
        );
    }
}
