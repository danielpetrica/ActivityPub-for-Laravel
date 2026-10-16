<?php

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Http\Resources\ActorResource;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class ActorUpdateMoveUser extends Authenticatable implements ActorContract
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
    config()->set('activitypub.security.allowed_hosts', ['mastodon.social', 'newserver.example']);

    Http::fake(['*' => Http::response(body: '', status: 404)]);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'identityuser',
        'name' => 'Identity User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->remote = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://mastodon.social/users/follower',
        'inbox_url' => 'https://mastodon.social/users/follower/inbox',
        'username' => 'follower',
        'domain' => 'mastodon.social',
    ]);

    Follower::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remote->id,
        'status' => FollowerStatus::Accepted,
    ]);
});

it('emits an Update(Person) and delivers it to followers', function (): void {
    Bus::fake();

    app(ActivityPubService::class)->updateActorProfile(actor: $this->actor);

    $update = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Update')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($update)->not->toBeNull()
        ->and($update->payload['type'])->toBe('Update')
        ->and($update->payload['object']['type'])->toBe('Person')
        ->and($update->payload['object']['id'])->toBe($this->actor->actor_id);

    Bus::assertDispatched(DeliverActivity::class, function ($job): bool {
        return $job->inboxUrl === $this->remote->inbox_url;
    });
});

it('includes alsoKnownAs in the actor resource and the Update payload', function (): void {
    $this->actor->update(attributes: [
        'also_known_as' => ['https://mastodon.social/@old', 'https://other.example/users/alias'],
    ]);

    $resource = (new ActorResource(resource: $this->actor))->toArray(request: Request::create(uri: '/'));

    expect($resource['alsoKnownAs'])->toBe([
        'https://mastodon.social/@old',
        'https://other.example/users/alias',
    ]);

    Bus::fake();
    app(ActivityPubService::class)->updateActorProfile(actor: $this->actor->fresh());

    $update = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Update')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($update->payload['object']['alsoKnownAs'])
        ->toBe(['https://mastodon.social/@old', 'https://other.example/users/alias']);
});

it('federates the profile Update when the profile screen is saved', function (): void {
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

    $user = ActorUpdateMoveUser::query()->create(attributes: [
        'name' => 'Identity User',
        'email' => 'identity@example.com',
        'password' => bcrypt('password'),
        'username' => 'identityuser',
    ]);

    Bus::fake();

    $this->actingAs($user)
        ->post(route('fediverse.profile.update'), [
            'name' => 'Renamed',
            'summary' => 'New bio',
        ])
        ->assertRedirect(route('fediverse.profile'));

    expect($this->actor->fresh()->name)->toBe('Renamed');

    $update = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Update')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($update)->not->toBeNull()
        ->and($update->payload['type'])->toBe('Update');
});

it('builds a Move activity with the destination and aliases', function (): void {
    $activity = app(ActivityBuilder::class)->move(
        actor: $this->actor,
        targetUrl: 'https://newserver.example/users/identityuser',
        alsoKnownAs: ['https://mastodon.social/users/identityuser'],
    );

    expect($activity['type'])->toBe('Move')
        ->and($activity['object'])->toBe($this->actor->actor_id)
        ->and($activity['target'])->toBe('https://newserver.example/users/identityuser')
        ->and($activity['alsoKnownAs'])->toBe(['https://mastodon.social/users/identityuser'])
        ->and($activity['to'])->toContain($this->actor->followers_url);
});

it('records and delivers an outbound Move to followers', function (): void {
    Bus::fake();

    app(ActivityPubService::class)->sendMove(
        actor: $this->actor,
        targetUrl: 'https://newserver.example/users/identityuser',
        alsoKnownAs: ['https://mastodon.social/users/identityuser'],
    );

    $move = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Move')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($move)->not->toBeNull()
        ->and($move->payload['type'])->toBe('Move')
        ->and($move->payload['target'])->toBe('https://newserver.example/users/identityuser');

    Bus::assertDispatched(DeliverActivity::class, function ($job): bool {
        return $job->inboxUrl === $this->remote->inbox_url;
    });
});

it('records an inbound Move and stores the movedTo target on the remote actor', function (): void {
    app(InboxProcessor::class)->process(payload: [
        'type' => 'Move',
        'id' => 'https://mastodon.social/users/follower#moves/1',
        'actor' => $this->remote->actor_url,
        'object' => $this->remote->actor_url,
        'target' => 'https://newserver.example/users/follower',
        'alsoKnownAs' => ['https://mastodon.social/users/follower-old'],
        'to' => [$this->actor->actor_id],
    ]);

    $this->remote->refresh();

    expect($this->remote->moved_to)->toBe('https://newserver.example/users/follower')
        ->and($this->remote->also_known_as)->toBe(['https://mastodon.social/users/follower-old']);

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Move')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->exists())->toBeTrue();
});

it('records an inbound Move defensively when the target is missing', function (): void {
    app(InboxProcessor::class)->process(payload: [
        'type' => 'Move',
        'actor' => 'https://mastodon.social/users/stranger',
        'object' => 'https://mastodon.social/users/stranger',
        'to' => [$this->actor->actor_id],
    ]);

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Move')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->exists())->toBeTrue();
});
