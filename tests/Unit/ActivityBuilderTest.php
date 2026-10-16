<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;

final class TestActivityBuilderActor implements ActorContract
{
    public function __construct(
        private string $username = 'test',
        private string $actorId = 'https://example.com/users/test',
    ) {}

    public function getPreferredUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->username;
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
        return $this->actorId;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getInboxUrl(): string
    {
        return $this->actorId.'/inbox';
    }

    public function getOutboxUrl(): string
    {
        return $this->actorId.'/outbox';
    }

    public function getFollowersUrl(): string
    {
        return $this->actorId.'/followers';
    }

    public function getFollowingUrl(): string
    {
        return $this->actorId.'/following';
    }

    public function getPublicKey(): string
    {
        return 'public-key';
    }

    public function getKeyId(): string
    {
        return $this->actorId.'#main-key';
    }

    public function getPrivateKeyPem(): ?string
    {
        return null;
    }
}

it('builds a Follow activity', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->follow($actor, 'https://example.com/users/target');

    expect($activity)
        ->toHaveKey('@context', 'https://www.w3.org/ns/activitystreams')
        ->toHaveKey('type', 'Follow')
        ->toHaveKey('actor', $actor->getActorId())
        ->toHaveKey('object', 'https://example.com/users/target')
        ->and($activity['id'])->toContain('#follow/');
});

it('builds an Announce activity with followers cc', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->announce($actor, 'https://example.com/notes/123');

    expect($activity)
        ->toHaveKey('type', 'Announce')
        ->toHaveKey('cc')
        ->and($activity['cc'])->toContain($actor->getFollowersUrl());
});

it('builds a CreateNote activity with cc', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->createNote(
        $actor,
        'Hello world',
        'https://example.com/notes/1',
        ['https://www.w3.org/ns/activitystreams#Public'],
        [$actor->getFollowersUrl()],
    );

    expect($activity)
        ->toHaveKey('type', 'Create')
        ->and($activity['object'])->toHaveKey('url')
        ->and($activity['object'])->toHaveKey('cc');
});

it('builds an Undo activity wrapping the real Follow id', function (): void {
    $actor = new TestActivityBuilderActor;
    $followId = 'https://example.com/users/test#follow/abc-123';
    $activity = (new ActivityBuilder)->undoFollow(
        actor: $actor,
        objectUrl: 'https://example.com/users/target',
        followId: $followId,
    );

    expect($activity)
        ->toHaveKey('type', 'Undo')
        ->toHaveKey('to')
        ->and($activity['to'])->toBe(['https://example.com/users/target'])
        ->and($activity['object'])->toHaveKey('id')
        ->and($activity['object']['id'])->toBe($followId)
        ->and($activity['object']['type'])->toBe('Follow');
});

it('falls back to a deterministic Follow id when none is provided', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->undoFollow($actor, 'https://example.com/users/target');

    expect($activity['object']['id'])->toBe('https://example.com/users/target#follow');
});

it('builds a Block activity addressed to the target', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->block($actor, 'https://example.com/users/target');

    expect($activity)
        ->toHaveKey('@context', 'https://www.w3.org/ns/activitystreams')
        ->toHaveKey('type', 'Block')
        ->toHaveKey('actor', $actor->getActorId())
        ->toHaveKey('object', 'https://example.com/users/target')
        ->and($activity['to'])->toBe(['https://example.com/users/target'])
        ->and($activity['id'])->toContain('#block/');
});

it('builds an Undo(Block) activity wrapping the Block', function (): void {
    $actor = new TestActivityBuilderActor;
    $activity = (new ActivityBuilder)->undoBlock($actor, 'https://example.com/users/target');

    expect($activity)
        ->toHaveKey('type', 'Undo')
        ->and($activity['object'])->toHaveKey('type', 'Block')
        ->and($activity['object']['object'])->toBe('https://example.com/users/target')
        ->and($activity['to'])->toBe(['https://example.com/users/target']);
});

it('builds a generic Undo envelope around any activity', function (): void {
    $actor = new TestActivityBuilderActor;
    $inner = (new ActivityBuilder)->follow($actor, 'https://example.com/users/target');

    $activity = (new ActivityBuilder)->undo($actor, $inner);

    expect($activity)
        ->toHaveKey('type', 'Undo')
        ->and($activity['object'])->toBe($inner)
        ->and($activity['to'])->toBe(['https://example.com/users/target'])
        ->and($activity['id'])->toContain('#undo/');
});
