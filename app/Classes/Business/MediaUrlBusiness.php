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

    public static function fromS3Url(string $url): ?string
    {
        $disks = [
            'media' => config('filesystems.disks.hetzner'),
            'og-images' => config('filesystems.disks.og-images'),
        ];

        foreach ($disks as $diskRoute => $diskConfig) {
            $baseUrl = rtrim($diskConfig['endpoint'], '/')
                .'/'.$diskConfig['bucket']
                .'/'.$diskConfig['prefix'].'/';

            if (str_starts_with($url, $baseUrl)) {
                $relativePath = substr($url, strlen($baseUrl));

                return $diskRoute === 'media'
                    ? self::forMedia($relativePath)
                    : self::forOgImage($relativePath);
            }
        }

        return null;
    }
}
