<?php

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\BlockedDomain;
use DanielPetrica\LaravelActivityPub\Models\BlockedRemoteActor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

class BlockingTestUser extends Authenticatable implements \DanielPetrica\LaravelActivityPub\Contracts\ActorContract
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

    public function getActorId(): string
    {
        return url('/users/'.$this->getPreferredUsername());
    }

    public function getInboxUrl(): string
    {
        return url('/users/'.$this->getPreferredUsername().'/inbox');
    }

    public function getOutboxUrl(): string
    {
        return url('/users/'.$this->getPreferredUsername().'/outbox');
    }

    public function getFollowersUrl(): string
    {
        return url('/users/'.$this->getPreferredUsername().'/followers');
    }

    public function getFollowingUrl(): string
    {
        return url('/users/'.$this->getPreferredUsername().'/following');
    }

    public function getPublicKey(): string
    {
        return '';
    }

    public function getKeyId(): string
    {
        return '';
    }

    public function getPrivateKeyPem(): ?string
    {
        return null;
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
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
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->user = BlockingTestUser::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'username' => 'testuser',
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
});

it('blocks a domain', function (): void {
    $response = $this->actingAs($this->user)->post(route('fediverse.servers.block-domain'), [
        'domain' => 'blocked.example.com',
    ]);

    $response->assertRedirect(route('fediverse.servers'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas(table: 'blocked_domains', data: [
        'domain' => 'blocked.example.com',
    ]);
});

it('unblocks a domain', function (): void {
    BlockedDomain::query()->create(['domain' => 'blocked.example.com']);

    $response = $this->actingAs($this->user)->delete(route('fediverse.servers.unblock-domain'), [
        'domain' => 'blocked.example.com',
    ]);

    $response->assertRedirect(route('fediverse.servers'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing(table: 'blocked_domains', data: [
        'domain' => 'blocked.example.com',
    ]);
});

it('blocks a remote actor', function (): void {
    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/badactor',
        'inbox_url' => 'https://mastodon.social/users/badactor/inbox',
        'username' => 'badactor',
        'domain' => 'mastodon.social',
    ]);

    $response = $this->actingAs($this->user)->post(route('fediverse.servers.block-actor'), [
        'remote_actor_id' => $remoteActor->id,
    ]);

    $response->assertRedirect(route('fediverse.followers'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas(table: 'blocked_remote_actors', data: [
        'remote_actor_id' => $remoteActor->id,
    ]);
});

it('unblocks a remote actor', function (): void {
    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/badactor',
        'inbox_url' => 'https://mastodon.social/users/badactor/inbox',
        'username' => 'badactor',
        'domain' => 'mastodon.social',
    ]);

    BlockedRemoteActor::query()->create(['remote_actor_id' => $remoteActor->id]);

    $response = $this->actingAs($this->user)->delete(route('fediverse.servers.unblock-actor'), [
        'remote_actor_id' => $remoteActor->id,
    ]);

    $response->assertRedirect(route('fediverse.followers'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing(table: 'blocked_remote_actors', data: [
        'remote_actor_id' => $remoteActor->id,
    ]);
});

it('removes followers when domain is blocked', function (): void {
    $remoteActor1 = RemoteActor::query()->create([
        'actor_url' => 'https://blocked.example.com/users/alice',
        'inbox_url' => 'https://blocked.example.com/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'blocked.example.com',
    ]);

    $remoteActor2 = RemoteActor::query()->create([
        'actor_url' => 'https://other.example.com/users/bob',
        'inbox_url' => 'https://other.example.com/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'other.example.com',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor1->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor2->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->post(route('fediverse.servers.block-domain'), [
        'domain' => 'blocked.example.com',
    ]);

    $response->assertRedirect(route('fediverse.servers'));

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor1->id,
    ]);

    $this->assertDatabaseHas(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor2->id,
    ]);
});

it('removes follower when actor is blocked', function (): void {
    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/badactor',
        'inbox_url' => 'https://mastodon.social/users/badactor/inbox',
        'username' => 'badactor',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->post(route('fediverse.servers.block-actor'), [
        'remote_actor_id' => $remoteActor->id,
    ]);

    $response->assertRedirect(route('fediverse.followers'));

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
    ]);
});

it('auto-rejects follow from blocked domain', function (): void {
    Bus::fake();

    RemoteActor::query()->create([
        'actor_url' => 'https://blocked.example.com/users/alice',
        'inbox_url' => 'https://blocked.example.com/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'blocked.example.com',
    ]);

    BlockedDomain::query()->create(['domain' => 'blocked.example.com']);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://blocked.example.com/users/alice',
            'object' => $this->actor->actor_id,
            'id' => 'https://blocked.example.com/users/alice#follow/99999',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
    ]);

    $rejectRecord = Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('type', 'Reject')
        ->where('is_incoming', false)
        ->first();

    expect($rejectRecord)->not->toBeNull();
    expect($rejectRecord->payload['type'])->toBe('Reject');
    expect($rejectRecord->payload['object']['type'])->toBe('Follow');
    expect($rejectRecord->payload['object']['actor'])->toBe('https://blocked.example.com/users/alice');

    Bus::assertDispatched(DeliverActivity::class, function ($job) {
        return $job->inboxUrl === 'https://blocked.example.com/users/alice/inbox';
    });
});

it('auto-rejects follow from blocked actor', function (): void {
    Bus::fake();

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/badactor',
        'inbox_url' => 'https://mastodon.social/users/badactor/inbox',
        'username' => 'badactor',
        'domain' => 'mastodon.social',
    ]);

    BlockedRemoteActor::query()->create(['remote_actor_id' => $remoteActor->id]);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActor->actor_url,
            'object' => $this->actor->actor_id,
            'id' => $remoteActor->actor_url.'#follow/88888',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseMissing(table: 'followers', data: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor->id,
    ]);

    $rejectRecord = Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('type', 'Reject')
        ->where('is_incoming', false)
        ->first();

    expect($rejectRecord)->not->toBeNull();
    expect($rejectRecord->payload['type'])->toBe('Reject');
    expect($rejectRecord->payload['object']['type'])->toBe('Follow');
    expect($rejectRecord->payload['object']['actor'])->toBe($remoteActor->actor_url);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($remoteActor) {
        return $job->inboxUrl === $remoteActor->inbox_url;
    });
});

it('allows follow from unblocked domain', function (): void {
    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://mastodon.social/users/goodactor',
            'object' => $this->actor->actor_id,
            'id' => 'https://mastodon.social/users/goodactor#follow/77777',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $remoteActor = RemoteActor::where('actor_url', 'https://mastodon.social/users/goodactor')->first();
    expect($remoteActor)->not->toBeNull();

    $follower = Follower::where('actor_id', $this->actor->id)
        ->where('remote_actor_id', $remoteActor->id)
        ->first();

    expect($follower)->not->toBeNull();
    expect($follower->status)->toBe(FollowerStatus::Accepted);

    $rejectRecord = Activity::query()
        ->where('actor_id', $this->actor->id)
        ->where('type', 'Reject')
        ->where('is_incoming', false)
        ->first();

    expect($rejectRecord)->toBeNull();
});

it('filters followers by domain', function (): void {
    $remoteActor1 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $remoteActor2 = RemoteActor::query()->create([
        'actor_url' => 'https://infosec.exchange/users/bob',
        'inbox_url' => 'https://infosec.exchange/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'infosec.exchange',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor1->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor2->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.followers', ['domain' => 'mastodon.social']));

    $response->assertStatus(200);
    $response->assertSee('mastodon.social');
    $response->assertDontSee('infosec.exchange');
});

it('filters followers by status', function (): void {
    $remoteActor1 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $remoteActor2 = RemoteActor::query()->create([
        'actor_url' => 'https://infosec.exchange/users/bob',
        'inbox_url' => 'https://infosec.exchange/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'infosec.exchange',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor1->id,
        'status' => FollowerStatus::Pending,
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor2->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.followers', ['status' => 'pending']));

    $response->assertStatus(200);
    $response->assertSee('alice');
    $response->assertDontSee('bob');
});

it('combines domain and status filters', function (): void {
    $remoteActor1 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    $remoteActor2 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/bob',
        'inbox_url' => 'https://mastodon.social/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'mastodon.social',
    ]);

    $remoteActor3 = RemoteActor::query()->create([
        'actor_url' => 'https://infosec.exchange/users/charlie',
        'inbox_url' => 'https://infosec.exchange/users/charlie/inbox',
        'username' => 'charlie',
        'domain' => 'infosec.exchange',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor1->id,
        'status' => FollowerStatus::Pending,
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor2->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $remoteActor3->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.followers', [
        'domain' => 'mastodon.social',
        'status' => 'pending',
    ]));

    $response->assertStatus(200);
    $response->assertSee('alice');
    $response->assertDontSee('bob');
    $response->assertDontSee('charlie');
});

it('shows blocked status on servers page', function (): void {
    BlockedDomain::query()->create(['domain' => 'blocked.example.com']);

    RemoteActor::query()->create([
        'actor_url' => 'https://blocked.example.com/users/alice',
        'inbox_url' => 'https://blocked.example.com/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'blocked.example.com',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => RemoteActor::where('actor_url', 'https://blocked.example.com/users/alice')->first()->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
    $response->assertSee('blocked.example.com');
    $response->assertSee('Blocked');
});

it('shows block button for unblocked domains', function (): void {
    RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => RemoteActor::where('actor_url', 'https://mastodon.social/users/alice')->first()->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
    $response->assertSee('mastodon.social');
    $response->assertSee('Block');
});

it('shows unblock button for blocked domains', function (): void {
    BlockedDomain::query()->create(['domain' => 'blocked.example.com']);

    RemoteActor::query()->create([
        'actor_url' => 'https://blocked.example.com/users/alice',
        'inbox_url' => 'https://blocked.example.com/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'blocked.example.com',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => RemoteActor::where('actor_url', 'https://blocked.example.com/users/alice')->first()->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
    $response->assertSee('blocked.example.com');
    $response->assertSee('Unblock');
});
