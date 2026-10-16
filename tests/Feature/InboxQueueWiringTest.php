<?php

use DanielPetrica\LaravelActivityPub\Jobs\ProcessInboxActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
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

it('dispatches ProcessInboxActivity for a verified inbox POST', function (): void {
    Queue::fake();

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => 'https://remote.example.com/users/remote#likes/9001',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/remote',
            'object' => 'https://example.com/notes/1',
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    Queue::assertPushed(ProcessInboxActivity::class, function (ProcessInboxActivity $job): bool {
        return $job->payload['id'] === 'https://remote.example.com/users/remote#likes/9001';
    });
});

it('dispatches ProcessInboxActivity for the shared inbox', function (): void {
    Queue::fake();

    $this->postJson(
        uri: route(name: 'activitypub.shared-inbox'),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => 'https://remote.example.com/users/remote#likes/9002',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/remote',
            'object' => 'https://example.com/notes/2',
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(status: 202);

    Queue::assertPushed(ProcessInboxActivity::class);
});

it('processes inline when the queue flag is disabled', function (): void {
    Queue::fake();
    config()->set('activitypub.queue.process_inbox', false);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => 'https://remote.example.com/users/remote#likes/9003',
            'type' => 'Like',
            'actor' => 'https://remote.example.com/users/remote',
            'object' => 'https://example.com/notes/3',
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(status: 202);

    Queue::assertNotPushed(ProcessInboxActivity::class);

    expect(Activity::query()
        ->where(column: 'actor_id', operator: '=', value: $this->actor->id)
        ->where(column: 'is_incoming', operator: '=', value: true)
        ->count())->toBe(1);
});

it('still enforces the 1 MB payload cap before dispatching', function (): void {
    Queue::fake();

    $content = json_encode([
        'type' => 'Create',
        'actor' => 'https://remote.example.com/users/remote',
        'object' => str_repeat('a', 1048576),
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        method: 'POST',
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        server: ['CONTENT_TYPE' => 'application/activity+json'],
        content: $content,
    );

    $response->assertStatus(status: 413);

    Queue::assertNothingPushed();
});

it('rejects an invalid signature before dispatching', function (): void {
    Queue::fake();
    config()->set('activitypub.http_signatures.enabled', true);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Create',
            'actor' => 'https://remote.example.com/users/remote',
            'object' => ['type' => 'Note', 'content' => 'Hello'],
        ],
        headers: ['Signature' => 'not-a-valid-signature'],
    )->assertStatus(status: 401);

    Queue::assertNotPushed(ProcessInboxActivity::class);
});
