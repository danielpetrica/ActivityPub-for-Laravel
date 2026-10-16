<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Helpers\SsrfGuard;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Traits\LogsActivityPub;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class RemoteActorResolver
{
    use LogsActivityPub;

    private array $cache = [];

    /** Cache failed resolutions for 1 hour to avoid spamming dead actor URLs. */
    private const FAILURE_CACHE_TTL = 3600;

    public function __construct(
        private HttpSignatureService $httpSignatureService,
    ) {}

    public function resolve(string $actorUri, ?array $preFetchedData = null): ?RemoteActor
    {
        $data = $preFetchedData;

        if ($data === null) {
            // Check if we recently failed to resolve this actor (e.g. 410 Gone)
            $failureKey = 'actor-resolve-fail:'.md5($actorUri);
            if (Cache::has($failureKey)) {
                $this->activityPubLog('info', 'Skipping recently failed actor resolution', ['actorUri' => $actorUri]);

                return null;
            }

            $this->activityPubLog('info', 'Resolving remote actor', ['actorUri' => $actorUri]);

            $data = $this->fetchActorData(actorUri: $actorUri);

            if ($data === null) {
                $this->activityPubLog('warning', 'Could not resolve remote actor', ['actorUri' => $actorUri]);

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
            ['actor_url' => $actorUrl],
            [
                'inbox_url' => $inboxUrl,
                'username' => basename($actorUrl),
                'domain' => parse_url($actorUrl, PHP_URL_HOST) ?: 'unknown',
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
        [$safe, $pinOptions] = SsrfGuard::validateAndPin(url: $actorUri);

        if (! $safe) {
            $this->activityPubLog('warning', 'Unsafe remote actor URL blocked', ['actorUri' => $actorUri]);

            return null;
        }

        try {
            $headers = ['Accept' => 'application/activity+json'];

            $localActor = $this->resolveLocalActor();

            if ($localActor !== null) {
                $headers = $this->httpSignatureService->sign(
                    method: 'GET',
                    url: $actorUri,
                    headers: $headers,
                    actor: $localActor,
                );
            }

            $response = Http::timeout(
                config('activitypub.federation.resolve_timeout', 5),
            )->withHeaders($headers)
                ->withOptions(array_merge(['allow_redirects' => false], $pinOptions))
                ->get($actorUri);

            if (! $response->successful()) {
                $statusCode = $response->status();

                $this->activityPubLog('warning', 'Remote actor HTTP fetch failed', [
                    'actorUri' => $actorUri,
                    'statusCode' => $statusCode,
                    'responseBody' => mb_strcut($response->body(), 0, 500),
                ]);

                // Cache 410 (Gone) and 404 (Not Found) to avoid repeated fetches
                // for deleted or fake accounts
                if ($statusCode === 410 || $statusCode === 404) {
                    $failureKey = 'actor-resolve-fail:'.md5($actorUri);
                    Cache::put($failureKey, true, now()->addSeconds(self::FAILURE_CACHE_TTL));
                }

                return null;
            }

            if (! $this->responseWithinLimits(response: $response)) {
                return null;
            }

            $data = $response->json();

            if ($data === null || $data === []) {
                $this->activityPubLog('warning', 'Remote actor returned empty or invalid data', [
                    'actorUri' => $actorUri,
                    'responseBody' => mb_strcut($response->body(), 0, 500),
                ]);

                return null;
            }

            $this->activityPubLog('info', 'Remote actor fetched successfully', [
                'actorUri' => $actorUri,
                'username' => $data['preferredUsername'] ?? 'unknown',
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->activityPubLog('warning', 'Remote actor fetch exception', [
                'actorUri' => $actorUri,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertFromData(string $actorUri, array $data): RemoteActor
    {
        $parts = parse_url($actorUri);
        $domain = $parts['host'] ?? 'unknown';
        $username = $data['preferredUsername'] ?? 'unknown';

        return RemoteActor::query()->updateOrCreate(
            ['actor_url' => $actorUri],
            [
                'inbox_url' => $data['inbox'] ?? $actorUri.'/inbox',
                'shared_inbox_url' => $data['endpoints']['sharedInbox'] ?? null,
                'public_key_pem' => $this->extractPublicKeyPem(data: $data),
                'username' => $username,
                'domain' => $domain,
                'name' => $data['name'] ?? null,
                'icon_url' => $data['icon']['url'] ?? null,
            ],
        );
    }

    /**
     * Extract a PEM public key from the various ActivityPub key shapes.
     *
     * Supports the classic `publicKey.publicKeyPem`, a top-level
     * `publicKeyPem`, and `assertionMethod` (single object, list, or URL
     * reference) including Multikey `publicKeyMultibase` values that carry a
     * PEM-encoded key.
     *
     * @param  array<string, mixed>  $data
     */
    protected function extractPublicKeyPem(array $data): ?string
    {
        $candidates = [
            $data['publicKey']['publicKeyPem'] ?? null,
            $data['publicKeyPem'] ?? null,
        ];

        if (isset($data['publicKey']['publicKeyMultibase']) && is_string($data['publicKey']['publicKeyMultibase'])) {
            $candidates[] = $this->multibaseToPem(value: $data['publicKey']['publicKeyMultibase']);
        }

        if (isset($data['publicKeyMultibase']) && is_string($data['publicKeyMultibase'])) {
            $candidates[] = $this->multibaseToPem(value: $data['publicKeyMultibase']);
        }

        $assertionMethod = $data['assertionMethod'] ?? null;

        if (is_array($assertionMethod)) {
            if (isset($assertionMethod['publicKeyPem']) && is_string($assertionMethod['publicKeyPem'])) {
                $candidates[] = $assertionMethod['publicKeyPem'];
            }

            if (isset($assertionMethod['publicKeyMultibase']) && is_string($assertionMethod['publicKeyMultibase'])) {
                $candidates[] = $this->multibaseToPem(value: $assertionMethod['publicKeyMultibase']);
            }

            foreach ($assertionMethod as $method) {
                if (! is_array($method)) {
                    continue;
                }

                if (isset($method['publicKeyPem']) && is_string($method['publicKeyPem'])) {
                    $candidates[] = $method['publicKeyPem'];
                }

                if (isset($method['publicKeyMultibase']) && is_string($method['publicKeyMultibase'])) {
                    $candidates[] = $this->multibaseToPem(value: $method['publicKeyMultibase']);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Decode a Multibase value that carries a PEM-encoded public key.
     */
    protected function multibaseToPem(string $value): ?string
    {
        // Multibase base64url encoding is prefixed with 'u'.
        if (! str_starts_with($value, 'u')) {
            return null;
        }

        $base64 = strtr(substr($value, 1), '-_', '+/');
        $base64 .= str_repeat('=', (4 - (strlen($base64) % 4)) % 4);

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            return null;
        }

        if (str_contains($decoded, 'BEGIN PUBLIC KEY') || str_contains($decoded, 'BEGIN RSA PUBLIC KEY')) {
            return $decoded;
        }

        return null;
    }

    protected function fetchInboxUrl(string $actorUrl): ?string
    {
        $data = $this->fetchActorData(actorUri: $actorUrl);

        if ($data === null) {
            return null;
        }

        return $data['inbox'] ?? null;
    }

    /**
     * Enforce size and content-type caps on a remote fetch response.
     */
    protected function responseWithinLimits(\Illuminate\Http\Client\Response $response): bool
    {
        $body = $response->body();
        $contentLength = $response->header('Content-Length');
        $declaredBytes = $contentLength !== '' ? (int) $contentLength : null;

        if (SsrfGuard::responseTooLarge(bodyBytes: strlen($body), declaredBytes: $declaredBytes)) {
            $this->activityPubLog('warning', 'Remote actor response exceeds size limit', [
                'bytes' => strlen($body),
                'declaredBytes' => $declaredBytes,
            ]);

            return false;
        }

        if (! SsrfGuard::contentTypeAllowed($response->header('Content-Type'), 'json')) {
            $this->activityPubLog('warning', 'Remote actor response has unsupported content-type', [
                'contentType' => $response->header('Content-Type'),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Resolve a local actor to sign outgoing federation requests with.
     * Prefers the currently authenticated user's actor, falling back to the
     * first actor that holds a private key (e.g. when running in a queued job).
     */
    protected function resolveLocalActor(): ?Actor
    {
        $user = auth()->user();

        if ($user instanceof ActorContract) {
            $actor = Actor::query()
                ->where('username', '=', $user->getPreferredUsername())
                ->first();

            if ($actor !== null && $actor->private_key_pem !== null) {
                return $actor;
            }
        }

        return Actor::query()
            ->whereNotNull('private_key_pem')
            ->first();
    }
}
