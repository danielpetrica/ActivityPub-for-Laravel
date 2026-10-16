<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class FediverseAuthorizationUser extends Authenticatable implements ActorContract
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

    Actor::query()->create(attributes: [
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->admin = FediverseAuthorizationUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'username' => 'admin',
    ]);

    $this->member = FediverseAuthorizationUser::query()->create([
        'name' => 'Member',
        'email' => 'member@example.com',
        'password' => bcrypt('password'),
        'username' => 'member',
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);

    $this->grantAdmin = function (): void {
        Gate::define('activitypub.fediverse.admin', fn (FediverseAuthorizationUser $user): bool => $user->id === $this->admin->id);
    };
});

it('denies a non-admin authenticated user on a mutating route', function (): void {
    ($this->grantAdmin)();

    $this->actingAs($this->member)
        ->post(route('fediverse.servers.block-domain'), ['domain' => 'blocked.example.com'])
        ->assertForbidden();

    $this->assertDatabaseMissing('blocked_domains', ['domain' => 'blocked.example.com']);
});

it('allows a non-admin authenticated user on a read-only route', function (): void {
    ($this->grantAdmin)();

    $this->actingAs($this->member)
        ->get(route('fediverse.servers'))
        ->assertOk();
});

it('allows an admin to perform a mutating instance-wide action', function (): void {
    ($this->grantAdmin)();

    $this->actingAs($this->admin)
        ->post(route('fediverse.servers.block-domain'), ['domain' => 'blocked.example.com'])
        ->assertRedirect(route('fediverse.servers'));

    $this->assertDatabaseHas('blocked_domains', ['domain' => 'blocked.example.com']);
});

it('protects every mutating status route with the admin gate', function (): void {
    ($this->grantAdmin)();

    foreach (['fediverse.status.reschedule', 'fediverse.status.refresh-accounts', 'fediverse.status.prune', 'fediverse.status.forward-posts'] as $route) {
        $this->actingAs($this->member)
            ->post(route($route))
            ->assertForbidden();
    }
});

it('fails closed when no admin ability is defined', function (): void {
    $this->actingAs($this->admin)
        ->post(route('fediverse.servers.block-domain'), ['domain' => 'blocked.example.com'])
        ->assertForbidden();
});
