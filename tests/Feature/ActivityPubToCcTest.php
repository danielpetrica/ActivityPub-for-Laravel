<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ToCcTestPost extends Model implements FederatableContentContract
{
    use FederatesContent;

    protected $table = 'to_cc_test_posts';

    protected $fillable = ['title'];

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
    Schema::create('to_cc_test_posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->timestamps();
    });

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'testactor',
        'name' => 'Test Actor',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.federation.enabled', false);
    config()->set('activitypub.http_signatures.enabled', false);
});

afterEach(function (): void {
    Schema::dropIfExists('to_cc_test_posts');
});

it('records a FederatesContent Create with a flat to array', function (): void {
    ToCcTestPost::query()->create(['title' => 'Hello']);

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();
    expect($record->payload['to'])->toBe(['https://www.w3.org/ns/activitystreams#Public']);
    expect($record->payload['object']['to'])->toBe(['https://www.w3.org/ns/activitystreams#Public']);
});

it('records a FederatesContent Create with a flat cc array', function (): void {
    ToCcTestPost::query()->create(['title' => 'Hello']);

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    $cc = $record->payload['cc'];
    $objectCc = $record->payload['object']['cc'];

    expect($cc)->toBeArray();
    expect($cc)->toBe([$this->actor->followers_url]);
    expect(array_filter($cc, 'is_string'))->toBe($cc);

    expect($objectCc)->toBeArray();
    expect(array_filter($objectCc, 'is_string'))->toBe($objectCc);
});

it('normalizes nested to/cc for sendCreateForActor', function (): void {
    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example/users/bob',
        'inbox_url' => 'https://remote.example/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'remote.example',
    ]);

    $post = new ToCcTestPost(['title' => 'Hello']);
    $post->save();

    app(ActivityPubService::class)->sendCreateForActor(
        content: $post,
        actor: $this->actor,
        target: $remoteActor,
    );

    $records = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->get();

    foreach ($records as $record) {
        expect(array_filter($record->payload['to'], 'is_string'))->toBe($record->payload['to']);
        expect(array_filter($record->payload['cc'], 'is_string'))->toBe($record->payload['cc']);
        expect($record->payload['to'])->toBe(['https://www.w3.org/ns/activitystreams#Public']);
    }
});

it('normalizes nested to/cc for sendCreateForActor with an explicit to', function (): void {
    $remoteActor = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example/users/erin',
        'inbox_url' => 'https://remote.example/users/erin/inbox',
        'username' => 'erin',
        'domain' => 'remote.example',
    ]);

    $post = new ToCcTestPost(['title' => 'Hello']);
    $post->save();

    $record = app(ActivityPubService::class)->sendCreateForActor(
        content: $post,
        actor: $this->actor,
        target: $remoteActor,
        to: 'https://remote.example/users/erin',
    );

    expect($record->payload['to'])->toBe(['https://remote.example/users/erin']);
    expect($record->payload['object']['to'])->toBe(['https://remote.example/users/erin']);
});

it('records a flat to/cc for sendUpdate', function (): void {
    $post = ToCcTestPost::query()->create(['title' => 'Hello']);

    app(ActivityPubService::class)->sendUpdate(content: $post);

    $record = Activity::query()
        ->where('type', 'Update')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();
    expect($record->payload['to'])->toBe(['https://www.w3.org/ns/activitystreams#Public']);
    expect(array_filter($record->payload['cc'], 'is_string'))->toBe($record->payload['cc']);
});
