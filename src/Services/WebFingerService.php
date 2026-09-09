<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Traits\LogsActivityPub;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class WebFingerService
{
    use LogsActivityPub;
    public function resolve(string $resource): ?array
    {
        if (str_starts_with(haystack: $resource, needle: 'acct:')) {
            $parts = explode(separator: '@', string: substr(string: $resource, offset: 5));
            $domain = $parts[1] ?? null;

            if ($domain === null) {
                return null;
            }

            $url = 'https://'.$domain.'/.well-known/webfinger?resource='.urlencode(string: $resource);

            return $this->fetch(url: $url);
        }

        $parsed = parse_url(url: $resource);

        if (! isset($parsed['host'])) {
            return null;
        }

        $domain = $parsed['host'];
        $url = 'https://'.$domain.'/.well-known/webfinger?resource='.urlencode(string: $resource);

        return $this->fetch(url: $url);
    }

    protected function fetch(string $url): ?array
    {
        if ($this->isPrivateDomain(url: $url)) {
            $this->activityPubLog('warning', 'WebFinger private domain blocked', ['url' => $url]);

            return null;
        }

        return Cache::remember(
            key: md5($url),
            ttl: 300,
            callback: function () use ($url): ?array {
                try {
                    $this->activityPubLog('info', 'WebFinger lookup', ['url' => $url]);

                    $response = Http::timeout(
                        seconds: config('activitypub.federation.resolve_timeout', 5),
                    )->get(url: $url);

                    if (! $response->successful()) {
                        $this->activityPubLog('warning', 'WebFinger HTTP request failed', [
                            'url' => $url,
                            'statusCode' => $response->status(),
                            'responseBody' => mb_strcut($response->body(), 0, 500),
                        ]);

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

    protected function isPrivateDomain(string $url): bool
    {
        $host = parse_url(url: $url, component: PHP_URL_HOST);

        if ($host === null) {
            return true;
        }

        $ip = gethostbyname(hostname: $host);

        // If DNS resolution fails (returns hostname unchanged), block the request
        if ($ip === $host) {
            return true;
        }

        if (filter_var(value: $ip, filter: FILTER_VALIDATE_IP, options: FILTER_FLAG_IPV6)) {
            if ($ip === '::1') {
                $this->activityPubLog('warning', 'WebFinger private IPv6 loopback blocked', ['url' => $url, 'ip' => $ip]);

                return true;
            }

            return false;
        }

        $parts = explode(separator: '.', string: $ip);

        if (count($parts) !== 4) {
            return false;
        }

        $first = (int) $parts[0];
        $second = (int) $parts[1];

        $isPrivate = (
            $first === 0
            || $first === 10
            || $first === 127
            || ($first === 169 && $second === 254)
            || ($first === 172 && $second >= 16 && $second <= 31)
            || ($first === 192 && $second === 168)
        );

        if ($isPrivate) {
            $this->activityPubLog('warning', 'WebFinger private IP blocked', ['url' => $url, 'ip' => $ip]);

            return true;
        }

        return false;
    }
}
