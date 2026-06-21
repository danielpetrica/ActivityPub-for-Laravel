<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class RemoteActorResolver
{
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

        $remoteActor = RemoteActor::query()->firstOrCreate(
            attributes: ['actor_url' => $actorUrl],
            values: [
                'inbox_url' => $actorUrl.'/inbox',
                'username' => basename($actorUrl),
                'domain' => parse_url(url: $actorUrl, component: PHP_URL_HOST) ?: 'unknown',
            ],
        );

        if ($remoteActor->wasRecentlyCreated) {
            FetchRemoteActor::dispatch(actorUri: $actorUrl);
        }

        return $remoteActor;
    }

    public function fetchActorData(string $actorUri): ?array
    {
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
}
