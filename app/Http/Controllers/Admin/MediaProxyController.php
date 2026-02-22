<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Proxy for remote object storage to avoid CORS issues in the admin panel.
 */
final class MediaProxyController extends Controller
{
    /**
     * Stream the requested media file from the bucket.
     * Accessible only by authenticated administrators.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $rawPath = (string) $request->query(key: 'path', default: '');

        if ($rawPath === '') {
            abort(code: 404);
        }

        // Normalize and validate the path relative to the hetzner disk prefix.
        $prefix = rtrim((string) Config::get(key: 'filesystems.disks.hetzner.prefix', default: ''), '/');
        $path = ltrim($rawPath, '/');

        // If the incoming path already contains the prefix, strip it to avoid double-prefixing.
        if ($prefix !== '' && str_starts_with($path, $prefix.'/')) {
            $path = substr($path, strlen($prefix) + 1);
        }

        // Prevent directory traversal and restrict to known media roots.
        if (str_contains($path, '..') || (! str_starts_with($path, 'media/') && ! str_starts_with($path, 'posts/'))) {
            abort(code: 404);
        }

        $disk = Storage::disk(name: 'hetzner');

        if (! $disk->exists(path: $path)) {
            abort(code: 404);
        }

        $mime = $disk->mimeType(path: $path) ?: 'application/octet-stream';
        $size = $disk->size(path: $path) ?: null;

        // Support HEAD by returning only headers.
        if ($request->isMethod(method: 'head')) {
            return response()->stream(
                callback: static function (): void {
                    // no body for HEAD
                },
                status: 200,
                headers: array_filter([
                    'Content-Type' => $mime,
                    'Content-Length' => $size,
                    'Cache-Control' => 'private, max-age=3600',
                    'Content-Disposition' => 'inline; filename="'.basename($path).'"',
                ])
            );
        }

        $stream = $disk->readStream(path: $path);

        return response()->stream(
            callback: static function () use ($stream): void {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            status: 200,
            headers: array_filter([
                'Content-Type' => $mime,
                'Content-Length' => $size,
                'Cache-Control' => 'private, max-age=3600',
                'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            ])
        );
    }
}
