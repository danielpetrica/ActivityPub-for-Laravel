<?php

namespace App\Observers;

use App\Models\Link;
use Illuminate\Support\Facades\Cache;

final class LinkObserver
{
    public function created(Link $link): void
    {
        $this->clearCache($link);
    }

    public function updated(Link $link): void
    {
        $this->clearCache($link);
    }

    public function deleted(Link $link): void
    {
        $this->clearCache($link);
    }

    protected function clearCache(Link $link): void
    {
        Cache::forget("links.{$link->position->value}");
    }
}
