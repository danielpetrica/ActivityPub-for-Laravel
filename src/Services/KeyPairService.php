<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use RuntimeException;

final class KeyPairService
{
    /**
     * @return array{public: string, private: string}
     */
    public function generate(): array
    {
        $keyResource = openssl_pkey_new(options: [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keyResource === false) {
            throw new RuntimeException(message: 'Failed to generate RSA key pair.');
        }

        $privateKey = '';

        $exported = openssl_pkey_export(key: $keyResource, output: $privateKey);

        if ($exported === false) {
            throw new RuntimeException(message: 'Failed to export private key.');
        }

        $publicKey = openssl_pkey_get_details(key: $keyResource)['key'];

        return [
            'public' => $publicKey,
            'private' => $privateKey,
        ];
    }
}
