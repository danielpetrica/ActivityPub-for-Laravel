<?php

namespace App\Http\Controllers\Api;

use App\Actions\RenderPostHtmlAction;
use App\Classes\Business\PostBusiness;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;

final class PageController extends Controller
{
    /**
     * Display the specified page.
     */
    public function show(string $slug): PageResource
    {
        $page = PostBusiness::findPublishedPageBySlugOrFail(slug: $slug);

        $page->rendered_html = RenderPostHtmlAction::execute($page);

        return new PageResource(resource: $page);
    }
}
