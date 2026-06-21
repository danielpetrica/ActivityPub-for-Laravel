<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;

final class TestActivityBuilderActor implements ActorContract
{
    public function __construct(
        private string $username = 'test',
        private string $actorId = 'https://example.com/users/test',
    ) {}

    public function getPreferredUsername(): string { return $this->username; }
    public function getDisplayName(): string { return $this->username; }
    public function getSummary(): ?string { return null; }
    public function getIconUrl(): ?string { return null; }
    public function getHeaderImageUrl(): ?string { return null; }
    public function getActorId(): string { return $this->actorId; }
    public function getInboxUrl(): string { return $this->actorId.'/inbox'; }
    public function getOutboxUrl(): string { return $this->actorId.'/outbox'; }
    public function getFollowersUrl(): string { return $this->actorId.'/followers'; }
    public function getFollowingUrl(): string { return $this->actorId.'/following'; }
    public function getPublicKey(): string { return 'public-key'; }
    public function getKeyId(): string { return $this->actorId.'#main-key'; }
    public function getPrivateKeyPem(): ?string { return null; }
}

it('builds a Follow activity', function (): void {
    $actor = new TestActivityBuilderActor();
    $activity = ActivityBuilder::follow($actor, 'https://example.com/users/target');

    expect($activity)
        ->toHaveKey('@context', 'https://www.w3.org/ns/activitystreams')
        ->toHaveKey('type', 'Follow')
        ->toHaveKey('actor', $actor->getActorId())
        ->toHaveKey('object', 'https://example.com/users/target')
        ->and($activity['id'])->toContain('#follow/');
});

it('builds an Announce activity with followers cc', function (): void {
    $actor = new TestActivityBuilderActor();
    $activity = ActivityBuilder::announce($actor, 'https://example.com/notes/123');

    expect($activity)
        ->toHaveKey('type', 'Announce')
        ->toHaveKey('cc')
        ->and($activity['cc'])->toContain($actor->getFollowersUrl());
});

it('builds a CreateNote activity with cc', function (): void {
    $actor = new TestActivityBuilderActor();
    $activity = ActivityBuilder::createNote(
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

it('builds an Undo activity with original Follow id', function (): void {
    $actor = new TestActivityBuilderActor();
    $activity = ActivityBuilder::undoFollow($actor, 'https://example.com/users/target');

    expect($activity)
        ->toHaveKey('type', 'Undo')
        ->and($activity['object'])->toHaveKey('id')
        ->and($activity['object']['id'])->toBe('https://example.com/users/target#follow');
});
