<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MentionTestPost extends Model implements FederatableContentContract
{
    use FederatesContent;

    protected $table = 'mention_test_posts';

    protected $fillable = ['body'];

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
    Http::fake([]);

    Schema::create('mention_test_posts', function (Blueprint $table): void {
        $table->id();
        $table->text('body')->nullable();
        $table->timestamps();
    });

    $keys = generateTestKeyPair();

    $this->actor = Actor::query()->create([
        'username' => 'mentioner',
        'name' => 'Mentioner',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    config()->set('activitypub.http_signatures.enabled', false);
    config()->set('activitypub.federation.enabled', false);
});

afterEach(function (): void {
    Schema::dropIfExists('mention_test_posts');
});

it('emits a Mention tag, adds the actor to cc, and delivers to their inbox', function (): void {
    config()->set('activitypub.federation.enabled', true);

    $bob = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example/users/bob',
        'inbox_url' => 'https://remote.example/users/bob/inbox',
        'username' => 'bob',
        'domain' => 'remote.example',
    ]);

    Bus::fake();

    MentionTestPost::query()->create(['body' => 'Hello @bob@remote.example how are you?']);

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();

    $tags = $record->payload['object']['tag'] ?? [];

    expect($tags)->toContain([
        'type' => 'Mention',
        'href' => $bob->actor_url,
        'name' => '@bob@remote.example',
    ]);

    expect($record->payload['object']['cc'])->toContain($bob->actor_url);
    expect($record->payload['cc'])->toContain($bob->actor_url);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($bob) {
        return $job->inboxUrl === $bob->inbox_url;
    });
});

it('lowercases the host in the mention name', function (): void {
    config()->set('activitypub.federation.enabled', true);

    RemoteActor::query()->create([
        'actor_url' => 'https://Remote.Example/users/casey',
        'inbox_url' => 'https://Remote.Example/users/casey/inbox',
        'username' => 'casey',
        'domain' => 'remote.example',
    ]);

    Bus::fake();

    MentionTestPost::query()->create(['body' => 'Hi @casey@REMOTE.EXAMPLE']);

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    $tags = $record->payload['object']['tag'] ?? [];

    expect($tags)->toContain([
        'type' => 'Mention',
        'href' => 'https://Remote.Example/users/casey',
        'name' => '@casey@remote.example',
    ]);
});

it('does not throw and emits no tag for an unresolvable mention', function (): void {
    config()->set('activitypub.federation.enabled', true);

    Bus::fake();

    $post = MentionTestPost::query()->create(['body' => 'Hi @ghost@nowhere.invalid']);

    expect($post->id)->not->toBeNull();

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    expect($record)->not->toBeNull();
    expect($record->payload['object']['tag'] ?? [])->toBe([]);

    Bus::assertNotDispatched(DeliverActivity::class);
});

it('attaches mentions to a reply Note and delivers to personal inboxes', function (): void {
    config()->set('activitypub.federation.enabled', true);

    $carol = RemoteActor::query()->create([
        'actor_url' => 'https://remote.example/users/carol',
        'inbox_url' => 'https://remote.example/users/carol/inbox',
        'username' => 'carol',
        'domain' => 'remote.example',
    ]);

    Bus::fake();

    app(ActivityPubService::class)->sendNote(
        actor: $this->actor,
        content: 'Replying to @carol@remote.example',
        inReplyToUrl: 'https://remote.example/notes/42',
    );

    $record = Activity::query()
        ->where('type', 'Create')
        ->where('is_incoming', false)
        ->latest()
        ->first();

    $tags = $record->payload['object']['tag'] ?? [];

    expect($tags)->toContain([
        'type' => 'Mention',
        'href' => $carol->actor_url,
        'name' => '@carol@remote.example',
    ]);

    expect($record->payload['cc'])->toContain($carol->actor_url);

    Bus::assertDispatched(DeliverActivity::class, function ($job) use ($carol) {
        return $job->inboxUrl === $carol->inbox_url;
    });
});
