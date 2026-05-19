<?php

namespace App\Observers;

use App\Classes\Business\OgImageBusiness;
use App\Models\Tag;

final class TagObserver
{
    public function saved(Tag $tag): void
    {
        if ($tag->og_image_generated_at !== null) {
            OgImageBusiness::generateForTag(tag: $tag);
        }
    }

    public function deleted(Tag $tag): void
    {
        if ($tag->og_image) {
            OgImageBusiness::deleteOgImage(path: $tag->og_image);
        }
    }
}
