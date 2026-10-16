<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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

/**
 * Stub every federation host the suite talks to: WebFinger lookups, remote
 * actor documents and inbox deliveries. Call this from a beforeEach() (or a
 * single test) before exercising an inbound activity that triggers a
 * synchronous Accept delivery, so nothing ever reaches the real network.
 *
 * The defaults return realistic content types so content-negotiation paths
 * keep working: application/jrd+json for WebFinger and
 * application/activity+json for actor documents. Inbox POSTs get an empty 202.
 *
 * Pass $overrides as URL-pattern => response pairs; they are matched before the
 * defaults, so a test can force e.g. a 500 for a specific inbox without
 * abandoning the shared fixtures.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeFederationHttp(array $overrides = []): void
{
    $hosts = [
        'mastodon.social',
        'infosec.exchange',
        'hachyderm.io',
        'example.com',
        'remote.example.com',
        'remote2.example.com',
        'other.example.com',
        'blocked.example.com',
        'keys.example.com',
        'fedi.example.com',
        'cdn.example.com',
        'newserver.example',
        'queue.example',
        'ghost.example.com',
        'bad.example.com',
        'expired.example.com',
        'quota.example',
    ];

    $stubs = [];

    foreach ($hosts as $host) {
        $stubs['https://'.$host.'/*'] = static function (Request $request) {
            $host = parse_url($request->url(), PHP_URL_HOST) ?? 'remote.example.com';
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            // WebFinger discovery.
            if (str_contains($path, '/.well-known/webfinger')) {
                return Http::response(
                    body: json_encode([
                        'subject' => 'acct:'.basename($path).'@'.$host,
                        'links' => [],
                    ]),
                    status: 200,
                    headers: ['Content-Type' => 'application/jrd+json'],
                );
            }

            // Remote actor documents are dereferenced with GET.
            if ($request->method() === 'GET') {
                return Http::response(
                    body: json_encode([
                        'id' => $request->url(),
                        'type' => 'Person',
                        'preferredUsername' => basename($path),
                        'inbox' => $request->url().'/inbox',
                        'publicKey' => [
                            'id' => $request->url().'#main-key',
                            'owner' => $request->url(),
                            'publicKeyPem' => '',
                        ],
                    ]),
                    status: 200,
                    headers: ['Content-Type' => 'application/activity+json'],
                );
            }

            // Inbox deliveries accept the activity.
            return Http::response(body: '', status: 202);
        };
    }

    // `+` (not array_merge) keeps the overrides first so they win the
    // first-match-wins URL pattern lookup.
    Http::fake($overrides + $stubs);
}
