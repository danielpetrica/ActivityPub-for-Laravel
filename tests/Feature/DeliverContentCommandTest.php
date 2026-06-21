<?php

use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Jobs\DeliverActivity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

final class TestFederatableContent extends Model implements FederatableContentContract
{
    protected $table = 'test_federatable_contents';

    protected $fillable = ['title', 'actor_username'];

    public function shouldFederate(): bool
    {
        return true;
    }

    public function activityPubActor(): ActorContract
    {
        $actor = Actor::query()
            ->where(column: 'username', operator: '=', value: $this->actor_username)
            ->first();

        if ($actor === null) {
            throw new RuntimeException('No actor found for test content.');
        }

        return $actor;
    }

    public function getActivityPubId(): string
    {
        return $this->activityPubActor()->getActorId().'/posts/'.$this->id;
    }

    public function getActivityPubType(): string
    {
        return 'Article';
    }

    public function getActivityPubName(): ?string
    {
        return $this->title;
    }

    public function getActivityPubContent(): string
    {
        return 'Test content body.';
    }

    public function getActivityPubSummary(): ?string
    {
        return null;
    }

    public function getActivityPubUrl(): string
    {
        return $this->getActivityPubId();
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
        return $this->activityPubActor()->getFollowersUrl();
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
        'username' => 'testpub',
        'name' => 'Test Publisher',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $this->remoteActor = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://remote.example.com/users/follower',
        'inbox_url' => 'https://remote.example.com/users/follower/inbox',
        'username' => 'follower',
        'domain' => 'remote.example.com',
        'public_key_pem' => $keys['public'],
    ]);

    Follower::query()->create(attributes: [
        'actor_id' => $this->actor->id,
        'remote_actor_id' => $this->remoteActor->id,
        'status' => FollowerStatus::Accepted,
    ]);

    Schema::create(table: 'test_federatable_contents', callback: static function (Blueprint $table): void {
        $table->id();
        $table->string(column: 'title');
        $table->string(column: 'actor_username');
        $table->timestamps();
    });

    config()->set(key: 'activitypub.federation.enabled', value: true);
});

afterEach(function (): void {
    Schema::dropIfExists(table: 'test_federatable_contents');
});

it('fails when model class does not exist', function (): void {
    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => 'Nonexistent\\Model',
        'id' => 1,
    ])->assertFailed();
});

it('fails when record is not found', function (): void {
    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => 999,
    ])->assertFailed();
});

it('fails when model does not implement FederatableContentContract', function (): void {
    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => Actor::class,
        'id' => $this->actor->id,
    ])->assertFailed();
});

it('succeeds and dispatches jobs for a valid content model', function (): void {
    Bus::fake();

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Test Article',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
    ])->assertSuccessful();

    Bus::assertDispatched(DeliverActivity::class);
});

it('succeeds with --actor option override', function (): void {
    Bus::fake();

    $keys = generateTestKeyPair();

    $secondActor = Actor::query()->create(attributes: [
        'username' => 'secondpub',
        'name' => 'Second Publisher',
        'public_key_pem' => $keys['public'],
        'private_key_pem' => $keys['private'],
    ]);

    $secondFollower = RemoteActor::query()->create(attributes: [
        'actor_url' => 'https://other.example.com/users/fan',
        'inbox_url' => 'https://other.example.com/users/fan/inbox',
        'username' => 'fan',
        'domain' => 'other.example.com',
    ]);

    Follower::query()->create(attributes: [
        'actor_id' => $secondActor->id,
        'remote_actor_id' => $secondFollower->id,
        'status' => FollowerStatus::Accepted,
    ]);

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Test Article',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
        '--actor' => 'secondpub',
    ])->assertSuccessful();

    Bus::assertDispatched(DeliverActivity::class);
});

it('warns when federation is disabled', function (): void {
    config()->set(key: 'activitypub.federation.enabled', value: false);

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Test Article',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
    ])->assertSuccessful();
});

it('debug mode delivers synchronously with response codes', function (): void {
    Http::fake([
        'remote.example.com/users/follower/inbox' => Http::response(status: 202),
    ]);

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Debug Article',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
        '--debug' => true,
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://remote.example.com/users/follower/inbox'
            && $request->method() === 'POST'
            && $request->hasHeader('Signature');
    });
});

it('debug mode reports response codes in a table', function (): void {
    Http::fake([
        'remote.example.com/users/follower/inbox' => Http::response(status: 202),
    ]);

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Debug Article',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
        '--debug' => true,
    ])->expectsTable(
        headers: ['Actor', 'Inbox URL', 'Response Code'],
        rows: [
            ['https://remote.example.com/users/follower', 'https://remote.example.com/users/follower/inbox', '202'],
        ],
    );
});

it('debug mode still validates federatable content', function (): void {
    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => Actor::class,
        'id' => $this->actor->id,
        '--debug' => true,
    ])->assertFailed();
});

it('debug mode without federation shows warning', function (): void {
    config()->set(key: 'activitypub.federation.enabled', value: false);

    $content = TestFederatableContent::query()->create(attributes: [
        'title' => 'Debug No Fed',
        'actor_username' => 'testpub',
    ]);

    $this->artisan(command: 'activitypub:deliver-content', parameters: [
        'model' => TestFederatableContent::class,
        'id' => $content->id,
        '--debug' => true,
    ])->assertSuccessful();
});
