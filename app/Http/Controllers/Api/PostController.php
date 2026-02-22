<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertPostToMarkdownAction;
use App\Actions\RenderPostHtmlAction;
use App\Classes\Business\PostBusiness;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PostController extends Controller
{
    /**
     * Display the specified post.
     */
    public function show(string $slug, Request $request): PostResource|Response
    {
        $post = PostBusiness::findPublishedBySlugOrFail(slug: $slug);

        if ($request->header('Accept') === 'text/markdown' || $request->is('*.md') || $request->query('format') === 'markdown') {
            return response(
                content: ConvertPostToMarkdownAction::execute($post),
                status: 200,
                headers: ['Content-Type' => 'text/markdown']
            );
        }

        $post->rendered_html = RenderPostHtmlAction::execute($post);

        return new PostResource(resource: $post);
    }
}
