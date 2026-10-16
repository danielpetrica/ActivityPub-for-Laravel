<?php

use DanielPetrica\LaravelActivityPub\Enums\ActivityStatus;
use DanielPetrica\LaravelActivityPub\Events\ActivityDelivered;
use DanielPetrica\LaravelActivityPub\Events\ActivityDeliveryFailed;
use DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse\StatusController;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\ActivityDelivery;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\DeliveryClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'deliverypub',
        'name' => 'Delivery Publisher',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set(key: 'activitypub.federation.enabled', value: true);
    config()->set(key: 'activitypub.security.allowed_hosts', value: [
        'remote.example.com',
        'remote2.example.com',
    ]);
});

function createOutgoingActivity(Actor $actor): Activity
{
    return Activity::query()->create(attributes: [
        'actor_id' => $actor->id,
        'type' => 'Create',
        'object_type' => 'Note',
        'object_id' => 'https://localhost/notes/1',
        'payload' => [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://localhost/users/'.$actor->username,
        ],
        'status' => 'pending',
        'is_incoming' => false,
    ]);
}

function deliverTo(string $inboxUrl, Activity $activity, Actor $actor): void
{
    (new DeliverActivity(
        inboxUrl: $inboxUrl,
        activityModelId: $activity->id,
        actorId: $actor->id,
    ))->handle(app(DeliveryClient::class));
}

it('keeps a 5xx delivery pending and throws so the queue can retry it', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('server error', 500)]);
    Event::fake([ActivityDelivered::class, ActivityDeliveryFailed::class]);

    $activity = createOutgoingActivity($this->actor);

    expect(fn () => deliverTo('https://remote.example.com/users/a/inbox', $activity, $this->actor))
        ->toThrow(RuntimeException::class);

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_PENDING)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_code)->toBe(500)
        ->and($delivery->last_error)->not->toBeNull();

    expect($activity->fresh()->status)->toBe(ActivityStatus::Pending);

    // A recoverable attempt must never emit terminal events or fail the activity.
    Event::assertNotDispatched(ActivityDeliveryFailed::class);
    Event::assertNotDispatched(ActivityDelivered::class);
});

it('marks a per-inbox delivery and the activity delivered on a 2xx response', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('', 202)]);
    Event::fake([ActivityDelivered::class, ActivityDeliveryFailed::class]);

    $activity = createOutgoingActivity($this->actor);

    deliverTo('https://remote.example.com/users/a/inbox', $activity, $this->actor);

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_DELIVERED)
        ->and($delivery->response_code)->toBe(202)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->delivered_at)->not->toBeNull();

    $fresh = $activity->fresh();
    expect($fresh->status)->toBe(ActivityStatus::Delivered)
        ->and($fresh->delivered_at)->not->toBeNull();

    Event::assertDispatched(
        ActivityDelivered::class,
        fn (ActivityDelivered $event): bool => $event->inboxUrl === 'https://remote.example.com/users/a/inbox',
    );
    Event::assertNotDispatched(ActivityDeliveryFailed::class);
});

it('marks the activity delivered only after every inbox delivery succeeds', function (): void {
    Http::fake([
        'remote.example.com/users/a/inbox' => Http::response('', 202),
        'remote2.example.com/users/b/inbox' => Http::sequence()
            ->push('server error', 500)
            ->push('', 202),
    ]);

    $activity = createOutgoingActivity($this->actor);

    // Inbox B fails transiently first and stays pending.
    expect(fn () => deliverTo('https://remote2.example.com/users/b/inbox', $activity, $this->actor))
        ->toThrow(RuntimeException::class);

    expect($activity->fresh()->status)->toBe(ActivityStatus::Pending);

    // Inbox A succeeds, but B is still pending, so the activity is not delivered.
    deliverTo('https://remote.example.com/users/a/inbox', $activity, $this->actor);

    expect($activity->fresh()->status)->toBe(ActivityStatus::Pending);

    // Inbox B succeeds on retry; now every inbox is delivered.
    deliverTo('https://remote2.example.com/users/b/inbox', $activity, $this->actor);

    $fresh = $activity->fresh();

    expect($fresh->status)->toBe(ActivityStatus::Delivered)
        ->and($fresh->delivered_at)->not->toBeNull()
        ->and(ActivityDelivery::query()->where('status', '=', ActivityDelivery::STATUS_DELIVERED)->count())->toBe(2);
});

it('marks the per-inbox row and aggregate activity failed once retries are exhausted', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('server error', 500)]);
    Event::fake([ActivityDeliveryFailed::class]);

    $activity = createOutgoingActivity($this->actor);

    $job = new DeliverActivity(
        inboxUrl: 'https://remote.example.com/users/a/inbox',
        activityModelId: $activity->id,
        actorId: $this->actor->id,
    );

    expect(fn () => $job->handle(app(DeliveryClient::class)))
        ->toThrow(RuntimeException::class);

    expect(ActivityDelivery::query()->sole()->status)->toBe(ActivityDelivery::STATUS_PENDING);

    // The queue invokes failed() only once the job is genuinely terminal.
    $job->failed(new RuntimeException('attempts exhausted'));

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_FAILED)
        ->and($delivery->last_error)->toContain('attempts exhausted');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Failed);

    Event::assertDispatched(ActivityDeliveryFailed::class);
});

it('treats a terminal 4xx response as a permanent per-inbox failure', function (): void {
    Http::fake(['remote.example.com/*' => Http::response('gone', 410)]);

    $activity = createOutgoingActivity($this->actor);

    deliverTo('https://remote.example.com/users/a/inbox', $activity, $this->actor);

    $delivery = ActivityDelivery::query()->sole();

    expect($delivery->status)->toBe(ActivityDelivery::STATUS_FAILED)
        ->and($delivery->response_code)->toBe(410)
        ->and($delivery->last_error)->toContain('410');

    expect($activity->fresh()->status)->toBe(ActivityStatus::Failed);
});

it('releases a 5xx delivery back onto the queue instead of failing it permanently', function (): void {
    config()->set(key: 'queue.default', value: 'database');
    config()->set(key: 'cache.default', value: 'array');

    Schema::create(table: 'jobs', callback: static function (Blueprint $table): void {
        $table->id();
        $table->string(column: 'queue')->index();
        $table->longText(column: 'payload');
        $table->unsignedTinyInteger(column: 'attempts');
        $table->unsignedInteger(column: 'reserved_at')->nullable();
        $table->unsignedInteger(column: 'available_at');
        $table->unsignedInteger(column: 'created_at');
    });

    Schema::create(table: 'failed_jobs', callback: static function (Blueprint $table): void {
        $table->id();
        $table->string(column: 'uuid')->unique();
        $table->text(column: 'connection');
        $table->text(column: 'queue');
        $table->longText(column: 'payload');
        $table->longText(column: 'exception');
        $table->timestamp(column: 'failed_at')->useCurrent();
    });

    Http::fake(['remote.example.com/*' => Http::response('server error', 500)]);

    $activity = createOutgoingActivity($this->actor);

    DeliverActivity::dispatch(
        inboxUrl: 'https://remote.example.com/users/a/inbox',
        activityModelId: $activity->id,
        actorId: $this->actor->id,
    );

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--tries' => 3,
        '--queue' => 'default',
    ])->run();

    // The job was released for a later retry, not deleted or failed.
    expect(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);

    expect(ActivityDelivery::query()->sole()->status)->toBe(ActivityDelivery::STATUS_PENDING);
    expect($activity->fresh()->status)->toBe(ActivityStatus::Pending);
});

it('re-dispatches only failed inboxes when rescheduling', function (): void {
    Bus::fake();

    $activity = createOutgoingActivity($this->actor);

    ActivityDelivery::query()->create(attributes: [
        'activity_id' => $activity->id,
        'inbox_url' => 'https://remote.example.com/users/a/inbox',
        'status' => ActivityDelivery::STATUS_FAILED,
        'attempts' => 3,
        'response_code' => 500,
        'last_error' => 'server error',
    ]);

    ActivityDelivery::query()->create(attributes: [
        'activity_id' => $activity->id,
        'inbox_url' => 'https://remote2.example.com/users/b/inbox',
        'status' => ActivityDelivery::STATUS_DELIVERED,
        'attempts' => 1,
        'delivered_at' => now(),
    ]);

    (new StatusController)->reschedulePending(Request::create('/fediverse/status/reschedule', 'POST'));

    Bus::assertDispatched(DeliverActivity::class, 1);
    Bus::assertDispatched(DeliverActivity::class, function (DeliverActivity $job) use ($activity): bool {
        return $job->inboxUrl === 'https://remote.example.com/users/a/inbox'
            && $job->activityModelId === $activity->id
            && $job->actorId === $this->actor->id;
    });
});
