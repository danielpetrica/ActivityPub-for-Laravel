<?php

use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\InboxLog;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('creates an actor with valid options', function (): void {
    $this->artisan(command: 'activitypub:create-actor', parameters: [
        '--username' => 'newactor',
        '--name' => 'New Actor',
    ])->assertSuccessful();

    $actor = Actor::query()->where(column: 'username', operator: '=', value: 'newactor')->first();

    expect($actor)->not->toBeNull()
        ->and($actor->name)->toBe('New Actor')
        ->and($actor->public_key_pem)->toStartWith('-----BEGIN PUBLIC KEY-----')
        ->and($actor->private_key_pem)->toStartWith('-----BEGIN');
});

it('fails when username is already taken', function (): void {
    $keys = generateTestKeyPair();

    Actor::query()->create(attributes: [
        'username' => 'existing',
        'name' => 'Existing',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->artisan(command: 'activitypub:create-actor', parameters: [
        '--username' => 'existing',
        '--name' => 'Duplicate',
    ])->assertFailed();
});

it('fails when username contains invalid characters', function (): void {
    $this->artisan(command: 'activitypub:create-actor', parameters: [
        '--username' => 'invalid user!',
        '--name' => 'Invalid',
    ])->assertFailed();
});

it('registers the prune command on the daily scheduler without overlapping', function (): void {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn (Event $event): bool => str_contains(
            (string) $event->command,
            'activitypub:prune-activities',
        ));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('prunes delivered activities older than the retention window', function (): void {
    $keys = generateTestKeyPair();

    $actor = Actor::query()->create(attributes: [
        'username' => 'pruner',
        'name' => 'Pruner',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    Activity::query()->forceCreate([
        'actor_id' => $actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => url('/posts/old'),
        'payload' => ['type' => 'Create'],
        'status' => 'delivered',
        'is_incoming' => false,
        'created_at' => now()->subDays(60),
    ]);

    $this->artisan(command: 'activitypub:prune-activities', parameters: [
        '--days' => 30,
    ])->assertSuccessful();

    expect(Activity::query()->where(column: 'is_incoming', operator: '=', value: false)->count())->toBe(0);
});

it('prunes received activities older than the retention window but keeps recent ones', function (): void {
    $keys = generateTestKeyPair();

    $actor = Actor::query()->create(attributes: [
        'username' => 'received-pruner',
        'name' => 'Received Pruner',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $old = Activity::query()->forceCreate([
        'actor_id' => $actor->id,
        'type' => 'Like',
        'object_type' => 'Note',
        'object_id' => 'https://example.com/notes/old',
        'payload' => ['type' => 'Like'],
        'status' => 'delivered',
        'is_incoming' => true,
        'created_at' => now()->subDays(120),
    ]);

    $recent = Activity::query()->forceCreate([
        'actor_id' => $actor->id,
        'type' => 'Like',
        'object_type' => 'Note',
        'object_id' => 'https://example.com/notes/recent',
        'payload' => ['type' => 'Like'],
        'status' => 'delivered',
        'is_incoming' => true,
        'created_at' => now()->subDays(1),
    ]);

    $this->artisan(command: 'activitypub:prune-activities')->assertSuccessful();

    expect(Activity::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Activity::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('prunes inbox logs older than the retention window', function (): void {
    $old = InboxLog::query()->forceCreate([
        'actor_id' => null,
        'actor_url' => 'https://bad.example.com/users/mallory',
        'key_id' => null,
        'ip' => '203.0.113.10',
        'method' => 'POST',
        'path' => 'inbox',
        'status_code' => 401,
        'reason' => 'old rejected request',
        'body_summary' => null,
        'headers' => null,
        'created_at' => now()->subDays(60),
    ]);

    $this->artisan(command: 'activitypub:prune-activities', parameters: [
        '--inbox-logs-days' => 30,
    ])->assertSuccessful();

    expect(InboxLog::query()->whereKey($old->id)->exists())->toBeFalse();
});

it('prunes interactions older than the retention window', function (): void {
    $old = Interaction::query()->forceCreate([
        'type' => 'Like',
        'actor_id' => null,
        'remote_actor_id' => null,
        'object_type' => 'Note',
        'object_id' => 'https://example.com/notes/old',
        'status' => 'Active',
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan(command: 'activitypub:prune-activities', parameters: [
        '--interactions-days' => 90,
    ])->assertSuccessful();

    expect(Interaction::query()->whereKey($old->id)->exists())->toBeFalse();
});

it('does not delete anything on a dry run', function (): void {
    $keys = generateTestKeyPair();

    $actor = Actor::query()->create(attributes: [
        'username' => 'dry-runner',
        'name' => 'Dry Runner',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $activity = Activity::query()->forceCreate([
        'actor_id' => $actor->id,
        'type' => 'Like',
        'object_type' => 'Note',
        'object_id' => 'https://example.com/notes/old',
        'payload' => ['type' => 'Like'],
        'status' => 'delivered',
        'is_incoming' => true,
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan(command: 'activitypub:prune-activities', parameters: [
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Activity::query()->whereKey($activity->id)->exists())->toBeTrue();
});
