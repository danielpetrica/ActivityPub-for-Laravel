<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;

/**
 * Baseline content model: exposes neither `sensitive` nor emoji, so the built
 * object must stay byte-identical to the pre-extension output.
 */
class ContentFidelityContent implements FederatableContentContract
{
    /**
     * @param  array<int, array{type: string, href: string, name: string}>  $tags
     */
    public function __construct(
        protected Actor $actor,
        protected array $tags = [],
        protected ?string $summary = null,
    ) {}

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
        return 'https://example.com/objects/1';
    }

    public function getActivityPubType(): string
    {
        return 'Note';
    }

    public function getActivityPubName(): ?string
    {
        return 'A note';
    }

    public function getActivityPubContent(): string
    {
        return 'Hello world';
    }

    public function getActivityPubSummary(): ?string
    {
        return $this->summary;
    }

    public function getActivityPubUrl(): string
    {
        return 'https://example.com/objects/1';
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
        return $this->tags;
    }
}

/**
 * Content model exposing the opt-in `sensitive` flag and emoji map through the
 * documented getter methods.
 */
final class ContentFidelityRichContent extends ContentFidelityContent
{
    /**
     * @param  array<string, mixed>  $emoji
     * @param  array<int, array{type: string, href: string, name: string}>  $tags
     */
    public function __construct(
        Actor $actor,
        private bool $sensitive = false,
        private array $emoji = [],
        array $tags = [],
    ) {
        parent::__construct(actor: $actor, tags: $tags);
    }

    public function isActivityPubSensitive(): bool
    {
        return $this->sensitive;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivityPubEmoji(): array
    {
        return $this->emoji;
    }
}

/**
 * Content model exposing the opt-in fields as attributes, mirroring an
 * Eloquent model with `sensitive` / `activitypub_emoji` columns.
 */
final class ContentFidelityAttributeContent extends ContentFidelityContent
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(Actor $actor, private array $attributes = [])
    {
        parent::__construct(actor: $actor);
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
}

/**
 * Actor exposing the opt-in `sensitive` flag so `createNote()` can be covered.
 */
final class ContentFidelitySensitiveActor implements ActorContract
{
    public function getPreferredUsername(): string
    {
        return 'sensitive';
    }

    public function getDisplayName(): string
    {
        return 'Sensitive';
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
        return 'https://example.com/users/sensitive';
    }

    public function getActorId(): string
    {
        return 'https://example.com/users/sensitive';
    }

    public function getInboxUrl(): string
    {
        return 'https://example.com/users/sensitive/inbox';
    }

    public function getOutboxUrl(): string
    {
        return 'https://example.com/users/sensitive/outbox';
    }

    public function getFollowersUrl(): string
    {
        return 'https://example.com/users/sensitive/followers';
    }

    public function getFollowingUrl(): string
    {
        return 'https://example.com/users/sensitive/following';
    }

    public function getPublicKey(): string
    {
        return 'public-key';
    }

    public function getKeyId(): string
    {
        return 'https://example.com/users/sensitive#main-key';
    }

    public function getPrivateKeyPem(): ?string
    {
        return null;
    }

    public function getActivityPubSensitive(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'fidelity',
        'name' => 'Fidelity User',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
});

it('omits sensitive and emoji tags by default', function (): void {
    $object = (new ActivityBuilder())->buildObject(
        content: new ContentFidelityContent(actor: $this->actor),
    );

    expect($object)->not->toHaveKey('sensitive')
        ->and($object)->not->toHaveKey('tag');
});

it('emits sensitive when the content exposes it as a method', function (): void {
    $content = new ContentFidelityRichContent(actor: $this->actor, sensitive: true);

    $object = (new ActivityBuilder())->buildObject(content: $content);

    expect($object['sensitive'] ?? null)->toBeTrue();
});

it('does not emit sensitive when the content exposes it as false', function (): void {
    $content = new ContentFidelityRichContent(actor: $this->actor, sensitive: false);

    $object = (new ActivityBuilder())->buildObject(content: $content);

    expect($object)->not->toHaveKey('sensitive');
});

it('emits custom emoji tags merged after existing tags', function (): void {
    $content = new ContentFidelityRichContent(
        actor: $this->actor,
        sensitive: true,
        emoji: [
            'blobcat' => 'https://example.com/emoji/blobcat.png',
            'party' => ['url' => 'https://example.com/emoji/party.png'],
        ],
        tags: [
            ['type' => 'Hashtag', 'href' => 'https://example.com/tags/php', 'name' => '#php'],
        ],
    );

    $object = (new ActivityBuilder())->buildObject(content: $content);

    expect($object['tag'])->toHaveCount(3)
        ->and($object['tag'][0]['type'])->toBe('Hashtag')
        ->and($object['tag'][1])->toBe([
            'type' => 'Emoji',
            'name' => ':blobcat:',
            'icon' => [
                'type' => 'Image',
                'url' => 'https://example.com/emoji/blobcat.png',
            ],
        ])
        ->and($object['tag'][2]['name'])->toBe(':party:');
});

it('skips malformed emoji entries without throwing', function (): void {
    $content = new ContentFidelityRichContent(
        actor: $this->actor,
        emoji: [
            '' => 'https://example.com/emoji/empty-shortcode.png',
            'empty-url' => '',
            0 => 'https://example.com/emoji/numeric-key.png',
            'bad-value' => ['nourl' => true],
            ':colon:' => 'https://example.com/emoji/colon.png',
            'good' => 'https://example.com/emoji/good.png',
        ],
    );

    $object = (new ActivityBuilder())->buildObject(content: $content);

    $names = array_column($object['tag'] ?? [], 'name');

    expect($names)->toBe([':colon:', ':good:'])
        ->and($object['tag'][0]['icon']['url'])->toBe('https://example.com/emoji/colon.png');
});

it('reads sensitive and emoji from attributes', function (): void {
    $content = new ContentFidelityAttributeContent(actor: $this->actor, attributes: [
        'sensitive' => true,
        'activitypub_emoji' => ['wave' => 'https://example.com/emoji/wave.png'],
    ]);

    $object = (new ActivityBuilder())->buildObject(content: $content);

    expect($object['sensitive'] ?? null)->toBeTrue()
        ->and($object['tag'][0]['name'])->toBe(':wave:');
});

it('emits sensitive on a created Note when the actor exposes it', function (): void {
    $activity = (new ActivityBuilder())->createNote(
        actor: new ContentFidelitySensitiveActor(),
        content: 'Content warning',
        inReplyToUrl: 'https://example.com/objects/1',
        to: ['https://www.w3.org/ns/activitystreams#Public'],
    );

    expect($activity['object']['sensitive'] ?? null)->toBeTrue();
});

it('omits sensitive on a created Note for an actor without the opt-in', function (): void {
    $activity = (new ActivityBuilder())->createNote(
        actor: $this->actor,
        content: 'Plain note',
        inReplyToUrl: 'https://example.com/objects/1',
        to: ['https://www.w3.org/ns/activitystreams#Public'],
    );

    expect($activity['object'])->not->toHaveKey('sensitive');
});
