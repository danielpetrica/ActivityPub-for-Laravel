<?php

namespace DanielPetrica\LaravelActivityPub\Http\Middleware;

use Closure;
use DanielPetrica\LaravelActivityPub\Helpers\InboxLogger;
use DanielPetrica\LaravelActivityPub\Http\Signatures\SignatureParser;
use DanielPetrica\LaravelActivityPub\Http\Signatures\SignatureVerifier;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyHttpSignature
{
    public function __construct(
        private SignatureParser $parser,
        private SignatureVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('activitypub.http_signatures.enabled')) {
            return $next($request);
        }

        // RFC 9421: uses Signature-Input + Signature headers (lowercase)
        $signatureInputHeader = $request->header('signature-input');

        if ($signatureInputHeader !== null) {
            return $this->handleRfc9421(request: $request, next: $next, signatureInputHeader: $signatureInputHeader);
        }

        return $this->handleDraftCavage(request: $request, next: $next);
    }

    // ──────────────────────────────────────────────────────────────
    // Draft-cavage HTTP Signatures (legacy / Mastodon < 4.7)
    // ──────────────────────────────────────────────────────────────

    protected function handleDraftCavage(Request $request, Closure $next): Response
    {
        // Draft-cavage: uses Signature header with keyId="..." format
        $signatureHeader = $request->header('signature');

        if ($signatureHeader === null) {
            return $this->reject(request: $request, error: 'Signature header required.');
        }

        $parsed = $this->parser->parseDraftCavageHeader(header: $signatureHeader);

        if ($parsed === null) {
            return $this->reject(request: $request, error: 'Invalid Signature header format.');
        }

        $bodyContent = $request->getContent();
        $method = strtolower($request->method());
        $isNonEmptyPost = $method === 'post' && $bodyContent !== '';

        $signedComponents = $this->parser->signedComponentNames(headers: $parsed['headers']);

        if (! in_array('(request-target)', $signedComponents, true)
            || ! in_array('host', $signedComponents, true)) {
            return $this->reject(
                request: $request,
                error: 'Signature must cover the (request-target) and host components.',
            );
        }

        if ($isNonEmptyPost && ! in_array('digest', $signedComponents, true)) {
            return $this->reject(
                request: $request,
                error: 'Signature must cover the digest component for POST requests.',
            );
        }

        $keyId = $parsed['keyId'];

        $dateHeader = $request->header('date');

        if ($dateHeader === null) {
            return response()->json(
                ['error' => 'Date header is required.'],
                401,
            );
        }

        $requestTime = strtotime($dateHeader);

        if ($requestTime === false || abs($requestTime - now()->timestamp) > config('activitypub.http_signatures.max_clock_skew', 120)) {
            return response()->json(
                ['error' => 'Request date is outside the allowed clock skew.'],
                401,
            );
        }

        $digestHeader = $request->header('digest');

        if ($isNonEmptyPost && $digestHeader === null) {
            return response()->json(
                ['error' => 'Digest header is required for POST requests.'],
                401,
            );
        }

        if ($digestHeader !== null && $bodyContent !== '') {
            $computedDigest = 'SHA-256='.base64_encode(hash(
                'sha256',
                $bodyContent,
                true,
            ));

            $normalizedDigest = preg_replace_callback(
                '/^([a-z][a-z0-9]*)=/i',
                fn (array $m): string => strtoupper($m[1]).'=',
                $digestHeader,
            );

            if (! hash_equals((string) $normalizedDigest, $computedDigest)) {
                return $this->reject(request: $request, error: 'Digest header does not match body.');
            }
        }

        $actorUrl = str_replace('#main-key', '', $keyId);

        // Validate keyId uses HTTPS — prevents downgrade attacks
        if (parse_url($actorUrl, PHP_URL_SCHEME) !== 'https') {
            return response()->json(
                ['error' => 'keyId must use HTTPS.'],
                401,
            );
        }

        $remoteActor = $this->resolveRemoteActor(actorUrl: $actorUrl);

        if ($remoteActor === null) {
            return $this->reject(request: $request, error: 'Unknown remote actor.');
        }

        $verified = $this->verifier->verifyDraftCavageSignature(
            request: $request,
            parsed: $parsed,
            remoteActor: $remoteActor,
        );

        if (! $verified) {
            return $this->reject(request: $request, error: 'Signature verification failed.');
        }

        // Only consume the replay slot once the signature has actually been
        // verified. A request rejected earlier (unknown actor, bad digest,
        // unverifiable signature) must not be able to burn the nonce for a
        // later, legitimate byte-identical retry within the TTL.
        $cacheKey = 'sig-replay:'.md5($keyId.'|'.$signatureHeader.'|'.$dateHeader);

        if (! Cache::add($cacheKey, true, 120)) {
            return response()->json(
                ['error' => 'Duplicate request detected.'],
                401,
            );
        }

        $request->attributes->set('remote_actor', $remoteActor);

        return $next($request);
    }

    // ──────────────────────────────────────────────────────────────
    // RFC 9421 HTTP Message Signatures
    // ──────────────────────────────────────────────────────────────

    protected function handleRfc9421(Request $request, Closure $next, string $signatureInputHeader): Response
    {
        $entries = $this->parser->parseRfc9421SignatureInput(header: $signatureInputHeader);

        if ($entries === null) {
            return $this->reject(request: $request, error: 'Invalid Signature-Input header format.');
        }

        // Get signature values from the Signature header (may contain several labels).
        $signatureHeader = $request->header('signature');

        if ($signatureHeader === null) {
            return $this->reject(request: $request, error: 'Signature header required.');
        }

        $signatures = $this->parser->extractRfc9421Signatures(header: $signatureHeader);

        $entry = null;
        $signatureValue = null;

        foreach ($entries as $candidate) {
            if (isset($signatures[$candidate['label']])) {
                $entry = $candidate;
                $signatureValue = $signatures[$candidate['label']];
                break;
            }
        }

        if ($entry === null || $signatureValue === null) {
            return $this->reject(request: $request, error: 'No matching signature for Signature-Input label.');
        }

        $keyId = $entry['keyid'];

        if ($keyId === null) {
            return $this->reject(request: $request, error: 'Missing keyid in Signature-Input.');
        }

        // Validate algorithm: accept the standard RSA tokens.
        $algorithm = $this->verifier->mapRfc9421Algorithm(alg: $entry['alg']);

        if ($algorithm === null) {
            Log::debug('VerifyHttpSignature: unsupported RFC 9421 algorithm', ['alg' => $entry['alg']]);

            return response()->json(
                ['error' => 'Unsupported signature algorithm.'],
                401,
            );
        }

        $components = array_map(
            static fn (string $component): string => strtolower($component),
            $entry['components'],
        );

        $hasMethodComponent = in_array('@method', $components, true)
            || in_array(':method', $components, true);

        $targetComponents = ['@target-uri', '@authority', '@path', '@request-target', ':path', ':authority'];
        $hasTargetComponent = array_intersect($targetComponents, $components) !== [];

        if (! $hasMethodComponent || ! $hasTargetComponent) {
            return $this->reject(
                request: $request,
                error: 'Signature must cover a method and a request target component.',
            );
        }

        $bodyContent = $request->getContent();
        $method = strtolower($request->method());
        $isNonEmptyPost = $method === 'post' && $bodyContent !== '';

        // Time source: require created, expires or a Date header, and enforce skew.
        $skew = (int) config('activitypub.http_signatures.max_clock_skew', 120);
        $now = now()->timestamp;
        $dateHeader = $request->header('date');

        if ($entry['created'] === null && $entry['expires'] === null && $dateHeader === null) {
            return $this->reject(
                request: $request,
                error: 'Signature must include a creation time (created/expires) or a Date header.',
            );
        }

        if ($entry['created'] !== null && abs($entry['created'] - $now) > $skew) {
            return response()->json(
                ['error' => 'Request signature is outside the allowed clock skew.'],
                401,
            );
        }

        if ($entry['expires'] !== null && $now > $entry['expires'] + $skew) {
            return response()->json(
                ['error' => 'Request signature has expired.'],
                401,
            );
        }

        if ($entry['created'] === null && $entry['expires'] === null && $dateHeader !== null) {
            $requestTime = strtotime($dateHeader);

            if ($requestTime === false || abs($requestTime - $now) > $skew) {
                return response()->json(
                    ['error' => 'Request date is outside the allowed clock skew.'],
                    401,
                );
            }
        }

        // Replay check — the signature time is part of the cache key. The key
        // is only registered after successful verification (below) so rejected
        // attempts cannot consume the replay slot for a later legit retry.
        $timeKey = (string) ($entry['created'] ?? $entry['expires'] ?? ($dateHeader !== null ? (string) strtotime($dateHeader) : ''));
        $cacheKey = 'sig-replay-rfc9421:'.md5($keyId.'|'.$signatureValue.'|'.$timeKey);

        if ($isNonEmptyPost) {
            $hasDigestComponent = in_array('content-digest', $components, true)
                || in_array('digest', $components, true);

            if (! $hasDigestComponent) {
                return $this->reject(
                    request: $request,
                    error: 'Signature must cover a content digest component for POST requests.',
                );
            }

            $digestError = $this->verifier->verifyRfc9421ContentDigest(request: $request);

            if ($digestError !== null) {
                return $this->reject(request: $request, error: $digestError);
            }
        }

        // Resolve remote actor (cache only — never fetch on the request path).
        $actorUrl = str_replace('#main-key', '', $keyId);

        if (parse_url($actorUrl, PHP_URL_SCHEME) !== 'https') {
            return response()->json(
                ['error' => 'keyId must use HTTPS.'],
                401,
            );
        }

        $remoteActor = $this->resolveRemoteActor(actorUrl: $actorUrl);

        if ($remoteActor === null) {
            return $this->reject(request: $request, error: 'Unknown remote actor.');
        }

        $publicKey = $remoteActor->public_key_pem;

        if ($publicKey === null || $publicKey === '') {
            return $this->reject(request: $request, error: 'Remote actor has no public key.');
        }

        // Build RFC 9421 signing string and verify
        $signingString = $this->verifier->buildRfc9421SigningString(request: $request, entry: $entry);

        $signature = base64_decode($signatureValue, true);

        if ($signature === false) {
            Log::debug('VerifyHttpSignature: invalid base64 signature (RFC 9421)');

            return $this->reject(request: $request, error: 'Invalid base64 signature.');
        }

        $verified = openssl_verify(
            $signingString,
            $signature,
            $publicKey,
            $algorithm,
        ) === 1;

        if (! $verified) {
            Log::debug('VerifyHttpSignature: RFC 9421 verification failed', [
                'actorUrl' => $actorUrl,
                'signingString' => $signingString,
                'opensslError' => openssl_error_string(),
            ]);

            return $this->reject(request: $request, error: 'Signature verification failed.');
        }

        // Consume the replay slot only after the signature verified.
        if (! Cache::add($cacheKey, true, 120)) {
            return response()->json(
                ['error' => 'Duplicate request detected.'],
                401,
            );
        }

        $request->attributes->set('remote_actor', $remoteActor);

        return $next($request);
    }

    protected function reject(Request $request, string $error, int $status = 401): Response
    {
        InboxLogger::logRejected(request: $request, statusCode: $status, reason: $error);

        return response()->json(
            ['error' => $error],
            $status,
        );
    }

    /**
     * Resolve an already-cached remote actor only. On a miss, queue a
     * background fetch and fail closed (401) instead of performing an
     * attacker-controlled synchronous outbound HTTP request.
     */
    protected function resolveRemoteActor(string $actorUrl): ?RemoteActor
    {
        $remoteActor = RemoteActor::query()
            ->where('actor_url', '=', $actorUrl)
            ->first();

        if ($remoteActor === null && (bool) config('activitypub.http_signatures.queue_unknown_actors', true)) {
            FetchRemoteActor::dispatch(actorUri: $actorUrl);
        }

        return $remoteActor;
    }
}
