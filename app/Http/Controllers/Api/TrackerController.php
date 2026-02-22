<?php

namespace App\Http\Controllers\Api;

use App\Classes\Business\TrackingBusiness;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tool;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrackerController
{
    /**
     * Track view and return a transparent pixel.
     */
    public function track(Request $request): Response
    {
        $type = $request->query(key: 'type'); // 'post', 'page', 'tool'
        $slug = $request->query(key: 'slug');

        if ($type && $slug) {
            $model = match ($type) {
                'post' => Post::where('slug', $slug)->first(),
                'page' => Page::where('slug', $slug)->first(),
                'tool' => Tool::where('slug', $slug)->first(),
                default => null,
            };

            if ($model) {
                TrackingBusiness::recordView(
                    viewable: $model,
                    ipAddress: $request->ip(),
                    referer: $request->header('referer'),
                    userAgent: $request->userAgent()
                );
            }
        }

        // Return a 1x1 transparent GIF
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
