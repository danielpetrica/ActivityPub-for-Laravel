<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;

/**
 * A consumer builder that delegates every existing method to the bundled
 * builder but stamps the Create/Update envelope so the swap is observable.
 */
final class StampingActivityBuilder implements ActivityBuilderContract
{
    public function __construct(private ActivityBuilder $inner) {}

    public function follow(ActorContract $actor, string $objectUrl): array
    {
        return $this->inner->follow(actor: $actor, objectUrl: $objectUrl);
    }

    public function undoFollow(ActorContract $actor, string $objectUrl, ?string $followId = null): array
    {
        return $this->inner->undoFollow(actor: $actor, objectUrl: $objectUrl, followId: $followId);
    }

    public function accept(ActorContract $actor, array $originalPayload): array
    {
        return $this->inner->accept(actor: $actor, originalPayload: $originalPayload);
    }

    public function reject(ActorContract $actor, array $originalPayload): array
    {
        return $this->inner->reject(actor: $actor, originalPayload: $originalPayload);
    }

    public function like(ActorContract $actor, string $objectUrl): array
    {
        return $this->inner->like(actor: $actor, objectUrl: $objectUrl);
    }

    public function announce(ActorContract $actor, string $objectUrl): array
    {
        return $this->inner->announce(actor: $actor, objectUrl: $objectUrl);
    }

    public function delete(ActorContract $actor, string $objectId): array
    {
        return $this->inner->delete(actor: $actor, objectId: $objectId);
    }

    public function undo(ActorContract $actor, array $activity): array
    {
        return $this->inner->undo(actor: $actor, activity: $activity);
    }

    public function block(ActorContract $actor, string $objectUrl): array
    {
        return $this->inner->block(actor: $actor, objectUrl: $objectUrl);
    }

    public function undoBlock(ActorContract $actor, string $objectUrl): array
    {
        return $this->inner->undoBlock(actor: $actor, objectUrl: $objectUrl);
    }

    public function move(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): array
    {
        return $this->inner->move(actor: $actor, targetUrl: $targetUrl, alsoKnownAs: $alsoKnownAs);
    }

    public function createNote(ActorContract $actor, string $content, string $inReplyToUrl, array $to, array $cc = []): array
    {
        return $this->inner->createNote(
            actor: $actor,
            content: $content,
            inReplyToUrl: $inReplyToUrl,
            to: $to,
            cc: $cc,
        );
    }

    public function buildObject(FederatableContentContract $content, array $mentions = []): array
    {
        return $this->inner->buildObject(content: $content, mentions: $mentions);
    }

    public function buildActivity(string $type, ActorContract $actor, array $object): array
    {
        $activity = $this->inner->buildActivity(type: $type, actor: $actor, object: $object);
        $activity['x-stamping-builder'] = true;

        return $activity;
    }

    public function updateActor(ActorContract $actor, array $object): array
    {
        $activity = $this->inner->updateActor(actor: $actor, object: $object);
        $activity['x-stamping-builder'] = true;

        return $activity;
    }

    public function attachMentions(array $activity, array $mentions): array
    {
        return $this->inner->attachMentions(activity: $activity, mentions: $mentions);
    }
}

final class BuilderSwapContent implements FederatableContentContract
{
    public function __construct(private Actor $actor) {}

    public function shouldFederate(): bool
    {
        return true;
    }

    public function isActivityPubPinned(): bool
    {
        return false;
    }

    public function activityPubActor(): ActorContract
    {
        return $this->actor;
    }

    public function getActivityPubId(): string
    {
        return 'https://example.com/posts/1';
    }

    public function getActivityPubType(): string
    {
        return 'Note';
    }

    public function getActivityPubName(): ?string
    {
        return 'A post';
    }

    public function getActivityPubContent(): string
    {
        return 'Hello world';
    }

    public function getActivityPubSummary(): ?string
    {
        return null;
    }

    public function getActivityPubUrl(): string
    {
        return 'https://example.com/posts/1';
    }

    public function getActivityPubPublishedAt(): string
    {
        return now()->toIso8601String();
    }

    public function getActivityPubAttributedTo(): string
    {
        return $this->actor->getActorId();
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

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'builderuser',
        'name' => 'Builder User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.federation.enabled', false);

    $this->app->bind(
        ActivityBuilderContract::class,
        fn ($app): StampingActivityBuilder => new StampingActivityBuilder($app->make(ActivityBuilder::class)),
    );
});

it('uses a custom ActivityBuilderContract for a Create', function (): void {
    app(ActivityPubService::class)->sendCreate(
        content: new BuilderSwapContent($this->actor),
    );

    $record = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Create')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->payload['x-stamping-builder'] ?? null)->toBeTrue()
        ->and($record->payload['type'])->toBe('Create')
        ->and($record->payload['object']['type'])->toBe('Note');
});

it('uses a custom ActivityBuilderContract for an Update', function (): void {
    app(ActivityPubService::class)->sendUpdate(
        content: new BuilderSwapContent($this->actor),
    );

    $record = Activity::query()
        ->where(column: 'type', operator: '=', value: 'Update')
        ->where(column: 'is_incoming', operator: '=', value: false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->payload['x-stamping-builder'] ?? null)->toBeTrue();
});

it('builds Create object envelopes through the swapped builder', function (): void {
    $builder = app(ActivityBuilderContract::class);

    expect($builder)->toBeInstanceOf(StampingActivityBuilder::class);

    $object = $builder->buildObject(content: new BuilderSwapContent($this->actor));
    $activity = $builder->buildActivity(type: 'Create', actor: $this->actor, object: $object);

    expect($activity['x-stamping-builder'] ?? null)->toBeTrue()
        ->and($activity['actor'])->toBe($this->actor->getActorId());
});
