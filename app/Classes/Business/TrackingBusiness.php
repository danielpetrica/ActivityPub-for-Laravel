<?php

namespace App\Classes\Business;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Model;

final class TrackingBusiness
{
    /**
     * Record a page view for a given model.
     */
    public static function recordView(
        Model $viewable,
        ?string $ipAddress = null,
        ?string $referer = null,
        ?string $userAgent = null
    ): PageView {
        return PageView::query()->create(attributes: [
            'viewable_id' => $viewable->getKey(),
            'viewable_type' => $viewable->getMorphClass(),
            'ip_address' => $ipAddress,
            'referer' => $referer,
            'user_agent' => $userAgent,
        ]);
    }
}
