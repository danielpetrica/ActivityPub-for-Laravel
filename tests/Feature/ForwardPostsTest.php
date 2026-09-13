<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Jobs\ForwardPostsToNewServerJob;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

class FederatablePost extends Model implements FederatableContentContract
{
    use FederatesContent;

    protected $table = 'activities';
    protected $fillable = [
        'actor_id', 'type', 'object_type', 'object_id', 'payload',
        'status', 'delivered_at', 'remote_actor_id', 'is_incoming', 'debug',
    ];

    public $timestamps = true;

    public function shouldFederate(): bool
    {
        return true;
    }

    public function isActivityPubPinned(): bool
    {
        return false;
    }

    public function activityPubActor(): \DanielPetrica\LaravelActivityPub\Contracts\ActorContract
    {
        return Actor::first();
    }

    public function getActivityPubId(): string
    {
        return url('/test/'.$this->id);
    }

    public function getActivityPubType(): string
    {
        return 'Note';
    }

    public function getActivityPubName(): ?string
    {
        return null;
    }

    public function getActivityPubContent(): string
    {
        return 'Test content #'.$this->id;
    }

    public function getActivityPubSummary(): ?string
    {
        return null;
    }

    public function getActivityPubUrl(): string
    {
        return url('/test/'.$this->id);
    }

    public function getActivityPubPublishedAt(): string
    {
        return $this->created_at->toIso8601String();
    }

    public function getActivityPubAttributedTo(): string
    {
        return $this->actor_id ? url('/users/'.Actor::find($this->actor_id)?->username) : '';
    }

    public function getActivityPubTo(): string
    {
        return 'https://www.w3.org/ns/activitystreams#Public';
    }

    public function getActivityPubCc(): string
    {
        return '';
    }

    public function getActivityPubAttachments(): array
    {
        return [];
    }

    public function getActivityPubTags(): array
    {
        return [];
    }
}

class NeverFederatePost extends FederatablePost
{
    public function shouldFederate(): bool
    {
        return false;
    }
}

class TestUser extends Authenticatable implements ActorContract
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
        return $this->getActorId().'#main-key';
    }

    public function getPrivateKeyPem(): ?string
    {
        return null;
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
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

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
});

it('does nothing when no federatable models are configured', function (): void {
    config()->set('activitypub.federatable_models', []);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    Bus::fake();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    Bus::assertNotDispatched(DeliverActivity::class);
});

it('forwards newest published posts to new follower', function (): void {
    config()->set('activitypub.federatable_models', [FederatablePost::class]);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/bob',
        'inbox_url' => 'https://mastodon.social/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'mastodon.social',
    ]);

    for ($i = 0; $i < 3; $i++) {
        Activity::query()->create([
            'actor_id' => $this->actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => url('/posts/'.$i),
            'payload' => ['@context' => 'https://www.w3.org/ns/activitystreams', 'type' => 'Create'],
            'status' => 'delivered',
            'is_incoming' => false,
            'created_at' => now()->subDays(3 - $i),
        ]);
    }

    $beforeCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    $afterCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    expect($afterCount - $beforeCount)->toBe(3);
});

it('forwards newest posts when more than maxPosts exist', function (): void {
    config()->set('activitypub.federatable_models', [FederatablePost::class]);
    config()->set('activitypub.federation.enabled', true);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/dave',
        'inbox_url' => 'https://mastodon.social/users/dave/inbox',
        'username' => 'dave',
        'domain' => 'mastodon.social',
    ]);

    // Create 5 posts: oldest (5 days ago) to newest (1 day ago)
    for ($i = 0; $i < 5; $i++) {
        Activity::query()->create([
            'actor_id' => $this->actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => url('/posts/'.$i),
            'payload' => ['@context' => 'https://www.w3.org/ns/activitystreams', 'type' => 'Create'],
            'status' => 'delivered',
            'is_incoming' => false,
            'created_at' => now()->subDays(5 - $i),
        ]);
    }

    Bus::fake();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    // Should forward 3 posts (newest)
    Bus::assertDispatched(DeliverActivity::class, 3);
});

it('forwards only 3 posts by default', function (): void {
    config()->set('activitypub.federatable_models', [FederatablePost::class]);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/charlie',
        'inbox_url' => 'https://mastodon.social/users/charlie/inbox',
        'username' => 'charlie',
        'domain' => 'mastodon.social',
    ]);

    for ($i = 0; $i < 5; $i++) {
        Activity::query()->create([
            'actor_id' => $this->actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => url('/posts/'.$i),
            'payload' => ['@context' => 'https://www.w3.org/ns/activitystreams', 'type' => 'Create'],
            'status' => 'delivered',
            'is_incoming' => false,
            'created_at' => now()->subDays(5 - $i),
        ]);
    }

    $beforeCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    $afterCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    expect($afterCount - $beforeCount)->toBe(3);
});

it('respects custom maxPosts parameter', function (): void {
    config()->set('activitypub.federatable_models', [FederatablePost::class]);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/dave',
        'inbox_url' => 'https://mastodon.social/users/dave/inbox',
        'username' => 'dave',
        'domain' => 'mastodon.social',
    ]);

    for ($i = 0; $i < 5; $i++) {
        Activity::query()->create([
            'actor_id' => $this->actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => url('/posts/'.$i),
            'payload' => ['@context' => 'https://www.w3.org/ns/activitystreams', 'type' => 'Create'],
            'status' => 'delivered',
            'is_incoming' => false,
            'created_at' => now()->subDays(5 - $i),
        ]);
    }

    $beforeCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
        maxPosts: 2,
    );

    $job->handle(app(ActivityPubService::class));

    $afterCount = Activity::query()->where('actor_id', $this->actor->id)->where('type', 'Create')->where('is_incoming', false)->count();

    expect($afterCount - $beforeCount)->toBe(2);
});

it('skips posts where shouldFederate returns false', function (): void {
    config()->set('activitypub.federatable_models', [NeverFederatePost::class]);

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/eve',
        'inbox_url' => 'https://mastodon.social/users/eve/inbox',
        'username' => 'eve',
        'domain' => 'mastodon.social',
    ]);

    for ($i = 0; $i < 3; $i++) {
        Activity::query()->create([
            'actor_id' => $this->actor->id,
            'type' => 'Create',
            'object_type' => 'Note',
            'object_id' => url('/posts/'.$i),
            'payload' => ['@context' => 'https://www.w3.org/ns/activitystreams', 'type' => 'Create'],
            'status' => 'delivered',
            'is_incoming' => false,
            'created_at' => now()->subDays(3 - $i),
        ]);
    }

    Bus::fake();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: $remoteActor->id,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    Bus::assertNotDispatched(DeliverActivity::class);
});

it('handles missing remote actor gracefully', function (): void {
    config()->set('activitypub.federatable_models', [FederatablePost::class]);

    Bus::fake();

    $job = new ForwardPostsToNewServerJob(
        remoteActorId: 99999,
        actorId: $this->actor->id,
    );

    $job->handle(app(ActivityPubService::class));

    Bus::assertNotDispatched(DeliverActivity::class);
});

it('dispatches ForwardPostsToNewServerJob on first follower from domain', function (): void {
    Bus::fake();

    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/frank',
        'inbox_url' => 'https://mastodon.social/users/frank/inbox',
        'username' => 'frank',
        'domain' => 'mastodon.social',
    ]);

    $payload = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'type' => 'Follow',
        'actor' => $remoteActor->actor_url,
        'object' => $this->actor->actor_id,
        'id' => $remoteActor->actor_url.'#follow/1',
        'to' => [$this->actor->actor_id],
    ];

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: $payload,
    );

    $response->assertStatus(status: 202);

    Bus::assertDispatched(ForwardPostsToNewServerJob::class, function ($job) use ($remoteActor) {
        return $job->remoteActorId === $remoteActor->id
            && $job->actorId === $this->actor->id;
    });
});

it('does not dispatch ForwardPostsToNewServerJob on subsequent followers from same domain', function (): void {
    Bus::fake();

    $remoteActor1 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/grace',
        'inbox_url' => 'https://mastodon.social/users/grace/inbox',
        'username' => 'grace',
        'domain' => 'mastodon.social',
    ]);

    $remoteActor2 = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/hank',
        'inbox_url' => 'https://mastodon.social/users/hank/inbox',
        'username' => 'hank',
        'domain' => 'mastodon.social',
    ]);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActor1->actor_url,
            'object' => $this->actor->actor_id,
            'id' => $remoteActor1->actor_url.'#follow/1',
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(202);

    Bus::assertDispatched(ForwardPostsToNewServerJob::class);

    Bus::fake();

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => $remoteActor2->actor_url,
            'object' => $this->actor->actor_id,
            'id' => $remoteActor2->actor_url.'#follow/2',
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(202);

    Bus::assertNotDispatched(ForwardPostsToNewServerJob::class);
});

it('shows federated servers page', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'username' => 'testuser',
    ]);

    $response = $this->actingAs($user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
});

it('lists servers with follower counts', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'username' => 'testuser',
    ]);

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
        'status' => FollowerStatus::Accepted,
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

    $response = $this->actingAs($user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
    $response->assertSee('mastodon.social');
    $response->assertSee('infosec.exchange');
});

it('shows empty state when no followers', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'username' => 'testuser',
    ]);

    $response = $this->actingAs($user)->get(route('fediverse.servers'));

    $response->assertStatus(200);
    $response->assertDontSee('mastodon.social');
    $response->assertDontSee('infosec.exchange');
});

it('isActivityPubPinned returns false by default', function (): void {
    $post = new FederatablePost();

    expect($post->isActivityPubPinned())->toBeFalse();
});
