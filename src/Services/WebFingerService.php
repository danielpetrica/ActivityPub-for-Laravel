<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WebFingerService
{
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
            return null;
        }

        return Cache::remember(
            key: md5($url),
            ttl: 300,
            callback: function () use ($url): ?array {
                try {
                    $response = Http::timeout(
                        seconds: config('activitypub.federation.delivery_timeout', 10),
                    )->get(url: $url);

                    if (! $response->successful()) {
                        return null;
                    }

                    $data = $response->json();

                    if ($data === null || ! isset($data['links'])) {
                        return null;
                    }

                    foreach ($data['links'] as $link) {
                        if (isset($link['type'], $link['href'])
                            && $link['type'] === 'application/activity+json') {
                            return $link;
                        }
                    }

                    return null;
                } catch (\Exception $e) {
                    Log::debug(
                        message: 'WebFingerService: Request failed',
                        context: [
                            'url' => $url,
                            'error' => $e->getMessage(),
                        ],
                    );

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

        if ($ip === $host) {
            return false;
        }

        if (filter_var(value: $ip, filter: FILTER_VALIDATE_IP, options: FILTER_FLAG_IPV6)) {
            if ($ip === '::1') {
                Log::debug('WebFingerService: private IP blocked', ['url' => $url, 'ip' => $ip]);

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
            $first === 127
            || $first === 10
            || ($first === 172 && $second >= 16 && $second <= 31)
            || ($first === 192 && $second === 168)
        );

        if ($isPrivate) {
            Log::debug('WebFingerService: private IP blocked', ['url' => $url, 'ip' => $ip]);

            return true;
        }

        return false;
    }
}
