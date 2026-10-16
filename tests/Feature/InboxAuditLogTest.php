<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;

class InboxAuditUser extends Authenticatable implements ActorContract
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
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'testuser',
        'name' => 'Test User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.federation.enabled', false);
});

it('records a rejected inbox request even when debug display is off', function (): void {
    expect(config('activitypub.debug_display'))->toBeFalse();

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: ['type' => 'Create', 'actor' => 'https://example.com/users/remote'],
    )->assertStatus(status: 401);

    $log = InboxLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->status_code)->toBe(401)
        ->and($log->actor_id)->toBe($this->actor->id)
        ->and($log->method)->toBe('POST');
});

it('records shared inbox rejections without inventing a foreign key', function (): void {
    $this->postJson(
        uri: route(name: 'activitypub.shared-inbox'),
        data: ['type' => 'Create', 'actor' => 'https://example.com/users/remote'],
    )->assertStatus(status: 401);

    $log = InboxLog::query()->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->status_code)->toBe(401)
        ->and($log->actor_id)->toBeNull();
});

it('logs validation failures with the validation status code', function (): void {
    config()->set('activitypub.http_signatures.enabled', false);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: ['actor' => 'https://example.com/users/remote'],
    )->assertStatus(status: 422);

    expect(InboxLog::query()->where('status_code', '=', 422)->exists())->toBeTrue();
});

it('never lets an audit failure mask the real rejection response', function (): void {
    Schema::drop('inbox_logs');

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: ['type' => 'Create', 'actor' => 'https://example.com/users/remote'],
    )->assertStatus(status: 401);
});

it('shows rejected requests in the fediverse inbox view', function (): void {
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

    $user = InboxAuditUser::query()->create([
        'name' => 'Operator',
        'email' => 'operator@example.com',
        'password' => bcrypt('password'),
        'username' => 'testuser',
    ]);

    InboxLog::query()->create([
        'actor_id' => $this->actor->id,
        'actor_url' => 'https://bad.example.com/users/mallory',
        'key_id' => 'https://bad.example.com/users/mallory#main-key',
        'ip' => '203.0.113.10',
        'method' => 'POST',
        'path' => 'users/testuser/inbox',
        'status_code' => 401,
        'reason' => 'Unique audit trail marker',
        'body_summary' => '{"type":"Create"}',
        'headers' => ['date' => 'now'],
    ]);

    $this->actingAs($user)
        ->get(route('fediverse.inbox'))
        ->assertOk()
        ->assertSee('Rejected Requests')
        ->assertSee('Unique audit trail marker')
        ->assertSee('bad.example.com');
});
