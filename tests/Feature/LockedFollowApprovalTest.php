<?php

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\ForwardPostsToNewServerJob;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LockedFollowApprovalUser extends Authenticatable implements ActorContract
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
    config()->set('activitypub.security.allowed_hosts', ['mastodon.social']);

    Http::fake(['*' => Http::response(body: '', status: 404)]);

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
        'username' => 'lockeduser',
        'name' => 'Locked User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
        'manually_approves_followers' => true,
    ]);

    $this->remote = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://mastodon.social/users/pending',
        'inbox_url' => 'https://mastodon.social/users/pending/inbox',
        'username' => 'pending',
        'domain' => 'mastodon.social',
    ]);

    $this->receiveFollow = function (): void {
        app(InboxProcessor::class)->process(payload: [
            'type' => 'Follow',
            'id' => 'https://mastodon.social/users/pending#follows/1',
            'actor' => $this->remote->actor_url,
            'object' => $this->actor->actor_id,
            'to' => [$this->actor->actor_id],
        ]);
    };
});

it('stores a pending follower and sends no Accept on a locked account', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    expect($follower->status)->toBe(FollowerStatus::Pending)
        ->and($follower->remote_actor_id)->toBe($this->remote->id);

    // No Accept was recorded or delivered: the request is waiting for approval.
    expect(Activity::query()->where(column: 'type', operator: '=', value: 'Accept')->exists())->toBeFalse();
    Bus::assertNotDispatched(DeliverActivity::class);

    // A pending follower must not receive forwarded posts before approval.
    Bus::assertNotDispatched(ForwardPostsToNewServerJob::class);
});

it('approves a pending follower, advances the status and delivers an Accept', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $approved = app(ActivityPubService::class)->approveFollower(actor: $this->actor, follower: $follower);

    expect($approved)->toBeTrue();
    expect($follower->fresh()->status)->toBe(FollowerStatus::Accepted);

    $accept = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Accept')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($accept)->not->toBeNull()
        ->and($accept->payload['type'])->toBe('Accept')
        ->and($accept->payload['object']['type'])->toBe('Follow')
        ->and($accept->payload['object']['id'])->toBe('https://mastodon.social/users/pending#follows/1');

    Bus::assertDispatched(DeliverActivity::class, function ($job): bool {
        return $job->inboxUrl === $this->remote->inbox_url;
    });

    // Approval turns the pending request into a real follower, so the recent
    // posts are forwarded only now.
    Bus::assertDispatched(ForwardPostsToNewServerJob::class, function ($job): bool {
        return $job->remoteActorId === $this->remote->id
            && $job->actorId === $this->actor->id;
    });
});

it('rejects a pending follower, removes the row and delivers a Reject', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $rejected = app(ActivityPubService::class)->rejectFollower(actor: $this->actor, follower: $follower);

    expect($rejected)->toBeTrue();

    expect(Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->exists())->toBeFalse();

    $reject = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Reject')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($reject)->not->toBeNull()
        ->and($reject->payload['type'])->toBe('Reject')
        ->and($reject->payload['object']['type'])->toBe('Follow');

    Bus::assertDispatched(DeliverActivity::class, function ($job): bool {
        return $job->inboxUrl === $this->remote->inbox_url;
    });
});

it('approves a pending follower through the Fediverse route', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $user = LockedFollowApprovalUser::query()->create(attributes: [
        'name' => 'Locked User',
        'email' => 'locked@example.com',
        'password' => bcrypt('password'),
        'username' => 'lockeduser',
    ]);

    $this->actingAs($user)
        ->post(route('fediverse.followers.approve', ['follower' => $follower->id]))
        ->assertRedirect(route('fediverse.followers'));

    expect($follower->fresh()->status)->toBe(FollowerStatus::Accepted);

    // The Fediverse approval action forwards posts through the same path.
    Bus::assertDispatched(ForwardPostsToNewServerJob::class);
});

it('rejects a pending follower through the Fediverse route', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $user = LockedFollowApprovalUser::query()->create(attributes: [
        'name' => 'Locked User',
        'email' => 'locked2@example.com',
        'password' => bcrypt('password'),
        'username' => 'lockeduser',
    ]);

    $this->actingAs($user)
        ->post(route('fediverse.followers.reject', ['follower' => $follower->id]))
        ->assertRedirect(route('fediverse.followers'));

    expect(Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->exists())->toBeFalse();
});

it('forbids another authenticated user from approving or rejecting a foreign follower', function (): void {
    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $intruder = LockedFollowApprovalUser::query()->create(attributes: [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => bcrypt('password'),
        'username' => 'intruder',
    ]);

    $this->actingAs($intruder)
        ->post(route('fediverse.followers.approve', ['follower' => $follower->id]))
        ->assertForbidden();

    expect($follower->fresh()->status)->toBe(FollowerStatus::Pending);

    $this->actingAs($intruder)
        ->post(route('fediverse.followers.reject', ['follower' => $follower->id]))
        ->assertForbidden();

    expect(Follower::query()->whereKey($follower->id)->exists())->toBeTrue();
});

it('allows an admin to approve a follower owned by another actor', function (): void {
    Bus::fake();

    ($this->receiveFollow)();

    $follower = Follower::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->firstOrFail();

    $admin = LockedFollowApprovalUser::query()->create(attributes: [
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'username' => 'fedadmin',
    ]);

    Gate::define(
        'activitypub.fediverse.admin',
        fn (LockedFollowApprovalUser $user): bool => $user->id === $admin->id,
    );

    $this->actingAs($admin)
        ->post(route('fediverse.followers.approve', ['follower' => $follower->id]))
        ->assertRedirect(route('fediverse.followers'));

    // The action is performed as the owning actor, not the admin's actor.
    expect($follower->fresh()->status)->toBe(FollowerStatus::Accepted);

    $accept = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Accept')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($accept)->not->toBeNull()
        ->and($accept->actor_id)->toBe($this->actor->id);
});

it('lists a pending follower with approve and reject controls', function (): void {
    ($this->receiveFollow)();

    $user = LockedFollowApprovalUser::query()->create(attributes: [
        'name' => 'Locked User',
        'email' => 'locked3@example.com',
        'password' => bcrypt('password'),
        'username' => 'lockeduser',
    ]);

    $this->actingAs($user)
        ->get(route('fediverse.followers'))
        ->assertOk()
        ->assertSee('Approve')
        ->assertSee('Reject')
        ->assertSee('pending');
});
