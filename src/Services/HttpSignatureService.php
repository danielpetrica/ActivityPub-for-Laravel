<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;

final class HttpSignatureService
{
    public function sign(string $method, string $url, array $headers, ActorContract $actor): array
    {
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            throw new \RuntimeException(message: 'Cannot sign request: invalid URL.');
        }

        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? null;
        $scheme = strtolower((string) ($parsedUrl['scheme'] ?? 'https'));

        // Draft-cavage (request-target) must include the query string.
        $requestTarget = $path.($query !== null && $query !== '' ? '?'.$query : '');

        $host = $this->buildHost(
            host: (string) ($parsedUrl['host'] ?? ''),
            port: $parsedUrl['port'] ?? null,
            scheme: $scheme,
        );

        $date = $headers['Date'] ?? now()->toRfc7231String();

        $signingParts = [
            '(request-target): '.strtolower($method).' '.$requestTarget,
            'host: '.$host,
            'date: '.$date,
        ];

        $signedHeaders = ['(request-target)', 'host', 'date'];

        if (isset($headers['Digest'])) {
            $signingParts[] = 'digest: '.$headers['Digest'];
            $signedHeaders[] = 'digest';
        } elseif (isset($headers['digest'])) {
            $signingParts[] = 'digest: '.$headers['digest'];
            $signedHeaders[] = 'digest';
        }

        // Sign the content-type when present so it is tamper-proof.
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? null;

        if (is_string($contentType) && $contentType !== '') {
            $signingParts[] = 'content-type: '.$contentType;
            $signedHeaders[] = 'content-type';
        }

        $signingString = implode("\n", $signingParts);

        $privateKey = $this->getPrivateKey(actor: $actor);

        if ($privateKey === null) {
            throw new \RuntimeException(message: 'Cannot sign request: actor has no private key.');
        }

        $signature = '';

        $result = openssl_sign(
            $signingString,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256,
        );

        if (! $result) {
            throw new \RuntimeException(message: 'Failed to sign request with actor private key.');
        }

        $signatureBase64 = base64_encode($signature);

        $signatureHeader = 'keyId="'.$actor->getKeyId().'",'
            .'algorithm="rsa-sha256",'
            .'headers="'.implode(' ', $signedHeaders).'",'
            .'signature="'.$signatureBase64.'"';

        $headers['Signature'] = $signatureHeader;
        $headers['Date'] = $date;
        // Keep the transmitted Host header consistent with the signed host,
        // including non-default ports.
        $headers['Host'] = $host;

        return $headers;
    }

    /**
     * Resolve the actor's private key through the contract instead of
     * type-checking against the concrete Actor model, so custom actor
     * implementations keep working.
     */
    protected function getPrivateKey(ActorContract $actor): ?string
    {
        return $actor->getPrivateKeyPem();
    }

    /**
     * Build the host header value, including non-default ports.
     */
    protected function buildHost(string $host, ?int $port, string $scheme): string
    {
        if ($host === '' || $port === null) {
            return $host;
        }

        $defaultPort = $scheme === 'http' ? 80 : 443;

        if ($port === $defaultPort) {
            return $host;
        }

        return $host.':'.$port;
    }
}
