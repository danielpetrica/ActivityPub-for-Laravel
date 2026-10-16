<?php

namespace DanielPetrica\LaravelActivityPub\Http\Signatures;

use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cryptographic verification and signing-string construction for draft-cavage
 * and RFC 9421 HTTP signatures.
 */
final class SignatureVerifier
{
    public function __construct(
        private SignatureParser $parser,
    ) {}

    /**
     * Map an RFC 9421 algorithm token to an OpenSSL digest constant.
     */
    public function mapRfc9421Algorithm(?string $alg): ?int
    {
        if ($alg === null || trim($alg) === '') {
            return OPENSSL_ALGO_SHA256;
        }

        $normalized = strtolower(trim($alg));

        // Some implementations append structured-field parameters.
        $normalized = trim(explode(';', $normalized)[0]);

        return match ($normalized) {
            'rsa-sha256', 'rsa-v1_5-sha256', 'rsa-pss-sha256', 'sha256', 'rsa' => OPENSSL_ALGO_SHA256,
            'rsa-sha512', 'rsa-v1_5-sha512', 'rsa-pss-sha512', 'sha512' => OPENSSL_ALGO_SHA512,
            default => null,
        };
    }

    /**
     * Build the signing string per RFC 9421 Section 2.5.
     *
     * Each covered component is encoded as: "name": value\n
     * The @signature-params line reuses the exact Signature-Input value so the
     * reconstruction is byte-exact.
     *
     * @param  array{
     *     label: string,
     *     components: list<string>,
     *     value: string,
     *     keyid: string|null,
     *     created: int|null,
     *     expires: int|null,
     *     nonce: string|null,
     *     tag: string|null,
     *     alg: string|null
     * }  $entry
     */
    public function buildRfc9421SigningString(Request $request, array $entry): string
    {
        $lines = [];

        foreach ($entry['components'] as $component) {
            $value = $this->getRfc9421ComponentValue(request: $request, component: $component);
            $lines[] = '"'.$component.'": '.$value;
        }

        $lines[] = '"("@signature-params)": '.$entry['value'];

        return implode("\n", $lines);
    }

    /**
     * Get the value for a covered component in the signing string.
     */
    protected function getRfc9421ComponentValue(Request $request, string $component): string
    {
        return match (strtolower($component)) {
            '@method' => $request->method(),
            '@target-uri' => $this->buildTargetUri(request: $request),
            '@authority' => $this->buildHostHeader(request: $request),
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
        $host = $this->buildHostHeader(request: $request);
        $path = $request->getPathInfo();
        $query = $request->getQueryString();

        $uri = $scheme.'://'.$host.$path;

        if ($query !== null && $query !== '') {
            $uri .= '?'.$query;
        }

        return $uri;
    }

    /**
     * Build the request authority (host) including non-default ports.
     */
    public function buildHostHeader(Request $request): string
    {
        $host = $request->getHost();
        $port = $request->getPort();

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            return $host.':'.$port;
        }

        return $host;
    }

    /**
     * Verify the request body against Content-Digest/Digest headers.
     */
    public function verifyRfc9421ContentDigest(Request $request): ?string
    {
        $header = $request->header('content-digest') ?? $request->header('digest');

        if ($header === null) {
            return 'Content digest header is required for POST requests.';
        }

        $expected = null;

        if (preg_match('/sha-256\s*=\s*:([^:]+):/i', $header, $matches) === 1) {
            $expected = $matches[1];
        } elseif (preg_match('/sha-256\s*=\s*([A-Za-z0-9+\/=]+)/i', $header, $matches) === 1) {
            $expected = $matches[1];
        }

        if ($expected === null) {
            return 'Unsupported content digest algorithm.';
        }

        $computed = base64_encode(hash('sha256', $request->getContent(), true));

        if (! hash_equals($computed, $expected)) {
            return 'Content digest does not match body.';
        }

        return null;
    }

    /**
     * @param  array{keyId: string, signature: string, headers: string, algorithm?: string}  $parsed
     */
    public function verifyDraftCavageSignature(Request $request, array $parsed, RemoteActor $remoteActor): bool
    {
        $publicKey = $remoteActor->public_key_pem;

        if ($publicKey === null || $publicKey === '') {
            return false;
        }

        // Validate algorithm is RSA-SHA256
        $algorithm = $parsed['algorithm'] ?? 'rsa-sha256';

        if ($algorithm !== 'rsa-sha256') {
            return false;
        }

        $signature = base64_decode($parsed['signature'], true);

        if ($signature === false) {
            Log::debug('VerifyHttpSignature: invalid base64 signature');

            return false;
        }

        $hostCandidates = [$this->buildHostHeader(request: $request)];
        $configuredHost = $this->configuredHost();

        if ($configuredHost !== null && $configuredHost !== '') {
            $hostCandidates[] = $configuredHost;
        }

        $hostCandidates = array_values(array_unique($hostCandidates));

        foreach ($hostCandidates as $candidate) {
            $signingString = $this->buildDraftCavageSigningString(
                request: $request,
                parsed: $parsed,
                host: $candidate,
            );

            if ($this->verifyWithString(
                signingString: $signingString,
                signature: $signature,
                publicKey: $publicKey,
            )) {
                return true;
            }
        }

        Log::debug('VerifyHttpSignature: draft-cavage verification failed', [
            'actorUrl' => $remoteActor->actor_url,
            'requestHost' => $request->getHost(),
            'opensslError' => openssl_error_string(),
            'signedHeaders' => $parsed['headers'],
        ]);

        return false;
    }

    /**
     * @param  array{keyId: string, signature: string, headers: string, algorithm?: string}  $parsed
     */
    protected function buildDraftCavageSigningString(Request $request, array $parsed, string $host): string
    {
        $headers = $this->parser->signedComponentNames(headers: $parsed['headers']);
        $method = strtolower($request->method());

        $signingString = '';

        foreach ($headers as $header) {
            $value = match ($header) {
                '(request-target)' => $method.' '.$request->getRequestUri(),
                'host' => $host,
                default => $request->header($header, ''),
            };

            $signingString .= $header.': '.$value."\n";
        }

        return rtrim($signingString);
    }

    /**
     * Resolve the host used in the configured ActivityPub domain, with port.
     */
    protected function configuredHost(): ?string
    {
        $domain = config('activitypub.domain');

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        $host = parse_url($domain, PHP_URL_HOST);

        if (! is_string($host)) {
            return null;
        }

        $port = parse_url($domain, PHP_URL_PORT);

        if (is_int($port) && ! in_array($port, [80, 443], true)) {
            return $host.':'.$port;
        }

        return $host;
    }

    public function verifyWithString(string $signingString, string $signature, string $publicKey): bool
    {
        return openssl_verify(
            $signingString,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        ) === 1;
    }
}
