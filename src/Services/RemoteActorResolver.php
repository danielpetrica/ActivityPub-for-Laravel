<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class RemoteActorResolver
{
    private array $cache = [];

    public function resolve(string $actorUri, ?array $preFetchedData = null): ?RemoteActor
    {
        $data = $preFetchedData;

        if ($data === null) {
            $data = $this->fetchActorData(actorUri: $actorUri);

            if ($data === null) {
                return null;
            }
        }

        return $this->upsertFromData(actorUri: $actorUri, data: $data);
    }

    public function resolveFromPayload(array $payload): ?RemoteActor
    {
        $actorUrl = $payload['actor'] ?? null;

        if (! is_string($actorUrl)) {
            return null;
        }

        if (isset($this->cache[$actorUrl])) {
            return $this->cache[$actorUrl];
        }

        $inboxUrl = $this->fetchInboxUrl(actorUrl: $actorUrl) ?? $actorUrl.'/inbox';

        $remoteActor = RemoteActor::query()->firstOrCreate(
            attributes: ['actor_url' => $actorUrl],
            values: [
                'inbox_url' => $inboxUrl,
                'username' => basename($actorUrl),
                'domain' => parse_url(url: $actorUrl, component: PHP_URL_HOST) ?: 'unknown',
            ],
        );

        if ($remoteActor->wasRecentlyCreated) {
            FetchRemoteActor::dispatch(actorUri: $actorUrl);
        }

        $this->cache[$actorUrl] = $remoteActor;

        return $remoteActor;
    }

    public function fetchActorData(string $actorUri): ?array
    {
        if ($this->isPrivateDomain(url: $actorUri)) {
            return null;
        }

        try {
            $response = Http::timeout(
                seconds: config('activitypub.federation.delivery_timeout', 10),
            )->withHeaders(['Accept' => 'application/activity+json'])
                ->get(url: $actorUri);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if ($data === null || $data === []) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::debug('RemoteActorResolver: fetch failed', [
                'actorUri' => $actorUri,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function upsertFromData(string $actorUri, array $data): RemoteActor
    {
        $parts = parse_url(url: $actorUri);
        $domain = $parts['host'] ?? 'unknown';
        $username = $data['preferredUsername'] ?? 'unknown';

        return RemoteActor::query()->updateOrCreate(
            attributes: ['actor_url' => $actorUri],
            values: [
                'inbox_url' => $data['inbox'] ?? $actorUri.'/inbox',
                'public_key_pem' => $data['publicKey']['publicKeyPem'] ?? null,
                'username' => $username,
                'domain' => $domain,
                'name' => $data['name'] ?? null,
                'icon_url' => $data['icon']['url'] ?? null,
            ],
        );
    }

    protected function fetchInboxUrl(string $actorUrl): ?string
    {
        $data = $this->fetchActorData(actorUri: $actorUrl);

        if ($data === null) {
            return null;
        }

        return $data['inbox'] ?? null;
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
                Log::debug('RemoteActorResolver: private IP blocked', ['url' => $url, 'ip' => $ip]);

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
            Log::debug('RemoteActorResolver: private IP blocked', ['url' => $url, 'ip' => $ip]);

            return true;
        }

        return false;
    }
}
