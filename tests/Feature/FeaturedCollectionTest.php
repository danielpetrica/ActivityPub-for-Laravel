<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FeaturedPost extends Model implements FederatableContentContract
{
    protected $table = 'featured_posts';

    protected $fillable = ['actor_id', 'pinned', 'body'];

    protected $casts = ['pinned' => 'boolean'];

    public function shouldFederate(): bool
    {
        return true;
    }

    public function isActivityPubPinned(): bool
    {
        return (bool) $this->pinned;
    }

    public function activityPubActor(): ActorContract
    {
        return Actor::query()->findOrFail($this->actor_id);
    }

    public function getActivityPubId(): string
    {
        return url('/featured-posts/'.$this->id);
    }

    public function getActivityPubType(): string
    {
        return 'Note';
    }

    public function getActivityPubName(): ?string
    {
        return null;
    }

    public function getActivityPubContent(): string
    {
        return (string) $this->body;
    }

    public function getActivityPubSummary(): ?string
    {
        return null;
    }

    public function getActivityPubUrl(): string
    {
        return url('/featured-posts/'.$this->id);
    }

    public function getActivityPubPublishedAt(): string
    {
        return $this->created_at->toIso8601String();
    }

    public function getActivityPubAttributedTo(): string
    {
        return url('/users/'.Actor::query()->find($this->actor_id)?->username);
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

function createFeaturedActor(string $username): Actor
{
    $keys = generateTestKeyPair();

    return Actor::query()->create(attributes: [
        'username' => $username,
        'name' => 'Featured Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);
}

beforeEach(function (): void {
    if (! Schema::hasTable('featured_posts')) {
        Schema::create('featured_posts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->boolean('pinned')->default(false);
            $table->string('body')->nullable();
            $table->timestamps();
        });
    }
});

it('lists pinned content in the featured collection', function (): void {
    $actor = createFeaturedActor(username: 'featured');

    $pinnedOne = FeaturedPost::query()->create(['actor_id' => $actor->id, 'pinned' => true, 'body' => 'Pinned one']);
    $pinnedTwo = FeaturedPost::query()->create(['actor_id' => $actor->id, 'pinned' => true, 'body' => 'Pinned two']);
    $unpinned = FeaturedPost::query()->create(['actor_id' => $actor->id, 'pinned' => false, 'body' => 'Not pinned']);

    // Pinned content belonging to a different actor must not leak in.
    $otherActor = createFeaturedActor(username: 'otherfeatured');
    $otherPinned = FeaturedPost::query()->create(['actor_id' => $otherActor->id, 'pinned' => true, 'body' => 'Someone else']);

    config()->set('activitypub.federatable_models', [FeaturedPost::class]);

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['type'])->toBe('OrderedCollection');
    expect($top['totalItems'])->toBe(2);
    expect($top['id'])->toBe($actor->actor_id.'/featured');
    expect($top)->not->toHaveKey('orderedItems');

    $page = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]).'?page=1',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($page['type'])->toBe('OrderedCollectionPage');
    expect($page['orderedItems'])->toHaveCount(2);
    expect($page['orderedItems'])->toContain($pinnedOne->getActivityPubId());
    expect($page['orderedItems'])->toContain($pinnedTwo->getActivityPubId());
    expect($page['orderedItems'])->not->toContain($unpinned->getActivityPubId());
    expect($page['orderedItems'])->not->toContain($otherPinned->getActivityPubId());
});

it('paginates the featured collection', function (): void {
    $actor = createFeaturedActor(username: 'featuredpager');

    for ($i = 0; $i < 25; $i++) {
        FeaturedPost::query()->create(['actor_id' => $actor->id, 'pinned' => true, 'body' => 'Pinned '.$i]);
    }

    config()->set('activitypub.federatable_models', [FeaturedPost::class]);

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['totalItems'])->toBe(25);
    expect($top['last'])->toBe($actor->actor_id.'/featured?page=2');

    $pageTwo = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]).'?page=2',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($pageTwo['orderedItems'])->toHaveCount(5);
    expect($pageTwo['prev'])->toBe($actor->actor_id.'/featured?page=1');
});

it('returns an empty featured collection when no models are configured', function (): void {
    $actor = createFeaturedActor(username: 'featuredempty');
    config()->set('activitypub.federatable_models', []);

    $top = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($top['type'])->toBe('OrderedCollection');
    expect($top['totalItems'])->toBe(0);

    $page = $this->getJson(
        uri: route(name: 'activitypub.actor.featured', parameters: ['actor' => $actor->username]).'?page=1',
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($page['orderedItems'])->toBe([]);
});

it('exposes the featured collection on the actor resource', function (): void {
    $actor = createFeaturedActor(username: 'featuredactor');

    $json = $this->getJson(
        uri: route(name: 'activitypub.actor', parameters: ['actor' => $actor->username]),
        headers: ['Accept' => 'application/activity+json'],
    )->json();

    expect($json['featured'])->toBe($actor->actor_id.'/featured');
});
