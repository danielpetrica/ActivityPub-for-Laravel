<?php

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Events\FlagReceived;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Report;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FlagModerationUser extends Authenticatable implements ActorContract
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
    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.security.allowed_hosts', ['remote.example', 'unknown.invalid']);

    Http::fake(['*' => Http::response(body: '', status: 404)]);

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'moderator',
        'name' => 'Moderator',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

it('records an inbound Flag as a Report, activity log entry and event', function (): void {
    Event::fake([FlagReceived::class]);

    app(InboxProcessor::class)->process(payload: [
        'type' => 'Flag',
        'id' => 'https://remote.example/users/reporter#flags/1',
        'actor' => 'https://remote.example/users/reporter',
        'object' => 'https://remote.example/notes/1',
        'content' => 'This is spam',
        'to' => [$this->actor->actor_id],
    ]);

    $report = Report::query()->first();

    expect($report)->not->toBeNull()
        ->and($report->object_id)->toBe('https://remote.example/notes/1')
        ->and($report->reason)->toBe('This is spam')
        ->and($report->status)->toBe(Report::STATUS_OPEN)
        ->and($report->actor_id)->toBe($this->actor->id);

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Flag')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->exists())->toBeTrue();

    Event::assertDispatched(FlagReceived::class, function (FlagReceived $event): bool {
        return $event->objectId === 'https://remote.example/notes/1'
            && $event->reason === 'This is spam';
    });
});

it('records an embedded-object Flag target and its type', function (): void {
    app(InboxProcessor::class)->process(payload: [
        'type' => 'Flag',
        'actor' => 'https://remote.example/users/reporter',
        'object' => [
            'type' => 'Note',
            'id' => 'https://remote.example/notes/embedded',
        ],
        'content' => 'Abuse',
        'to' => [$this->actor->actor_id],
    ]);

    $report = Report::query()->first();

    expect($report)->not->toBeNull()
        ->and($report->object_type)->toBe('Note')
        ->and($report->object_id)->toBe('https://remote.example/notes/embedded');
});

it('records a Flag defensively when the object and actor cannot be resolved', function (): void {
    app(InboxProcessor::class)->process(payload: [
        'type' => 'Flag',
        'actor' => 'https://unknown.invalid/users/ghost',
        'object' => ['unexpected' => 'shape'],
        'to' => [$this->actor->actor_id],
    ]);

    $report = Report::query()->first();

    expect($report)->not->toBeNull()
        ->and($report->object_id)->toBeNull()
        ->and($report->object_type)->toBeNull();

    expect(Activity::query()
        ->where(column: 'type', operator: '=', value: 'Flag')
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->exists())->toBeTrue();
});

it('shows open reports on the Fediverse status page', function (): void {
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

    Report::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'object_type' => 'Note',
        'object_id' => 'https://remote.example/notes/reported',
        'reason' => 'Reported content',
        'status' => Report::STATUS_OPEN,
    ]);

    $user = FlagModerationUser::query()->create(attributes: [
        'name' => 'Moderator',
        'email' => 'moderator@example.com',
        'password' => bcrypt('password'),
        'username' => 'moderator',
    ]);

    $this->actingAs($user)
        ->get(route('fediverse.status'))
        ->assertOk()
        ->assertSee('https://remote.example/notes/reported');
});
