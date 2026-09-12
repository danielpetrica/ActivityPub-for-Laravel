<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FederatesContentPost extends Model implements FederatableContentContract
{
    use FederatesContent;

    protected $table = 'federates_content_test_posts';
    protected $fillable = ['title', 'status', 'should_federate'];

    public function shouldFederate(): bool
    {
        return (bool) ($this->should_federate ?? true);
    }

    public function isActivityPubPinned(): bool
    {
        return false;
    }

    public function activityPubActor(): ActorContract
    {
        return Actor::first();
    }

    public function getActivityPubId(): string
    {
        return url('/posts/'.$this->id);
    }

    public function getActivityPubType(): string
    {
        return 'Note';
    }

    public function getActivityPubName(): ?string
    {
        return $this->title;
    }

    public function getActivityPubContent(): string
    {
        return 'Test content #'.$this->id;
    }

    public function getActivityPubSummary(): ?string
    {
        return null;
    }

    public function getActivityPubUrl(): string
    {
        return url('/posts/'.$this->id);
    }

    public function getActivityPubPublishedAt(): string
    {
        return $this->created_at->toIso8601String();
    }

    public function getActivityPubAttributedTo(): string
    {
        return $this->activityPubActor()->getActorId();
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
    Schema::create('federates_content_test_posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->boolean('should_federate')->default(true);
        $table->timestamps();
    });

    $keys = generateTestKeyPair();

    Actor::query()->create([
        'username' => 'testactor',
        'name' => 'Test Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.http_signatures.enabled', false);
});

afterEach(function (): void {
    Schema::dropIfExists('federates_content_test_posts');
});

it('saves a model using FederatesContent without SQL errors', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Test Post',
    ]);

    expect($post->id)->not->toBeNull();
    expect($post->title)->toBe('Test Post');
});

it('does not include _was_federatable_before_save in model attributes after save', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Test Post',
    ]);

    $attributes = $post->getAttributes();

    expect($attributes)->not->toHaveKey('_was_federatable_before_save');
});

it('does not include _was_federatable_before_save in getDirty during update', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Original Title',
    ]);

    $post->title = 'Updated Title';

    // Check dirty BEFORE save — this is where the pollution would appear
    $dirty = $post->getDirty();

    expect($dirty)->not->toHaveKey('_was_federatable_before_save');
    expect($dirty)->toHaveKey('title');
    expect($dirty['title'])->toBe('Updated Title');
});

it('does not include _was_federatable_before_save in getAttributesForInsert during create', function (): void {
    $post = new FederatesContentPost();
    $post->title = 'New Post';

    $post->save();

    $raw = $post->getAttributes();
    expect($raw)->not->toHaveKey('_was_federatable_before_save');
});

it('sets activityPubWasFederatableBeforeSave property correctly on new model', function (): void {
    // Federation is disabled, so sendCreate/sendUpdate won't be called
    // We verify the property exists and doesn't leak into attributes
    $post = FederatesContentPost::query()->create([
        'title' => 'New Post',
        'should_federate' => true,
    ]);

    // The property should be set but NOT present in the attribute bag
    expect($post->getAttributes())->not->toHaveKey('activityPubWasFederatableBeforeSave');
    expect($post->getAttributes())->not->toHaveKey('_was_federatable_before_save');
});

it('sets activityPubWasFederatableBeforeSave to false when shouldFederate returns false', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Non-Federatable Post',
        'should_federate' => false,
    ]);

    // The property should be set but NOT present in the attribute bag
    expect($post->getAttributes())->not->toHaveKey('activityPubWasFederatableBeforeSave');
    expect($post->getAttributes())->not->toHaveKey('_was_federatable_before_save');
});

it('does not leak internal property into serialised model', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Test Post',
    ]);

    $array = $post->toArray();

    expect($array)->not->toHaveKey('activityPubWasFederatableBeforeSave');
    expect($array)->not->toHaveKey('_was_federatable_before_save');
});

it('can create and update multiple models without attribute pollution', function (): void {
    $posts = [];
    for ($i = 0; $i < 5; $i++) {
        $posts[] = FederatesContentPost::query()->create([
            'title' => "Post #{$i}",
        ]);
    }

    foreach ($posts as $post) {
        $attributes = $post->getAttributes();
        expect($attributes)->not->toHaveKey('_was_federatable_before_save');
        expect($attributes)->not->toHaveKey('activityPubWasFederatableBeforeSave');
    }

    foreach ($posts as $post) {
        $post->title = $post->title.' (updated)';
        $post->save();

        $dirty = $post->getDirty();
        expect($dirty)->not->toHaveKey('_was_federatable_before_save');
    }
});

it('survives rapid create-update-delete cycle without attribute leaks', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Rapid Cycle Post',
    ]);

    expect($post->getAttributes())->not->toHaveKey('_was_federatable_before_save');

    $post->title = 'Updated';
    $post->save();

    expect($post->getAttributes())->not->toHaveKey('_was_federatable_before_save');

    $post->delete();
});

it('preserves other attributes correctly when using the trait', function (): void {
    $post = FederatesContentPost::query()->create([
        'title' => 'Preserve Test',
        'status' => 'published',
    ]);

    expect($post->title)->toBe('Preserve Test');
    expect($post->status)->toBe('published');

    $post->title = 'Updated Title';
    $post->save();

    expect($post->fresh()->title)->toBe('Updated Title');
});
