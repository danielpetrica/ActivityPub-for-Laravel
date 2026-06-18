<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim($request->query(key: 'q', default: ''));

        if ($query === '') {
            return view(
                view: 'search',
                data: [
                    'query' => '',
                    'posts' => collect(),
                    'pages' => collect(),
                    'tags' => collect(),
                ]
            );
        }

        $posts = Post::search(search: $query)
            ->query(fn ($builder) => $builder->where(column: 'status', operator: '=', value: PostStatus::Published))
            ->get();

        $pages = Page::search(search: $query)
            ->query(fn ($builder) => $builder->where(column: 'status', operator: '=', value: PostStatus::Published))
            ->get();

        $tags = Tag::search(search: $query)->get();

        return view(
            view: 'search',
            data: [
                'query' => $query,
                'posts' => $posts,
                'pages' => $pages,
                'tags' => $tags,
            ]
        );
    }
}
