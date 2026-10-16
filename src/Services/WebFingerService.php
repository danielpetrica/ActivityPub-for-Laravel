<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Helpers\SsrfGuard;
use DanielPetrica\LaravelActivityPub\Traits\LogsActivityPub;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class WebFingerService
{
    use LogsActivityPub;

    public function resolve(string $resource): ?array
    {
        if (str_starts_with($resource, 'acct:')) {
            $parts = explode('@', substr($resource, 5));
            $domain = $parts[1] ?? null;

            if ($domain === null) {
                return null;
            }

            $url = 'https://'.$domain.'/.well-known/webfinger?resource='.urlencode($resource);

            return $this->fetch(url: $url);
        }

        $parsed = parse_url($resource);

        if (! isset($parsed['host'])) {
            return null;
        }

        $domain = $parsed['host'];
        $url = 'https://'.$domain.'/.well-known/webfinger?resource='.urlencode($resource);

        return $this->fetch(url: $url);
    }

    protected function fetch(string $url): ?array
    {
        [$safe, $pinOptions] = SsrfGuard::validateAndPin(url: $url);

        if (! $safe) {
            $this->activityPubLog('warning', 'WebFinger unsafe URL blocked', ['url' => $url]);

            return null;
        }

        return Cache::remember(
            md5($url),
            300,
            function () use ($url, $pinOptions): ?array {
                try {
                    $this->activityPubLog('info', 'WebFinger lookup', ['url' => $url]);

                    $response = Http::timeout(
                        config('activitypub.federation.resolve_timeout', 5),
                    )->withOptions(array_merge(['allow_redirects' => false], $pinOptions))
                        ->get($url);

                    if (! $response->successful()) {
                        $this->activityPubLog('warning', 'WebFinger HTTP request failed', [
                            'url' => $url,
                            'statusCode' => $response->status(),
                            'responseBody' => mb_strcut($response->body(), 0, 500),
                        ]);

                        return null;
                    }

                    if (! $this->responseWithinLimits(response: $response)) {
                        return null;
                    }

                    $data = $response->json();

                    if ($data === null || ! isset($data['links'])) {
                        $this->activityPubLog('warning', 'WebFinger response missing links', [
                            'url' => $url,
                            'responseBody' => mb_strcut($response->body(), 0, 500),
                        ]);

                        return null;
                    }

                    foreach ($data['links'] as $link) {
                        if (isset($link['type'], $link['href'])
                            && $link['type'] === 'application/activity+json') {
                            $this->activityPubLog('info', 'WebFinger resolved', ['url' => $url, 'href' => $link['href']]);

                            return $link;
                        }
                    }

                    $this->activityPubLog('warning', 'WebFinger no activity+json link found', [
                        'url' => $url,
                        'links' => $data['links'],
                    ]);

                    return null;
                } catch (\Exception $e) {
                    $this->activityPubLog('warning', 'WebFinger request exception', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]);

                    return null;
                }
            },
        );
    }

    /**
     * Enforce size and content-type caps on a remote fetch response.
     */
    protected function responseWithinLimits(Response $response): bool
    {
        $body = $response->body();
        $contentLength = $response->header('Content-Length');
        $declaredBytes = $contentLength !== '' ? (int) $contentLength : null;

        if (SsrfGuard::responseTooLarge(bodyBytes: strlen($body), declaredBytes: $declaredBytes)) {
            $this->activityPubLog('warning', 'WebFinger response exceeds size limit', [
                'url' => $response->effectiveUri(),
                'bytes' => strlen($body),
                'declaredBytes' => $declaredBytes,
            ]);

            return false;
        }

        if (! SsrfGuard::contentTypeAllowed($response->header('Content-Type'), 'json')) {
            $this->activityPubLog('warning', 'WebFinger response has unsupported content-type', [
                'contentType' => $response->header('Content-Type'),
            ]);

            return false;
        }

        return true;
    }
}
