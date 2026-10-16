<?php

use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use DanielPetrica\LaravelActivityPub\Services\ActivityInteractions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal local content model used to exercise polymorphic object linking
 * (`object_type` = class name, `object_id` = primary key).
 */
final class InteractionTestPost extends Model
{
    protected $table = 'interaction_test_posts';

    /** @var list<string> */
    protected $fillable = ['title'];
}

/**
 * Stand-in for a timeline item referenced by remote URL. The interaction
 * service keys objects off the morph class and primary key, so this carrier
 * exposes the two values the inbox handlers persist for remote content:
 * `object_type` = 'remote_url' and `object_id` = the full URL.
 */
final class InteractionRemoteUrl extends Model
{
    private string $url = '';

    public function withUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getMorphClass(): string
    {
        return 'remote_url';
    }

    public function getKey(): string
    {
        return $this->url;
    }
}

beforeEach(function (): void {
    // Inbound Like/Announce resolves the remote actor, which may try an HTTP
    // fetch. Fake it so the suite never touches the network.
    Http::fake([]);

    config()->set(key: 'activitypub.http_signatures.enabled', value: false);
    config()->set(key: 'activitypub.federation.enabled', value: false);
    // Keep the suite hermetic: never resolve example.com through real DNS.
    config()->set(key: 'activitypub.security.allowed_hosts', value: ['example.com']);

    Schema::create(table: 'interaction_test_posts', callback: static function (Blueprint $table): void {
        $table->id();
        $table->string(column: 'title');
        $table->timestamps();
    });

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create(attributes: [
        'username' => 'liker',
        'name' => 'Liker',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->otherActor = Actor::query()->create(attributes: [
        'username' => 'other',
        'name' => 'Other',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->post = InteractionTestPost::query()->create(attributes: ['title' => 'First']);
    $this->otherPost = InteractionTestPost::query()->create(attributes: ['title' => 'Second']);
});

afterEach(function (): void {
    Schema::dropIfExists(table: 'interaction_test_posts');
});

// ── Counts ──

it('counts each interaction type and excludes undone interactions', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::like($this->otherActor, $this->post);
    ActivityInteractions::boost($this->actor, $this->post);
    ActivityInteractions::bookmark($this->actor, $this->post);

    Interaction::query()->create([
        'type' => InteractionType::Comment,
        'actor_id' => $this->actor->id,
        'object_type' => $this->post->getMorphClass(),
        'object_id' => (string) $this->post->getKey(),
        'status' => InteractionStatus::Active,
    ]);

    Interaction::query()->create([
        'type' => InteractionType::Quote,
        'actor_id' => $this->otherActor->id,
        'object_type' => $this->post->getMorphClass(),
        'object_id' => (string) $this->post->getKey(),
        'status' => InteractionStatus::Active,
    ]);

    expect(ActivityInteractions::likeCount($this->post))->toBe(2)
        ->and(ActivityInteractions::boostCount($this->post))->toBe(1)
        ->and(ActivityInteractions::bookmarkCount($this->post))->toBe(1)
        ->and(ActivityInteractions::commentCount($this->post))->toBe(1)
        ->and(ActivityInteractions::quoteCount($this->post))->toBe(1);

    ActivityInteractions::unlike($this->actor, $this->post);

    expect(ActivityInteractions::likeCount($this->post))->toBe(1);
});

it('scopes counts to the given object', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::like($this->actor, $this->otherPost);
    ActivityInteractions::like($this->otherActor, $this->otherPost);

    expect(ActivityInteractions::likeCount($this->post))->toBe(1)
        ->and(ActivityInteractions::likeCount($this->otherPost))->toBe(2);
});

// ── Boolean checks ──

it('reports whether an actor liked, boosted or bookmarked an object', function (): void {
    expect(ActivityInteractions::hasLiked($this->post, $this->actor))->toBeFalse()
        ->and(ActivityInteractions::hasBoosted($this->post, $this->actor))->toBeFalse()
        ->and(ActivityInteractions::hasBookmarked($this->post, $this->actor))->toBeFalse();

    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::boost($this->actor, $this->post);
    ActivityInteractions::bookmark($this->actor, $this->post);

    expect(ActivityInteractions::hasLiked($this->post, $this->actor))->toBeTrue()
        ->and(ActivityInteractions::hasBoosted($this->post, $this->actor))->toBeTrue()
        ->and(ActivityInteractions::hasBookmarked($this->post, $this->actor))->toBeTrue()
        // Per-actor isolation: another actor's absence is not affected.
        ->and(ActivityInteractions::hasLiked($this->post, $this->otherActor))->toBeFalse()
        ->and(ActivityInteractions::hasBoosted($this->post, $this->otherActor))->toBeFalse()
        ->and(ActivityInteractions::hasBookmarked($this->post, $this->otherActor))->toBeFalse();
});

it('reports false for an actor whose interaction was undone', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::unlike($this->actor, $this->post);

    expect(ActivityInteractions::hasLiked($this->post, $this->actor))->toBeFalse();
});

// ── Mutations: idempotency ──

it('does not duplicate a like when called twice', function (): void {
    $first = ActivityInteractions::like($this->actor, $this->post);
    $second = ActivityInteractions::like($this->actor, $this->post);

    expect($second->id)->toBe($first->id)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::likeCount($this->post))->toBe(1);
});

it('does not duplicate a boost when called twice', function (): void {
    $first = ActivityInteractions::boost($this->actor, $this->post);
    $second = ActivityInteractions::boost($this->actor, $this->post);

    expect($second->id)->toBe($first->id)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::boostCount($this->post))->toBe(1);
});

it('does not duplicate a bookmark when called twice', function (): void {
    $first = ActivityInteractions::bookmark($this->actor, $this->post);
    $second = ActivityInteractions::bookmark($this->actor, $this->post);

    expect($second->id)->toBe($first->id)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::bookmarkCount($this->post))->toBe(1);
});

// ── Mutations: undo preserves history and can be reactivated ──

it('marks a like undone, preserves the row, and reactivates it on re-like', function (): void {
    $interaction = ActivityInteractions::like($this->actor, $this->post);

    ActivityInteractions::unlike($this->actor, $this->post);

    $undone = Interaction::query()->findOrFail(id: $interaction->id);

    expect($undone->status)->toBe(InteractionStatus::Undone)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::likeCount($this->post))->toBe(0);

    $reactivated = ActivityInteractions::like($this->actor, $this->post);

    expect($reactivated->id)->toBe($interaction->id)
        ->and($reactivated->status)->toBe(InteractionStatus::Active)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::likeCount($this->post))->toBe(1);
});

it('marks a boost undone, preserves the row, and reactivates it on re-boost', function (): void {
    $interaction = ActivityInteractions::boost($this->actor, $this->post);

    ActivityInteractions::unboost($this->actor, $this->post);

    $undone = Interaction::query()->findOrFail(id: $interaction->id);

    expect($undone->status)->toBe(InteractionStatus::Undone)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::boostCount($this->post))->toBe(0);

    $reactivated = ActivityInteractions::boost($this->actor, $this->post);

    expect($reactivated->id)->toBe($interaction->id)
        ->and($reactivated->status)->toBe(InteractionStatus::Active)
        ->and(ActivityInteractions::boostCount($this->post))->toBe(1);
});

it('marks a bookmark undone, preserves the row, and reactivates it on re-bookmark', function (): void {
    $interaction = ActivityInteractions::bookmark($this->actor, $this->post);

    ActivityInteractions::unbookmark($this->actor, $this->post);

    $undone = Interaction::query()->findOrFail(id: $interaction->id);

    expect($undone->status)->toBe(InteractionStatus::Undone)
        ->and(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::bookmarkCount($this->post))->toBe(0);

    $reactivated = ActivityInteractions::bookmark($this->actor, $this->post);

    expect($reactivated->id)->toBe($interaction->id)
        ->and($reactivated->status)->toBe(InteractionStatus::Active)
        ->and(ActivityInteractions::bookmarkCount($this->post))->toBe(1);
});

it('is a no-op when undoing an interaction that does not exist', function (): void {
    ActivityInteractions::unlike($this->actor, $this->post);
    ActivityInteractions::unboost($this->actor, $this->post);
    ActivityInteractions::unbookmark($this->actor, $this->post);

    expect(Interaction::query()->count())->toBe(0);
});

// ── Queries ──

it('returns active likers and boosters as actors', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::like($this->otherActor, $this->post);
    ActivityInteractions::boost($this->actor, $this->post);

    ActivityInteractions::unlike($this->otherActor, $this->post);

    $likers = ActivityInteractions::likers($this->post);
    $boosters = ActivityInteractions::boosters($this->post);

    expect($likers)->toBeInstanceOf(Collection::class)
        ->and($likers->pluck('id')->all())->toBe([$this->actor->id])
        ->and($boosters)->toBeInstanceOf(Collection::class)
        ->and($boosters->pluck('id')->all())->toBe([$this->actor->id]);
});

it('isolates likers and boosters between objects', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::boost($this->otherActor, $this->otherPost);

    expect(ActivityInteractions::likers($this->otherPost))->toHaveCount(0)
        ->and(ActivityInteractions::boosters($this->post))->toHaveCount(0);
});

it('returns an actors active bookmark interactions only', function (): void {
    ActivityInteractions::bookmark($this->actor, $this->post);
    ActivityInteractions::bookmark($this->actor, $this->otherPost);
    ActivityInteractions::bookmark($this->otherActor, $this->post);

    $bookmarks = ActivityInteractions::bookmarks($this->actor);

    expect($bookmarks)->toBeInstanceOf(Collection::class)
        ->and($bookmarks)->toHaveCount(2)
        ->and($bookmarks->pluck('actor_id')->unique()->values()->all())->toBe([$this->actor->id])
        ->and($bookmarks->every(
            fn (Interaction $interaction): bool => $interaction->type === InteractionType::Bookmark
        ))->toBeTrue();

    ActivityInteractions::unbookmark($this->actor, $this->otherPost);

    expect(ActivityInteractions::bookmarks($this->actor))->toHaveCount(1);
});

it('returns recent interactions newest first and respects the limit', function (): void {
    $older = ActivityInteractions::like($this->actor, $this->post);
    $newer = ActivityInteractions::like($this->otherActor, $this->post);

    Interaction::query()->whereKey($older->id)->update(['created_at' => now()->subDay()]);
    Interaction::query()->whereKey($newer->id)->update(['created_at' => now()]);

    $recent = ActivityInteractions::recentForModel($this->post, InteractionType::Like);

    expect($recent)->toHaveCount(2)
        ->and($recent->first()?->id)->toBe($newer->id)
        ->and($recent->last()?->id)->toBe($older->id);

    $limited = ActivityInteractions::recentForModel($this->post, InteractionType::Like, 1);

    expect($limited)->toHaveCount(1)
        ->and($limited->first()?->id)->toBe($newer->id);
});

it('excludes undone interactions from recentForModel', function (): void {
    ActivityInteractions::like($this->actor, $this->post);
    ActivityInteractions::like($this->otherActor, $this->post);

    ActivityInteractions::unlike($this->otherActor, $this->post);

    $recent = ActivityInteractions::recentForModel($this->post, InteractionType::Like);

    expect($recent)->toHaveCount(1)
        ->and($recent->first()?->actor_id)->toBe($this->actor->id);
});

// ── Polymorphic object linking ──

it('links local model interactions by morph class and primary key', function (): void {
    $interaction = ActivityInteractions::like($this->actor, $this->post);

    expect($interaction->object_type)->toBe(InteractionTestPost::class)
        ->and($interaction->object_id)->toBe((string) $this->post->getKey());

    $this->assertDatabaseHas(table: 'interactions', data: [
        'type' => 'Like',
        'object_type' => InteractionTestPost::class,
        'object_id' => (string) $this->post->getKey(),
        'status' => 'Active',
    ]);
});

it('resolves remote url interactions through the query api', function (): void {
    $url = 'https://remote.example/notes/42';
    $remote = (new InteractionRemoteUrl)->withUrl($url);

    Interaction::query()->create([
        'type' => InteractionType::Like,
        'actor_id' => $this->actor->id,
        'object_type' => 'remote_url',
        'object_id' => $url,
        'status' => InteractionStatus::Active,
    ]);

    expect(ActivityInteractions::likeCount($remote))->toBe(1)
        ->and(ActivityInteractions::hasLiked($remote, $this->actor))->toBeTrue()
        ->and(ActivityInteractions::hasLiked($remote, $this->otherActor))->toBeFalse()
        ->and(ActivityInteractions::likers($remote)->pluck('id')->all())->toBe([$this->actor->id])
        ->and(ActivityInteractions::recentForModel($remote, InteractionType::Like))->toHaveCount(1);
});

it('writes remote url interactions through the mutation api', function (): void {
    $url = 'https://remote.example/notes/43';
    $remote = (new InteractionRemoteUrl)->withUrl($url);

    $interaction = ActivityInteractions::like($this->actor, $remote);

    expect($interaction->object_type)->toBe('remote_url')
        ->and($interaction->object_id)->toBe($url)
        ->and($interaction->status)->toBe(InteractionStatus::Active)
        ->and(ActivityInteractions::likeCount($remote))->toBe(1);

    ActivityInteractions::unlike($this->actor, $remote);

    expect(ActivityInteractions::hasLiked($remote, $this->actor))->toBeFalse()
        ->and(ActivityInteractions::likeCount($remote))->toBe(0);
});

// ── Inbound integration ──

it('creates a Like interaction when a Like is received on the inbox', function (): void {
    $url = 'https://remote.example/notes/99';

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => 'https://example.com/users/liker',
            'object' => $url,
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseHas(table: 'interactions', data: [
        'type' => 'Like',
        'object_type' => 'remote_url',
        'object_id' => $url,
        'status' => 'Active',
    ]);

    expect(ActivityInteractions::likeCount((new InteractionRemoteUrl)->withUrl($url)))->toBe(1);
});

it('reverses a Like interaction when Undo(Like) is received', function (): void {
    $url = 'https://remote.example/notes/100';
    $actorUrl = 'https://example.com/users/liker';

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Like',
            'actor' => $actorUrl,
            'object' => $url,
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(status: 202);

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => $actorUrl,
            'object' => [
                'type' => 'Like',
                'actor' => $actorUrl,
                'object' => $url,
            ],
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    // History is preserved: the row still exists, only its status flips.
    $this->assertDatabaseHas(table: 'interactions', data: [
        'type' => 'Like',
        'object_type' => 'remote_url',
        'object_id' => $url,
        'status' => 'Undone',
    ]);

    expect(Interaction::query()->count())->toBe(1)
        ->and(ActivityInteractions::likeCount((new InteractionRemoteUrl)->withUrl($url)))->toBe(0);
});

it('creates an Announce interaction when a boost is received', function (): void {
    $url = 'https://remote.example/notes/101';

    $response = $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => 'https://example.com/users/booster',
            'object' => $url,
            'to' => [$this->actor->actor_id],
        ],
    );

    $response->assertStatus(status: 202);

    $this->assertDatabaseHas(table: 'interactions', data: [
        'type' => 'Announce',
        'object_type' => 'remote_url',
        'object_id' => $url,
        'status' => 'Active',
    ]);

    expect(ActivityInteractions::boostCount((new InteractionRemoteUrl)->withUrl($url)))->toBe(1);
});

it('reverses an Announce interaction when Undo(Announce) is received', function (): void {
    $url = 'https://remote.example/notes/102';
    $actorUrl = 'https://example.com/users/booster';

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Announce',
            'actor' => $actorUrl,
            'object' => $url,
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(status: 202);

    $this->postJson(
        uri: route(name: 'activitypub.actor.inbox.store', parameters: ['actor' => $this->actor->username]),
        data: [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Undo',
            'actor' => $actorUrl,
            'object' => [
                'type' => 'Announce',
                'actor' => $actorUrl,
                'object' => $url,
            ],
            'to' => [$this->actor->actor_id],
        ],
    )->assertStatus(status: 202);

    $this->assertDatabaseHas(table: 'interactions', data: [
        'type' => 'Announce',
        'object_type' => 'remote_url',
        'object_id' => $url,
        'status' => 'Undone',
    ]);

    expect(ActivityInteractions::boostCount((new InteractionRemoteUrl)->withUrl($url)))->toBe(0);
});
