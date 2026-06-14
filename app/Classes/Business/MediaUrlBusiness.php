<?php

namespace App\Classes\Business;

final class MediaUrlBusiness
{
    public static function forMedia(string $path): string
    {
        return '/objectproxy/media/'.ltrim($path, '/');
    }

    public static function forOgImage(string $path): string
    {
        return '/objectproxy/og-images/'.ltrim($path, '/');
    }
}
