<?php

use DanielPetrica\LaravelActivityPub\Http\Middleware\VerifyHttpSignature;
use DanielPetrica\LaravelActivityPub\Jobs\FetchRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use DanielPetrica\LaravelActivityPub\Services\HttpSignatureService;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

function buildSignatureHeader(
    string $actorUrl,
    string $date,
    string $signingString,
    string $privateKey,
    string $headers = '(request-target) host date digest',
): string {
    $keyResource = openssl_pkey_get_private($privateKey);
    openssl_sign($signingString, $signature, $keyResource, OPENSSL_ALGO_SHA256);
    openssl_pkey_free($keyResource);

    $signatureBase64 = base64_encode($signature);

    return 'keyId="'.$actorUrl.'#main-key",'
        .'algorithm="rsa-sha256",'
        .'headers="'.$headers.'",'
        .'signature="'.$signatureBase64.'"';
}

function buildSigningString(array $lines): string
{
    return implode("\n", $lines);
}

function extractSignatureParam(string $header, string $param): ?string
{
    return preg_match('/'.$param.'="([^"]*)"/', $header, $matches) === 1
        ? $matches[1]
        : null;
}

/**
 * Build a valid RFC 9421 Signature-Input + Signature request for the given
 * body and target URI.
 *
 * @return array<string, string>
 */
function buildRfc9421Headers(
    string $keyId,
    string $privateKey,
    string $targetUri,
    string $body,
    int $created,
    array $components = ['@method', '@target-uri', 'content-digest'],
    string $alg = 'rsa-v1_5-sha256',
    string $label = 'sig1',
): array {
    $digest = 'sha-256=:'.base64_encode(hash('sha256', $body, binary: true)).':';
    $componentsQuoted = implode(' ', array_map(static fn (string $c): string => '"'.$c.'"', $components));
    $params = '('.$componentsQuoted.');created='.$created.';keyid="'.$keyId.'";alg="'.$alg.'"';

    $lines = [];
    foreach ($components as $component) {
        $value = match ($component) {
            '@method' => 'POST',
            '@target-uri' => $targetUri,
            'content-digest' => $digest,
            default => '',
        };
        $lines[] = '"'.$component.'": '.$value;
    }
    $lines[] = '"("@signature-params)": '.$params;

    $base = implode("\n", $lines);

    $keyResource = openssl_pkey_get_private($privateKey);
    openssl_sign($base, $signature, $keyResource, OPENSSL_ALGO_SHA256);
    openssl_pkey_free($keyResource);

    return [
        'Signature-Input' => $label.'='.$params,
        'Signature' => $label.'=:'.base64_encode($signature).':',
        'Content-Digest' => $digest,
    ];
}

/**
 * Send a raw-body POST request with the given header map.
 */
function sendRawPost(string $uri, string $body, array $headers): TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/activity+json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call(
        method: 'POST',
        uri: $uri,
        parameters: [],
        cookies: [],
        files: [],
        server: $server,
        content: $body,
    );
}

beforeEach(function (): void {
    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);

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
            && str_contains($signatureHeader, 'headers="(request-target) host date digest content-type"');
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
    $digest = 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: '.$date,
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
    $wrongDigest = 'SHA-256='.base64_encode(hash('sha256', 'tampered-body', binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: '.$date,
        'digest: '.$wrongDigest,
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
    $digest = 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: '.$date,
        'digest: '.$digest,
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

it('runs the inbox rate limiter before signature verification', function (): void {
    $router = app('router');

    $route = collect($router->getRoutes()->getRoutes())
        ->first(fn ($route): bool => $route->getName() === 'activitypub.actor.inbox.store');

    expect($route)->not->toBeNull();

    $middleware = $router->gatherRouteMiddleware($route);

    $verifyIndex = array_search(
        VerifyHttpSignature::class,
        $middleware,
        true,
    );

    $throttleIndex = null;

    foreach ($middleware as $index => $resolved) {
        if (is_string($resolved) && str_contains($resolved, ThrottleRequests::class)) {
            $throttleIndex = $index;
            break;
        }
    }

    expect($verifyIndex)->not->toBeFalse();
    expect($throttleIndex)->not->toBeNull();
    expect($throttleIndex)->toBeLessThan($verifyIndex);
});

it('signs outbound requests with a spec-correct signing string', function (): void {
    $service = app(HttpSignatureService::class);
    $date = 'Mon, 01 Jan 2024 00:00:00 GMT';
    $digest = 'SHA-256='.base64_encode(hash('sha256', '{}', binary: true));

    $headers = $service->sign(
        method: 'POST',
        url: 'https://remote.example.com:8443/users/follower/inbox?foo=bar',
        headers: [
            'Date' => $date,
            'Digest' => $digest,
            'Content-Type' => 'application/activity+json',
        ],
        actor: $this->actor,
    );

    $signatureHeader = $headers['Signature'];

    expect(extractSignatureParam($signatureHeader, 'headers'))
        ->toBe('(request-target) host date digest content-type');

    $signatureValue = extractSignatureParam($signatureHeader, 'signature');
    expect($signatureValue)->not->toBeNull();

    // (request-target) must include the query, host must include the port.
    $signingString = implode("\n", [
        '(request-target): post /users/follower/inbox?foo=bar',
        'host: remote.example.com:8443',
        'date: '.$date,
        'digest: '.$digest,
        'content-type: application/activity+json',
    ]);

    $verified = openssl_verify(
        $signingString,
        base64_decode($signatureValue),
        $this->actor->public_key_pem,
        OPENSSL_ALGO_SHA256,
    );

    expect($verified)->toBe(1);
    expect($headers['Date'])->toBe($date);
});

it('rejects draft-cavage signatures that cover only date', function (): void {
    $body = json_encode(['type' => 'Create', 'actor' => $this->remoteActor->actor_url]);
    $date = gmdate('D, d M Y H:i:s T');

    $signingString = 'date: '.$date;

    $signatureHeader = buildSignatureHeader(
        $this->remoteActor->actor_url,
        $date,
        $signingString,
        $this->remoteKeys['private'],
        headers: 'date',
    );

    $response = $this->postJson(
        uri: '/inbox',
        data: json_decode($body, true),
        headers: [
            'Date' => $date,
            'Host' => 'localhost',
            'Signature' => $signatureHeader,
            'Digest' => 'SHA-256='.base64_encode(hash('sha256', $body, binary: true)),
            'Content-Type' => 'application/activity+json',
        ],
    );

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Signature must cover the (request-target) and host components.']);
});

it('rejects draft-cavage POST signatures that do not cover the digest', function (): void {
    $body = json_encode(['type' => 'Create', 'actor' => $this->remoteActor->actor_url]);
    $date = gmdate('D, d M Y H:i:s T');

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: '.$date,
    ]);

    $signatureHeader = buildSignatureHeader(
        $this->remoteActor->actor_url,
        $date,
        $signingString,
        $this->remoteKeys['private'],
        headers: '(request-target) host date',
    );

    $response = $this->postJson(
        uri: '/inbox',
        data: json_decode($body, true),
        headers: [
            'Date' => $date,
            'Host' => 'localhost',
            'Signature' => $signatureHeader,
            'Digest' => 'SHA-256='.base64_encode(hash('sha256', $body, binary: true)),
            'Content-Type' => 'application/activity+json',
        ],
    );

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Signature must cover the digest component for POST requests.']);
});

it('rejects unknown remote actors and queues a background fetch', function (): void {
    Bus::fake();

    $keys = generateTestKeyPair();
    $body = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => 'https://ghost.example.com/users/ghost',
        'object' => ['type' => 'Note', 'content' => 'Hello'],
    ]);
    $date = gmdate('D, d M Y H:i:s T');
    $digest = 'SHA-256='.base64_encode(hash('sha256', $body, binary: true));

    $signingString = buildSigningString([
        '(request-target): post /inbox',
        'host: localhost',
        'date: '.$date,
        'digest: '.$digest,
    ]);

    $signatureHeader = buildSignatureHeader(
        'https://ghost.example.com/users/ghost',
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
    $response->assertJson(['error' => 'Unknown remote actor.']);

    Bus::assertDispatched(FetchRemoteActor::class, function (FetchRemoteActor $job): bool {
        return $job->actorUri === 'https://ghost.example.com/users/ghost';
    });
});

it('accepts a valid RFC 9421 signed request', function (): void {
    $targetUri = route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]);

    $body = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $this->remoteActor->actor_url,
        'object' => ['type' => 'Note', 'content' => 'RFC 9421 Hello'],
    ]);

    $headers = buildRfc9421Headers(
        keyId: $this->remoteActor->actor_url.'#main-key',
        privateKey: $this->remoteKeys['private'],
        targetUri: $targetUri,
        body: $body,
        created: now()->timestamp,
    );

    $response = sendRawPost(uri: $targetUri, body: $body, headers: $headers);

    $response->assertStatus(status: 202);
    $response->assertJson(['status' => 'accepted']);
});

it('rejects RFC 9421 signatures that omit method and target components', function (): void {
    $targetUri = route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]);

    $body = json_encode(['type' => 'Create', 'actor' => $this->remoteActor->actor_url]);

    $headers = buildRfc9421Headers(
        keyId: $this->remoteActor->actor_url.'#main-key',
        privateKey: $this->remoteKeys['private'],
        targetUri: $targetUri,
        body: $body,
        created: now()->timestamp,
        components: ['date'],
    );

    $response = sendRawPost(uri: $targetUri, body: $body, headers: $headers);

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Signature must cover a method and a request target component.']);
});

it('rejects a tampered RFC 9421 body via content digest', function (): void {
    $targetUri = route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]);

    $signedBody = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $this->remoteActor->actor_url,
        'object' => ['type' => 'Note', 'content' => 'Original'],
    ]);

    $headers = buildRfc9421Headers(
        keyId: $this->remoteActor->actor_url.'#main-key',
        privateKey: $this->remoteKeys['private'],
        targetUri: $targetUri,
        body: $signedBody,
        created: now()->timestamp,
    );

    $tamperedBody = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $this->remoteActor->actor_url,
        'object' => ['type' => 'Note', 'content' => 'Tampered'],
    ]);

    $response = sendRawPost(uri: $targetUri, body: $tamperedBody, headers: $headers);

    $response->assertStatus(status: 401);
    $response->assertJson(['error' => 'Content digest does not match body.']);
});

it('rejects replayed RFC 9421 requests', function (): void {
    $targetUri = route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]);

    $body = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $this->remoteActor->actor_url,
        'object' => ['type' => 'Note', 'content' => 'Replay me'],
    ]);

    $headers = buildRfc9421Headers(
        keyId: $this->remoteActor->actor_url.'#main-key',
        privateKey: $this->remoteKeys['private'],
        targetUri: $targetUri,
        body: $body,
        created: now()->timestamp,
    );

    $first = sendRawPost(uri: $targetUri, body: $body, headers: $headers);
    $first->assertStatus(status: 202);

    $second = sendRawPost(uri: $targetUri, body: $body, headers: $headers);
    $second->assertStatus(status: 401);
    $second->assertJson(['error' => 'Duplicate request detected.']);
});

it('does not consume the replay slot for a request rejected before verification', function (): void {
    // Never queue a background fetch; the actor is added inline below.
    config()->set('activitypub.http_signatures.queue_unknown_actors', false);

    $keys = generateTestKeyPair();
    $actorUrl = 'https://remote.example.com/users/late';
    $targetUri = route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]);

    $body = json_encode([
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Create',
        'actor' => $actorUrl,
        'object' => ['type' => 'Note', 'content' => 'Retry me'],
    ]);

    $headers = buildRfc9421Headers(
        keyId: $actorUrl.'#main-key',
        privateKey: $keys['private'],
        targetUri: $targetUri,
        body: $body,
        created: now()->timestamp,
    );

    // The actor is unknown, so the request is rejected before the signature is
    // verified. It must not burn the replay slot for this signature.
    $first = sendRawPost(uri: $targetUri, body: $body, headers: $headers);
    $first->assertStatus(status: 401);
    $first->assertJson(['error' => 'Unknown remote actor.']);

    RemoteActor::query()->create(attributes: [
        'actor_url' => $actorUrl,
        'inbox_url' => $actorUrl.'/inbox',
        'username' => 'late',
        'domain' => 'remote.example.com',
        'public_key_pem' => $keys['public'],
    ]);

    // The byte-identical retry now verifies and is accepted.
    $second = sendRawPost(uri: $targetUri, body: $body, headers: $headers);
    $second->assertStatus(status: 202);
});
