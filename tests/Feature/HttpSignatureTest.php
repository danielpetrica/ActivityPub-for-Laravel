<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function buildSignatureHeader(string $actorUrl, string $date, string $signingString, string $privateKey): string
{
    $keyResource = openssl_pkey_get_private($privateKey);
    openssl_sign($signingString, $signature, $keyResource, OPENSSL_ALGO_SHA256);
    openssl_pkey_free($keyResource);

    $signatureBase64 = base64_encode($signature);

    return 'keyId="' . $actorUrl . '#main-key",'
        . 'algorithm="rsa-sha256",'
        . 'headers="(request-target) host date digest",'
        . 'signature="' . $signatureBase64 . '"';
}

function buildSigningString(array $lines): string
{
    return implode("\n", $lines);
}

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'sigtest',
        'name' => 'Sig Test',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->remoteKeys = generateTestKeyPair();

    $this->remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://remote.example.com/users/sender',
        'inbox_url' => 'https://remote.example.com/users/sender/inbox',
        'username' => 'sender',
        'domain' => 'remote.example.com',
        'public_key_pem' => $this->remoteKeys['public'],
    ]);
});

it('signs outbound delivery with valid signature header', function (): void {
    Http::fake([
        'remote.example.com/*' => Http::response(status: 202),
    ]);

    $deliveryClient = app(DeliveryClient::class);

    $result = $deliveryClient->deliver(
        inboxUrl: 'https://remote.example.com/users/follower/inbox',
        activity: ['type' => 'Create', 'actor' => $this->actor->getActorId()],
        actor: $this->actor,
    );

    expect($result)->not->toBeNull();
    expect($result['status'])->toBe(202);

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Signature')
            && str_contains($request->header('Signature')[0], 'keyId=')
            && str_contains($request->header('Signature')[0], 'algorithm="rsa-sha256"');
    });
});

it('includes digest in signed headers for POST requests', function (): void {
    Http::fake([
        'remote.example.com/*' => Http::response(status: 202),
    ]);

    $deliveryClient = app(DeliveryClient::class);

    $deliveryClient->deliver(
        inboxUrl: 'https://remote.example.com/users/follower/inbox',
        activity: ['type' => 'Create', 'actor' => $this->actor->getActorId()],
        actor: $this->actor,
    );

    Http::assertSent(function (Request $request): bool {
        $signatureHeader = $request->header('Signature')[0];

        return $request->hasHeader('Digest')
            && str_contains($signatureHeader, 'headers="(request-target) host date digest"');
    });
});

it('rejects incoming requests with expired date', function (): void {
    $keys = generateTestKeyPair();

    $remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://expired.example.com/users/sender',
        'inbox_url' => 'https://expired.example.com/users/sender/inbox',
        'username' => 'sender',
        'domain' => 'expired.example.com',
        'public_key_pem' => $keys['public'],
    ]);

    $body = json_encode(['type' => 'Create', 'actor' => $remoteActor->actor_url]);
    $date = gmdate('D, d M Y H:i:s T', now()->subMinutes(20)->timestamp);
    $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: ' . $date,
    ]);

    $signatureHeader = buildSignatureHeader(
        $remoteActor->actor_url,
        $date,
        $signingString,
        $keys['private'],
    );

    $response = $this->postJson(
        uri: '/inbox',
        data: json_decode($body, true),
        headers: [
            'Date' => $date,
            'Host' => 'localhost',
            'Signature' => $signatureHeader,
            'Digest' => $digest,
            'Content-Type' => 'application/activity+json',
        ],
    );

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Request date is outside the allowed clock skew.']);
});

it('rejects incoming requests with invalid digest', function (): void {
    $keys = generateTestKeyPair();

    $remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://baddigest.example.com/users/sender',
        'inbox_url' => 'https://baddigest.example.com/users/sender/inbox',
        'username' => 'sender',
        'domain' => 'baddigest.example.com',
        'public_key_pem' => $keys['public'],
    ]);

    $body = json_encode(['type' => 'Create', 'actor' => $remoteActor->actor_url]);
    $date = gmdate('D, d M Y H:i:s T');
    $wrongDigest = 'SHA-256=' . base64_encode(hash('sha256', 'tampered-body', binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: ' . $date,
        'digest: ' . $wrongDigest,
    ]);

    $signatureHeader = buildSignatureHeader(
        $remoteActor->actor_url,
        $date,
        $signingString,
        $keys['private'],
    );

    $response = $this->postJson(
        uri: '/inbox',
        data: json_decode($body, true),
        headers: [
            'Date' => $date,
            'Host' => 'localhost',
            'Signature' => $signatureHeader,
            'Digest' => $wrongDigest,
            'Content-Type' => 'application/activity+json',
        ],
    );

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Digest header does not match body.']);
});

it('accepts valid incoming signature', function (): void {
    $keys = generateTestKeyPair();

    $remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://localhost/users/sender',
        'inbox_url' => 'https://localhost/users/sender/inbox',
        'username' => 'sender',
        'domain' => 'localhost',
        'public_key_pem' => $keys['public'],
    ]);

    $body = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $remoteActor->actor_url,
        'object' => ['type' => 'Note', 'content' => 'Hello'],
    ]);
    $date = gmdate('D, d M Y H:i:s T');
    $digest = 'SHA-256=' . base64_encode(hash('sha256', $body, binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: ' . $date,
        'digest: ' . $digest,
    ]);

    $signatureHeader = buildSignatureHeader(
        $remoteActor->actor_url,
        $date,
        $signingString,
        $keys['private'],
    );

    $response = $this->postJson(
        uri: '/inbox',
        data: json_decode($body, true),
        headers: [
            'Date' => $date,
            'Host' => 'localhost',
            'Signature' => $signatureHeader,
            'Digest' => $digest,
            'Content-Type' => 'application/activity+json',
        ],
    );

    $response->assertStatus(status: 202);
    $response->assertJson(['status' => 'accepted']);
});
