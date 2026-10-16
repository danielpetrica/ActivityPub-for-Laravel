<?php

use DanielPetrica\LaravelActivityPub\Helpers\SsrfGuard;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('activitypub.security.allowed_hosts', []);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'ssrfguard',
        'name' => 'SSRF Guard',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

it('flags private, loopback, link-local and reserved IPv4 ranges', function (string $ip): void {
    expect(SsrfGuard::isPrivateIp($ip))->toBeTrue();
})->with([
    '0.0.0.0',
    '10.0.0.5',
    '100.64.0.1',
    '127.0.0.1',
    '169.254.169.254',
    '172.16.0.1',
    '172.31.255.255',
    '192.0.0.1',
    '192.0.2.1',
    '192.88.99.1',
    '192.168.1.1',
    '198.18.0.1',
    '198.19.255.255',
    '198.51.100.1',
    '203.0.113.1',
    '224.0.0.1',
    '240.0.0.1',
]);

it('flags private IPv6 ranges', function (string $ip): void {
    expect(SsrfGuard::isPrivateIp($ip))->toBeTrue();
})->with([
    '::1',
    '::',
    'fc00::1',
    'fd12:3456::1',
    'fe80::1',
    'ff02::1',
    '::ffff:127.0.0.1',
    '::ffff:10.0.0.1',
]);

it('allows public IP addresses', function (string $ip): void {
    expect(SsrfGuard::isPrivateIp($ip))->toBeFalse();
})->with([
    '8.8.8.8',
    '151.101.65.55',
    '2606:4700:4700::1111',
    '::ffff:8.8.8.8',
]);

it('refuses to deliver to HTTP and private or reserved targets', function (string $url): void {
    Http::fake();

    $client = app(DeliveryClient::class);

    $result = $client->deliver(
        inboxUrl: $url,
        activity: ['type' => 'Create'],
        actor: $this->actor,
    );

    expect($result)->toBeNull();
    Http::assertNothingSent();
})->with([
    'http scheme' => 'http://remote.example.com/users/x/inbox',
    'loopback ipv4' => 'https://127.0.0.1/users/x/inbox',
    'loopback ipv6' => 'https://[::1]/users/x/inbox',
    'link-local metadata' => 'https://169.254.169.254/latest/meta-data',
    'private 10/8' => 'https://10.1.2.3/users/x/inbox',
    'carrier-grade nat' => 'https://100.64.0.1/users/x/inbox',
    'unresolvable host' => 'https://remote.example.com/users/x/inbox',
]);

it('refuses to resolve remote actors from unsafe URLs', function (string $url): void {
    Http::fake();

    $resolver = app(RemoteActorResolver::class);

    expect($resolver->fetchActorData($url))->toBeNull();
    Http::assertNothingSent();
})->with([
    'http scheme' => 'http://example.com/users/x',
    'loopback' => 'https://127.0.0.1/users/x',
    'ipv6 loopback' => 'https://[::1]/users/x',
    'link-local' => 'https://169.254.169.254/latest/meta-data',
    'private 192.168/16' => 'https://192.168.0.10/users/x',
    'unresolvable host' => 'https://remote.example.com/users/x',
]);

it('allows an allow-listed host to bypass DNS resolution checks', function (): void {
    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);

    expect(SsrfGuard::isSafeUrl('https://remote.example.com/users/x'))->toBeTrue();
    // Allow-listing must not bypass private IP literals.
    expect(SsrfGuard::isSafeUrl('https://127.0.0.1/users/x'))->toBeFalse();
});

it('pins the connection to the validated IP instead of letting Guzzle re-resolve', function (): void {
    config()->set('activitypub.security.allowed_hosts', []);

    [$safe, $options] = SsrfGuard::validateAndPin(
        url: 'https://example.com/users/x/inbox',
        requireHttps: true,
        resolvedIps: ['93.184.216.34'],
    );

    expect($safe)->toBeTrue()
        ->and($options)->toBe(['curl' => [CURLOPT_RESOLVE => ['example.com:443:93.184.216.34']]]);
});

it('refuses to pin a private resolved IP (rebinding to a local target)', function (): void {
    config()->set('activitypub.security.allowed_hosts', []);

    [$safe, $options] = SsrfGuard::validateAndPin(
        url: 'https://rebind.example.com/users/x/inbox',
        requireHttps: true,
        resolvedIps: ['127.0.0.1'],
    );

    expect($safe)->toBeFalse()
        ->and($options)->toBe([]);
});

it('does not pin allow-listed hosts or literal IP URLs', function (): void {
    config()->set('activitypub.security.allowed_hosts', ['remote.example.com']);

    [$allowedSafe, $allowedOptions] = SsrfGuard::validateAndPin(url: 'https://remote.example.com/users/x');
    [$ipSafe, $ipOptions] = SsrfGuard::validateAndPin(url: 'https://93.184.216.34/users/x');

    expect($allowedSafe)->toBeTrue()
        ->and($allowedOptions)->toBe([])
        ->and($ipSafe)->toBeTrue()
        ->and($ipOptions)->toBe([]);
});

it('honours an explicit non-default port in the pinned resolution', function (): void {
    config()->set('activitypub.security.allowed_hosts', []);

    [$safe, $options] = SsrfGuard::validateAndPin(
        url: 'https://example.com:8443/users/x/inbox',
        requireHttps: true,
        resolvedIps: ['93.184.216.34'],
    );

    expect($safe)->toBeTrue()
        ->and($options)->toBe(['curl' => [CURLOPT_RESOLVE => ['example.com:8443:93.184.216.34']]]);
});

it('rejects malformed URLs', function (string $url): void {
    expect(SsrfGuard::isSafeUrl($url))->toBeFalse();
})->with([
    'empty' => '',
    'no host' => 'https:///users/x',
    'relative' => '/users/x',
    'garbage' => 'not a url',
]);
