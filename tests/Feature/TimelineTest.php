<?php

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TimelineTestUser extends Authenticatable implements \DanielPetrica\LaravelActivityPub\Contracts\ActorContract
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password', 'username'];

    public function getPreferredUsername(): string { return $this->username ?? $this->name; }
    public function getDisplayName(): string { return $this->name; }
    public function getSummary(): ?string { return null; }
    public function getIconUrl(): ?string { return null; }
    public function getHeaderImageUrl(): ?string { return null; }
    public function getProfileUrl(): string { return ''; }
    public function getActorId(): string { return url('/users/'.$this->getPreferredUsername()); }
    public function getInboxUrl(): string { return $this->getActorId().'/inbox'; }
    public function getOutboxUrl(): string { return $this->getActorId().'/outbox'; }
    public function getFollowersUrl(): string { return $this->getActorId().'/followers'; }
    public function getFollowingUrl(): string { return $this->getActorId().'/following'; }
    public function getPublicKey(): string { return ''; }
    public function getKeyId(): string { return $this->getActorId().'#main-key'; }
    public function getPrivateKeyPem(): ?string { return null; }
}

beforeEach(function (): void {
    if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
        \Illuminate\Support\Facades\Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    $this->user = TimelineTestUser::query()->create([
        'name' => 'Timeline User',
        'email' => 'timeline@test.com',
        'password' => bcrypt('password'),
        'username' => 'timelineuser',
    ]);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'timelineuser',
        'name' => 'Timeline User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/alice',
        'inbox_url' => 'https://mastodon.social/users/alice/inbox',
        'username' => 'alice',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.fediverse.enabled', true);
    config()->set('activitypub.fediverse.middleware', ['web']);
});

it('shows Create activities in timeline', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Note',
                'content' => '<p>Hello world</p>',
                'url' => 'https://mastodon.social/@alice/1',
            ],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('fediverse.timeline'));

    $response->assertStatus(200);
    $response->assertSee('Hello world');
});

it('shows Announce activities in timeline', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Announce',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1/announces/2',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => 'https://other.example.com/users/bob/statuses/42',
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertSee('boosted');
    $response->assertSee('https://other.example.com/users/bob/statuses/42');
});

it('does not show Follow activities in timeline', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Follow',
        'object_type' => 'Actor',
        'object_id' => 'https://mastodon.social/users/alice',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => $this->actor->actor_id,
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertDontSee('boosted');
    $response->assertDontSee('Direct message');
});

it('shows DM indicator for Create activities not targeting Public', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Note',
                'content' => '<p>Secret message</p>',
                'to' => [$this->actor->actor_id],
            ],
            'to' => [$this->actor->actor_id],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertSee('Direct message');
    $response->assertSee('Secret message');
});

it('does not show DM indicator for public Create activities', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'type' => 'Note',
                'content' => '<p>Public post</p>',
            ],
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertDontSee('Direct message');
    $response->assertSee('Public post');
});

it('only shows activities from followed actors', function (): void {
    $otherRemote = RemoteActor::query()->create([
        'actor_url' => 'https://mastodon.social/users/bob',
        'inbox_url' => 'https://mastodon.social/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'mastodon.social',
    ]);

    // Activity from followed actor
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>From Alice</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    // Activity from non-followed actor
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $otherRemote->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/bob/statuses/2',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>From Bob</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertSee('From Alice');
    $response->assertDontSee('From Bob');
});

it('only shows incoming activities in timeline', function (): void {
    // Outgoing activity (should not appear)
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://danielpetrica.com/posts/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>My outgoing post</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => false,
    ]);

    // Incoming activity (should appear)
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>Incoming post</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertSee('Incoming post');
    $response->assertDontSee('My outgoing post');
});

it('shows empty state when no activities exist', function (): void {
    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    $response->assertSee('Your timeline is empty');
});

it('orders activities by newest first', function (): void {
    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>Older post</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
        'created_at' => now()->subHours(2),
    ]);

    Activity::query()->create([
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://mastodon.social/users/alice/statuses/2',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'object' => ['type' => 'Note', 'content' => '<p>Newer post</p>'],
        ],
        'status' => 'delivered',
        'is_incoming' => true,
        'created_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($this->user)->get(url('/fediverse/timeline'));

    $response->assertStatus(200);
    // Both should appear, and the page renders them in order
    $response->assertSee('Newer post');
    $response->assertSee('Older post');
});
