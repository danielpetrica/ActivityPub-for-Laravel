<?php

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.security.allowed_hosts', [
        'allowed.example',
        'blocked.example',
        'quota.example',
    ]);

    Http::fake(['*' => Http::response(body: '', status: 404)]);

    Cache::flush();

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'allowlistuser',
        'name' => 'Allowlist User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->like = function (string $host, int $index = 0): void {
        app(InboxProcessor::class)->process(payload: [
            'type' => 'Like',
            'actor' => 'https://'.$host.'/users/sender',
            'object' => 'https://'.$host.'/notes/'.$index,
            'to' => [$this->actor->actor_id],
        ]);
    };
});

it('drops activities from a domain that is not on the allowlist when enabled', function (): void {
    config()->set('activitypub.inbox.allowlist.enabled', true);
    config()->set('activitypub.inbox.allowlist.domains', ['allowed.example']);

    ($this->like)('blocked.example');

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->exists())->toBeFalse();
});

it('accepts activities from an allowlisted domain when enabled', function (): void {
    config()->set('activitypub.inbox.allowlist.enabled', true);
    config()->set('activitypub.inbox.allowlist.domains', ['allowed.example']);

    ($this->like)('allowed.example');

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->exists())->toBeTrue();
});

it('matches allowlisted domains through normalization (www and casing)', function (): void {
    config()->set('activitypub.inbox.allowlist.enabled', true);
    config()->set('activitypub.inbox.allowlist.domains', ['WWW.Allowed.Example.']);

    ($this->like)('allowed.example');

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->exists())->toBeTrue();
});

it('ignores the allowlist when it is enabled but empty', function (): void {
    config()->set('activitypub.inbox.allowlist.enabled', true);
    config()->set('activitypub.inbox.allowlist.domains', []);

    ($this->like)('blocked.example');

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->exists())->toBeTrue();
});

it('drops activities that exceed the per-actor inbox quota', function (): void {
    config()->set('activitypub.inbox.quotas.enabled', true);
    config()->set('activitypub.inbox.quotas.per_actor_per_minute', 2);
    config()->set('activitypub.inbox.quotas.per_domain_per_minute', 0);
    config()->set('activitypub.inbox.quotas.per_actor_per_day', 0);

    foreach (range(0, 4) as $index) {
        ($this->like)('quota.example', $index);
    }

    // The first two are accepted; every later delivery is over the cap.
    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->count())->toBe(2);
});

it('applies quotas independently per actor', function (): void {
    config()->set('activitypub.inbox.quotas.enabled', true);
    config()->set('activitypub.inbox.quotas.per_actor_per_minute', 1);
    config()->set('activitypub.inbox.quotas.per_domain_per_minute', 0);
    config()->set('activitypub.inbox.quotas.per_actor_per_day', 0);

    $a = function (string $actorUrl, int $index): void {
        app(InboxProcessor::class)->process(payload: [
            'type' => 'Like',
            'actor' => $actorUrl,
            'object' => 'https://quota.example/notes/'.$index,
            'to' => [$this->actor->actor_id],
        ]);
    };

    $a('https://quota.example/users/one', 1);
    $a('https://quota.example/users/one', 2);
    $a('https://quota.example/users/two', 3);

    // One per actor: actor "one" is capped after its first, "two" is allowed.
    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Like')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->count())->toBe(2);
});
