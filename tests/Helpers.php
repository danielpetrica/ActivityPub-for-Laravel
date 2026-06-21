<?php

function generateTestKeyPair(): array
{
    $keyResource = openssl_pkey_new(options: [
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    $privateKey = '';
    openssl_pkey_export(key: $keyResource, output: $privateKey);

    $publicKey = openssl_pkey_get_details(key: $keyResource)['key'];

    return ['public' => $publicKey, 'private' => $privateKey];
}
