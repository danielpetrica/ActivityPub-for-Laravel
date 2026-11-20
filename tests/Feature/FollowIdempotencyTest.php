<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Following;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FollowIdempotencyUser extends Authenticatable implements ActorContract
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'username'];

    public function getPreferredUsername(): string
    {
        return $this->attributes['username'] ?? $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->name;
    }

    public function getSummary(): ?string
    {
        return null;
    }

    public function getIconUrl(): ?string
    {
        return null;
    }

    public function getHeaderImageUrl(): ?string
    {
        return null;
    }

    public function getProfileUrl(): string
    {
        return url('/users/'.$this->getPreferredUsername());
    }

    public function getActorId(): string
    {
        return url('/users/'.$this->getPreferredUsername());
    }

    public function getInboxUrl(): string
    {
        return $this->getActorId().'/inbox';
    }

    public function getOutboxUrl(): string
    {
        return $this->getActorId().'/outbox';
    }

    public function getFollowersUrl(): string
    {
        return $this->getActorId().'/followers';
    }

    public function getFollowingUrl(): string
    {
        return $this->getActorId().'/following';
    }

    public function getPublicKey(): string
    {
        return '';
    }

    public function getKeyId(): string
    {
        return $this->getActorId().'#main-key';
    }

    public function getPrivateKeyPem(): ?string
    {
        return null;
    }
}

beforeEach(function (): void {
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', true);
    config()->set('activitypub.security.allowed_hosts', ['mastodon.social', 'infosec.exchange']);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'followuser',
        'name' => 'Follow User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->user = FollowIdempotencyUser::query()->create(attributes: [
        'name' => 'Follow User',
        'email' => 'follow@example.com',
        'password' => bcrypt('password'),
        'username' => 'followuser',
    ]);

    fakeFederationHttp([
        'https://mastodon.social/.well-known/webfinger*' => Http::response(
            body: json_encode([
                'subject' => 'acct:friend@mastodon.social',
                'links' => [[
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => 'https://mastodon.social/users/friend',
                ]],
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/jrd+json'],
        ),
        'https://infosec.exchange/.well-known/webfinger*' => Http::response(
            body: json_encode([
                'subject' => 'acct:danielpetrica@infosec.exchange',
                'links' => [[
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => 'https://infosec.exchange/users/danielpetrica',
                ]],
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/jrd+json'],
        ),
        'https://mastodon.social/users/friend' => Http::response(
            body: json_encode([
                'id' => 'https://mastodon.social/users/friend',
                'type' => 'Person',
                'preferredUsername' => 'friend',
                'name' => 'Friend',
                'inbox' => 'https://mastodon.social/users/friend/inbox',
                'manuallyApprovesFollowers' => false,
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/activity+json'],
        ),
        'https://mastodon.social/users/locked' => Http::response(
            body: json_encode([
                'id' => 'https://mastodon.social/users/locked',
                'type' => 'Person',
                'preferredUsername' => 'locked',
                'name' => 'Locked',
                'inbox' => 'https://mastodon.social/users/locked/inbox',
                'manuallyApprovesFollowers' => true,
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/activity+json'],
        ),
        'https://infosec.exchange/users/danielpetrica' => Http::response(
            body: json_encode([
                'id' => 'https://infosec.exchange/users/danielpetrica',
                'type' => 'Person',
                'preferredUsername' => 'danielpetrica',
                'name' => 'Daniel Petrica',
                'inbox' => 'https://infosec.exchange/users/danielpetrica/inbox',
                'manuallyApprovesFollowers' => false,
            ]),
            status: 200,
            headers: ['Content-Type' => 'application/activity+json'],
        ),
    ]);
});

it('follows the same remote actor twice without an error, one row and one Follow activity', function (): void {
    Bus::fake();

    $payload = ['remote_actor_url' => 'https://mastodon.social/users/friend'];

    $this->actingAs($this->user)
        ->post(route('fediverse.follow'), $payload)
        ->assertRedirect(route('fediverse.following'))
        ->assertSessionHas('success', 'Following!');

    $this->actingAs($this->user)
        ->post(route('fediverse.follow'), $payload)
        ->assertRedirect(route('fediverse.following'))
        ->assertSessionHas('success', 'You are already following this account.');

    $remoteActor = RemoteActor::query()
        ->where('actor_url', '=', 'https://mastodon.social/users/friend')
        ->firstOrFail();

    $following = Following::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('remote_actor_id', '=', $remoteActor->id)
        ->get();

    expect($following)->toHaveCount(1)
        ->and($following->first()->status)->toBe(FollowerStatus::Accepted);

    expect(Activity::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('type', '=', 'Follow')
        ->where('is_incoming', '=', false)
        ->count())->toBe(1);

    Bus::assertDispatchedTimes(DeliverActivity::class, 1);
});

it('does not send a second Follow for a duplicate pending request', function (): void {
    Bus::fake();

    $payload = ['remote_actor_url' => 'https://mastodon.social/users/locked'];

    $this->actingAs($this->user)
        ->post(route('fediverse.follow'), $payload)
        ->assertRedirect(route('fediverse.following'))
        ->assertSessionHas('success', 'Follow request sent!');

    $this->actingAs($this->user)
        ->post(route('fediverse.follow'), $payload)
        ->assertRedirect(route('fediverse.following'))
        ->assertSessionHas('success', 'A follow request to this account is already pending.');

    $remoteActor = RemoteActor::query()
        ->where('actor_url', '=', 'https://mastodon.social/users/locked')
        ->firstOrFail();

    $following = Following::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('remote_actor_id', '=', $remoteActor->id)
        ->get();

    expect($following)->toHaveCount(1)
        ->and($following->first()->status)->toBe(FollowerStatus::Pending);

    expect(Activity::query()
        ->where('actor_id', '=', $this->actor->id)
        ->where('type', '=', 'Follow')
        ->where('is_incoming', '=', false)
        ->count())->toBe(1);

    Bus::assertDispatchedTimes(DeliverActivity::class, 1);
});

it('derives the resolved discover follow state from the following table after pruning', function (): void {
    $remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://mastodon.social/users/friend',
        'inbox_url' => 'https://mastodon.social/users/friend/inbox',
        'username' => 'friend',
        'domain' => 'mastodon.social',
    ]);

    Following::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    // The Follow activity was pruned, but the following row survived.
    expect(Activity::query()
        ->where('type', '=', 'Follow')
        ->where('is_incoming', '=', false)
        ->exists())->toBeFalse();

    $this->actingAs($this->user)
        ->post(route('fediverse.discover.resolve'), ['handle' => 'friend@mastodon.social'])
        ->assertOk()
        ->assertViewHas('isFollowing', true);
});

it('derives the default discover follow state from the following table after pruning', function (): void {
    $remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://infosec.exchange/users/danielpetrica',
        'inbox_url' => 'https://infosec.exchange/users/danielpetrica/inbox',
        'username' => 'danielpetrica',
        'domain' => 'infosec.exchange',
    ]);

    Following::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Pending,
    ]);

    expect(Activity::query()
        ->where('type', '=', 'Follow')
        ->where('is_incoming', '=', false)
        ->exists())->toBeFalse();

    $this->actingAs($this->user)
        ->get(route('fediverse.discover'))
        ->assertOk()
        ->assertViewHas('isFollowing', true);
});
