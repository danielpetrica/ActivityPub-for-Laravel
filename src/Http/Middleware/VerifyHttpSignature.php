<?php

namespace DanielPetrica\LaravelActivityPub\Http\Middleware;

use Closure;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyHttpSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('activitypub.http_signatures.enabled')) {
            return $next($request);
        }

        // RFC 9421: uses Signature-Input + Signature headers (lowercase)
        $signatureInputHeader = $request->header(key: 'signature-input');

        if ($signatureInputHeader !== null) {
            return $this->handleRfc9421(request: $request, next: $next, signatureInputHeader: $signatureInputHeader);
        }

        // Draft-cavage: uses Signature header with keyId="..." format
        $signatureHeader = $request->header(key: 'signature');

        if ($signatureHeader === null) {
            return response()->json(
                data: ['error' => 'Signature header required.'],
                status: 401,
            );
        }

        $parsed = $this->parseDraftCavageHeader(header: $signatureHeader);

        if ($parsed === null) {
            return response()->json(
                data: ['error' => 'Invalid Signature header format.'],
                status: 401,
            );
        }

        $keyId = $parsed['keyId'];

        $dateHeader = $request->header(key: 'date');

        if ($dateHeader === null) {
            return response()->json(
                data: ['error' => 'Date header is required.'],
                status: 401,
            );
        }

        $requestTime = strtotime(datetime: $dateHeader);

        if ($requestTime === false || abs(num: $requestTime - now()->timestamp) > config('activitypub.http_signatures.max_clock_skew', 120)) {
            return response()->json(
                data: ['error' => 'Request date is outside the allowed clock skew.'],
                status: 401,
            );
        }

        $cacheKey = 'sig-replay:'.md5($keyId.'|'.$signatureHeader.'|'.$dateHeader);

        $isDuplicate = ! Cache::add(key: $cacheKey, value: true, ttl: 120);

        if ($isDuplicate) {
            return response()->json(
                data: ['error' => 'Duplicate request detected.'],
                status: 401,
            );
        }

        $method = strtolower($request->method());

        if ($method === 'post' && $request->getContent() !== '') {
            $digestHeader = $request->header(key: 'digest');

            if ($digestHeader === null) {
                return response()->json(
                    data: ['error' => 'Digest header is required for POST requests.'],
                    status: 401,
                );
            }
        } else {
            $digestHeader = $request->header(key: 'digest');
        }

        if ($digestHeader !== null) {
            $bodyContent = $request->getContent();

            if ($bodyContent !== '') {
                $computedDigest = 'SHA-256='.base64_encode(string: hash(
                    algo: 'sha256',
                    data: $bodyContent,
                    binary: true,
                ));

                $normalizedDigest = preg_replace_callback(
                    pattern: '/^([a-z][a-z0-9]*)=/i',
                    callback: fn (array $m): string => strtoupper($m[1]).'=',
                    subject: $digestHeader,
                );

                if (! hash_equals(known_string: $normalizedDigest, user_string: $computedDigest)) {
                    return response()->json(
                        data: ['error' => 'Digest header does not match body.'],
                        status: 401,
                    );
                }
            }
        }

        $actorUrl = str_replace(search: '#main-key', replace: '', subject: $keyId);

        // Validate keyId uses HTTPS — prevents downgrade attacks
        if (parse_url($actorUrl, PHP_URL_SCHEME) !== 'https') {
            return response()->json(
                data: ['error' => 'keyId must use HTTPS.'],
                status: 401,
            );
        }

        $remoteActor = $this->resolveRemoteActor(actorUrl: $actorUrl);

        if ($remoteActor === null) {
            return response()->json(
                data: ['error' => 'Unknown remote actor.'],
                status: 401,
            );
        }

        $verified = $this->verifyDraftCavageSignature(
            request: $request,
            parsed: $parsed,
            remoteActor: $remoteActor,
        );

        if (! $verified) {
            return response()->json(
                data: ['error' => 'Signature verification failed.'],
                status: 401,
            );
        }

        $request->attributes->set(key: 'remote_actor', value: $remoteActor);

        return $next($request);
    }

    // ──────────────────────────────────────────────────────────────
    // RFC 9421 HTTP Message Signatures
    // ──────────────────────────────────────────────────────────────

    protected function handleRfc9421(Request $request, Closure $next, string $signatureInputHeader): Response
    {
        $parsed = $this->parseRfc9421SignatureInput(header: $signatureInputHeader);

        if ($parsed === null) {
            return response()->json(
                data: ['error' => 'Invalid Signature-Input header format.'],
                status: 401,
            );
        }

        $keyId = $parsed['keyId'];

        if ($keyId === null) {
            return response()->json(
                data: ['error' => 'Missing keyid in Signature-Input.'],
                status: 401,
            );
        }

        // Validate algorithm is RSA-SHA256
        $algorithm = $parsed['algorithm'] ?? 'rsa-sha256';

        if ($algorithm !== 'rsa-sha256') {
            Log::debug('VerifyHttpSignature: unsupported RFC 9421 algorithm', ['alg' => $algorithm]);

            return response()->json(
                data: ['error' => 'Unsupported signature algorithm.'],
                status: 401,
            );
        }

        // Check created timestamp for clock skew
        if (isset($parsed['created'])) {
            $skew = config('activitypub.http_signatures.max_clock_skew', 120);

            if (abs($parsed['created'] - now()->timestamp) > $skew) {
                return response()->json(
                    data: ['error' => 'Request signature is outside the allowed clock skew.'],
                    status: 401,
                );
            }
        }

        // Get signature value from Signature header
        $signatureHeader = $request->header(key: 'signature');

        if ($signatureHeader === null) {
            return response()->json(
                data: ['error' => 'Signature header required.'],
                status: 401,
            );
        }

        $signatureValue = $this->extractRfc9421Signature(header: $signatureHeader, label: $parsed['label']);

        if ($signatureValue === null) {
            return response()->json(
                data: ['error' => 'Invalid Signature header format for RFC 9421.'],
                status: 401,
            );
        }

        // Replay check
        $cacheKey = 'sig-replay-rfc9421:'.md5($keyId.'|'.$signatureHeader);

        if (! Cache::add(key: $cacheKey, value: true, ttl: 120)) {
            return response()->json(
                data: ['error' => 'Duplicate request detected.'],
                status: 401,
            );
        }

        // Resolve remote actor
        $actorUrl = str_replace(search: '#main-key', replace: '', subject: $keyId);

        if (parse_url($actorUrl, PHP_URL_SCHEME) !== 'https') {
            return response()->json(
                data: ['error' => 'keyId must use HTTPS.'],
                status: 401,
            );
        }

        $remoteActor = $this->resolveRemoteActor(actorUrl: $actorUrl);

        if ($remoteActor === null) {
            return response()->json(
                data: ['error' => 'Unknown remote actor.'],
                status: 401,
            );
        }

        // Build RFC 9421 signing string and verify
        $signingString = $this->buildRfc9421SigningString(request: $request, parsed: $parsed);

        $signature = base64_decode(string: $signatureValue);

        if ($signature === false) {
            Log::debug('VerifyHttpSignature: invalid base64 signature (RFC 9421)');

            return response()->json(
                data: ['error' => 'Invalid base64 signature.'],
                status: 401,
            );
        }

        $verified = openssl_verify(
            data: $signingString,
            signature: $signature,
            public_key: $remoteActor->public_key_pem,
            algorithm: OPENSSL_ALGO_SHA256,
        ) === 1;

        if (! $verified) {
            Log::debug('VerifyHttpSignature: RFC 9421 verification failed', [
                'actorUrl' => $actorUrl,
                'signingString' => $signingString,
                'opensslError' => openssl_error_string(),
            ]);

            return response()->json(
                data: ['error' => 'Signature verification failed.'],
                status: 401,
            );
        }

        $request->attributes->set(key: 'remote_actor', value: $remoteActor);

        return $next($request);
    }

    /**
     * Parse the RFC 9421 Signature-Input header.
     *
     * Format: label=("comp1" "comp2");created=...;keyid="...";alg="..."
     */
    protected function parseRfc9421SignatureInput(string $header): ?array
    {
        $header = trim($header);

        // Match: label=("component1" "component2");params...
        if (! preg_match('/^(\w+)\s*=\s*\(([^)]*)\)(.*)$/', $header, $matches)) {
            return null;
        }

        $label = $matches[1];
        $componentsStr = $matches[2];
        $paramsStr = $matches[3];

        // Extract component names from quoted strings
        preg_match_all('/"([^"]+)"/', $componentsStr, $componentMatches);
        $components = $componentMatches[1];

        // Parse parameters
        $keyId = null;
        $created = null;
        $algorithm = null;

        if (preg_match('/keyid="([^"]+)"/', $paramsStr, $m)) {
            $keyId = $m[1];
        }

        if (preg_match('/created=(\d+)/', $paramsStr, $m)) {
            $created = (int) $m[1];
        }

        if (preg_match('/alg="([^"]+)"/', $paramsStr, $m)) {
            $algorithm = $m[1];
        }

        return [
            'label' => $label,
            'components' => $components,
            'keyId' => $keyId,
            'created' => $created,
            'algorithm' => $algorithm,
            // Preserve raw params for the signature base reconstruction
            'rawParams' => ltrim($paramsStr, '; '),
        ];
    }

    /**
     * Extract the base64 signature from the RFC 9421 Signature header.
     *
     * Format: label=:base64data:
     */
    protected function extractRfc9421Signature(string $header, string $label): ?string
    {
        $pattern = '/^'.preg_quote($label, '/').'\s*=\s*:(.+):$/s';

        if (! preg_match(pattern: $pattern, subject: trim($header), matches: $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Build the signing string per RFC 9421 Section 2.3.
     *
     * Each covered component is encoded as: "name": value\n
     * The signature parameters line uses the raw Signature-Input value.
     */
    protected function buildRfc9421SigningString(Request $request, array $parsed): string
    {
        $lines = [];

        foreach ($parsed['components'] as $component) {
            $value = $this->getRfc9421ComponentValue(request: $request, component: $component);
            $lines[] = '"'.$component.'": '.$value;
        }

        // Signature parameters line: reconstruct from parsed data
        $componentsQuoted = implode(' ', array_map(fn ($c) => '"'.$c.'"', $parsed['components']));
        $paramsValue = '('.$componentsQuoted.');'.$parsed['rawParams'];
        $lines[] = '"("@signature-params)": '.$paramsValue;

        return implode("\n", $lines);
    }

    /**
     * Get the value for a covered component in the signing string.
     */
    protected function getRfc9421ComponentValue(Request $request, string $component): string
    {
        return match ($component) {
            '@method' => strtolower($request->method()),
            '@target-uri' => $this->buildTargetUri(request: $request),
            '@authority' => $request->getHost(),
            '@scheme' => $request->getScheme(),
            '@request-target' => $request->getRequestUri(),
            '@path' => $request->getPathInfo(),
            '@query' => $request->getQueryString() ?? '',
            default => $request->header($component, ''),
        };
    }

    /**
     * Build the full target URI for the @target-uri derived component.
     */
    protected function buildTargetUri(Request $request): string
    {
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $path = $request->getPathInfo();
        $query = $request->getQueryString();

        $uri = $scheme.'://'.$host.$path;

        if ($query !== null && $query !== '') {
            $uri .= '?'.$query;
        }

        return $uri;
    }

    // ──────────────────────────────────────────────────────────────
    // Draft-cavage HTTP Signatures (legacy / Mastodon < 4.7)
    // ──────────────────────────────────────────────────────────────

    protected function parseDraftCavageHeader(string $header): ?array
    {
        $parts = [];

        foreach (explode(separator: ',', string: $header) as $param) {
            $param = trim(string: $param);

            if (preg_match(pattern: '/^(\w+)="(.*)"$/', subject: $param, matches: $matches)) {
                $parts[$matches[1]] = $matches[2];
            }
        }

        if (! isset($parts['keyId'], $parts['signature'], $parts['headers'])) {
            return null;
        }

        return $parts;
    }

    protected function verifyDraftCavageSignature(Request $request, array $parsed, RemoteActor $remoteActor): bool
    {
        $publicKey = $remoteActor->public_key_pem;

        if ($publicKey === null) {
            return false;
        }

        // Validate algorithm is RSA-SHA256
        $algorithm = $parsed['algorithm'] ?? 'rsa-sha256';
        if ($algorithm !== 'rsa-sha256') {
            return false;
        }

        $signature = base64_decode(string: $parsed['signature']);

        if ($signature === false) {
            Log::debug('VerifyHttpSignature: invalid base64 signature');

            return false;
        }

        $configHost = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST) ?? $request->getHost();

        $signingString = $this->buildDraftCavageSigningString(
            request: $request,
            parsed: $parsed,
            host: $configHost,
        );

        $result = $this->verifyWithString(
            signingString: $signingString,
            signature: $signature,
            publicKey: $publicKey,
        );

        if ($result) {
            return true;
        }

        $opensslError = openssl_error_string();

        $configHost = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST);

        if ($configHost !== null && $configHost !== $request->getHost()) {
            $altSigningString = $this->buildDraftCavageSigningString(
                request: $request,
                parsed: $parsed,
                host: $configHost,
            );

            $result = $this->verifyWithString(
                signingString: $altSigningString,
                signature: $signature,
                publicKey: $publicKey,
            );

            if ($result) {
                return true;
            }
        }

        Log::debug('VerifyHttpSignature: draft-cavage verification failed', [
            'actorUrl' => $remoteActor->actor_url,
            'requestHost' => $request->getHost(),
            'configHost' => $configHost,
            'opensslError' => $opensslError,
            'signedHeaders' => $parsed['headers'],
            'signingString' => $signingString,
        ]);

        return false;
    }

    protected function buildDraftCavageSigningString(Request $request, array $parsed, string $host): string
    {
        $headers = explode(separator: ' ', string: $parsed['headers']);
        $path = $request->getPathInfo();
        $method = strtolower(string: $request->method());

        $signingString = '';

        foreach ($headers as $header) {
            $header = trim(string: $header);

            if ($header === '(request-target)') {
                $signingString .= '(request-target): '.$method.' '.$path."\n";
            } elseif ($header === 'host') {
                $signingString .= 'host: '.$host."\n";
            } elseif ($header === 'date') {
                $signingString .= 'date: '.$request->header(key: 'date', default: '')."\n";
            } elseif ($header === 'digest') {
                $signingString .= 'digest: '.$request->header(key: 'digest', default: '')."\n";
            } elseif ($header === 'content-type') {
                $signingString .= 'content-type: '.$request->header(key: 'content-type', default: '')."\n";
            }
        }

        return rtrim(string: $signingString);
    }

    protected function verifyWithString(string $signingString, string $signature, string $publicKey): bool
    {
        return openssl_verify(
            data: $signingString,
            signature: $signature,
            public_key: $publicKey,
            algorithm: OPENSSL_ALGO_SHA256,
        ) === 1;
    }

    // ──────────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────────

    protected function resolveRemoteActor(string $actorUrl): ?RemoteActor
    {
        $remoteActor = RemoteActor::query()
            ->where(column: 'actor_url', operator: '=', value: $actorUrl)
            ->first();

        if ($remoteActor === null) {
            $resolver = app(RemoteActorResolver::class);
            $remoteActor = $resolver->resolve(actorUri: $actorUrl);
        }

        return $remoteActor;
    }
}
