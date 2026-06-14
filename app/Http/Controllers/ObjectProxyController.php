<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ObjectProxyController extends Controller
{
    private const array DISK_MAP = [
        'media' => 'hetzner',
        'og-images' => 'og-images',
    ];

    private const int CACHE_MAX_AGE = 86400; // 24 hours

    public function __invoke(Request $request, string $disk, string $path = ''): StreamedResponse
    {
        $path = ltrim(string: $path, characters: '/');

        if ($path === '' || str_contains(haystack: $path, needle: '..')) {
            abort(code: 404);
        }

        $diskName = self::DISK_MAP[$disk] ?? null;

        if ($diskName === null) {
            abort(code: 404);
        }

        $storage = Storage::disk(name: $diskName);

        if (! $storage->exists(path: $path)) {
            abort(code: 404);
        }

        $mime = $storage->mimeType(path: $path) ?: 'application/octet-stream';
        $size = $storage->size(path: $path) ?: null;

        $headers = array_filter([
            'Content-Type' => $mime,
            'Content-Length' => $size,
        ]);

        if ($request->isMethod(method: 'head')) {
            $response = response()->stream(
                callback: static function (): void {},
                status: 200,
                headers: $headers,
            );
        } else {
            $stream = $storage->readStream(path: $path);

            $response = response()->stream(
                callback: static function () use ($stream): void {
                    if (is_resource(value: $stream)) {
                        fpassthru(stream: $stream);
                        fclose(stream: $stream);
                    }
                },
                status: 200,
                headers: $headers,
            );
        }

        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);

        return $response;
    }
}
