<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;

final class HttpSignatureService
{
    public function sign(string $method, string $url, array $headers, ActorContract $actor): array
    {
        $parsedUrl = parse_url(url: $url);
        $path = $parsedUrl['path'] ?? '/';
        $host = $parsedUrl['host'] ?? '';
        $date = $headers['Date'] ?? now()->toRfc7231String();

        $signingParts = [
            '(request-target): '.strtolower(string: $method).' '.$path,
            'host: '.$host,
            'date: '.$date,
        ];

        if (isset($headers['Digest'])) {
            $signingParts[] = 'digest: '.$headers['Digest'];
        }

        $signingString = implode(separator: "\n", array: $signingParts);

        $privateKey = $this->getPrivateKey(actor: $actor);

        if ($privateKey === null) {
            throw new \RuntimeException(message: 'Cannot sign request: actor has no private key.');
        }

        $signature = '';

        $result = openssl_sign(
            data: $signingString,
            signature: $signature,
            private_key: $privateKey,
            algorithm: OPENSSL_ALGO_SHA256,
        );

        if (! $result) {
            throw new \RuntimeException(message: 'Failed to sign request with actor private key.');
        }

        $signatureBase64 = base64_encode(string: $signature);

        $signedHeaders = ['(request-target)', 'host', 'date'];

        if (isset($headers['Digest'])) {
            $signedHeaders[] = 'digest';
        }

        $signatureHeader = 'keyId="'.$actor->getKeyId().'",'
            .'algorithm="rsa-sha256",'
            .'headers="'.implode(separator: ' ', array: $signedHeaders).'",'
            .'signature="'.$signatureBase64.'"';

        $headers['Signature'] = $signatureHeader;
        $headers['Date'] = $date;

        return $headers;
    }

    protected function getPrivateKey(ActorContract $actor): ?string
    {
        if ($actor instanceof Actor) {
            return $actor->private_key_pem;
        }

        return null;
    }

    protected function parseSignatureHeader(string $header): ?array
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
}
