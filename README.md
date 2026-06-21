# Laravel ActivityPub

A self-hosted ActivityPub server for Laravel 13 that enables federation with the Fediverse (Mastodon, Pleroma/Akkoma, Misskey, Pixelfed, PeerTube, etc.).

## Features

**Protocol**
- Actor profiles (Person, ActivityStreams JSON-LD)
- Inbox/Outbox with OrderedCollection pagination
- WebFinger discovery (JRD format)
- NodeInfo 2.0 and host-meta endpoints
- Featured/endorsed collections

**Federation**
- Outbound delivery of Create, Update, Delete, Follow, Accept, Reject, Like, Announce, Undo
- Queue-driven delivery via `DeliverActivity` job with exponential backoff (30s, 2min, 10min) and 3 retries
- Shared inbox optimization: delivery grouped by `shared_inbox_url`, prefers shared inbox over personal inbox
- Follower management with Accept/Block/Reject handling
- Following model for outbound follows
- `Activity` model tracks all federated activities with backed `ActivityStatus` enum (pending, delivered, received, failed)

**HTTP Signatures**
- RSA-SHA256 incoming verification and outbound signing
- Digest header required for non-empty POST bodies
- Signature replay cache with 120s TTL for idempotency
- keyId HTTPS scheme validation
- Canonical host from config used in signing strings

**Strategy Pattern Handlers**
10 handler classes (Follow, Like, Announce, Undo, Create, Delete, Update, Block, Accept, Reject) implement `ActivityHandler`, tagged via the service container, and dispatched by `InboxProcessor`.

**Events**
6 event classes: `ActivityDelivered`, `ActivityDeliveryFailed`, `FollowReceived`, `FollowRemoved`, `BlockReceived`, `InboxActivityReceived`.

**Security**
- SSRF protection via private IP blocking in `WebFingerService` and `RemoteActorResolver`
- Per-IP rate limiting on inbox endpoints (60 requests/min)
- Fediverse interaction rate limiting (30 requests/min)
- Content-Length limit of 1 MB on inbox POST bodies
- Accept header negotiation via `RespondsToAccept` trait (browsers get 406)

**Performance**
- Chunked follower delivery (`chunk(200)`) instead of loading all into memory
- Memoized actor resolution via request-level caches
- Subquery-based timeline queries instead of pluck+whereIn
- Composite indexes for feed performance
- Eager-loading of `remoteActor` relation on followers (N+1 fix)
- Skip data query on paginated collection pages > 1

**Web UI**
- Blade-based Fediverse dashboard with 8 views (dashboard, timeline, inbox, discover, profile, outbox, following, layout)
- Profile editing, follow/unfollow, like, boost, and reply interactions

**Artisan Commands**
- `activitypub:create-actor` — creates a local actor with RSA key pair
- `activitypub:deliver-content` — manually delivers content with `--debug` mode for synchronous delivery with response table
- `activitypub:prune-activities` — prunes old delivered activities

## Installation

```bash
composer require danielpetrica/activitypub-for-laravel
```

Publish the configuration, migrations, and views:

```bash
php artisan vendor:publish --tag=activitypub-config
php artisan vendor:publish --tag=activitypub-migrations
php artisan vendor:publish --tag=activitypub-views
php artisan migrate
```

## Configuration

```env
ACTIVITYPUB_DOMAIN=https://your-domain.com          # Actor identifiers domain (defaults to APP_URL)
ACTIVITYPUB_FEDERATION_ENABLED=true                  # Enable outbound federation
ACTIVITYPUB_FEDIVERSE_ENABLED=true                   # Enable Blade-based fediverse web UI
ACTIVITYPUB_CACHE_ENABLED=true                       # Cache-Control headers on ActivityPub responses
```

The full configuration is published to `config/activitypub.php` and includes settings for routes, HTTP signatures, federation timeouts, user agent, and the actor model class.

## Usage

### Creating an Actor

```bash
php artisan activitypub:create-actor --username=yourname --name="Your Name"
```

### Federating Content

Have your Eloquent model implement `FederatableContentContract` and use the `FederatesContent` trait:

```php
use DanielPetrica\LaravelActivityPub\Contracts\ActorContract;
use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Traits\FederatesContent;

class Post extends Model implements FederatableContentContract
{
    use FederatesContent;

    public function shouldFederate(): bool
    {
        return $this->status === 'published';
    }

    public function activityPubActor(): ActorContract
    {
        return $this->author; // must implement ActorContract
    }

    public function getActivityPubId(): string
    {
        return url("/posts/{$this->id}");
    }

    // ... implement remaining contract methods
}
```

Sending federated activities is handled automatically by the trait's model events, or you can use the facade:

```php
ActivityPub::sendCreate($post);
ActivityPub::sendUpdate($post);
ActivityPub::sendDelete($post->getActivityPubId(), $post->activityPubActor());
```

## Architecture

```
src/
  Actions/
    InboxProcessor.php          -- Dispatches to handlers by activity type
    Handlers/                   -- 10 ActivityHandler implementations
  Contracts/                    -- ActorContract, FederatableContentContract,
  |                                ActivityBuilderContract, ActorProfileContract,
  |                                FederatedActorContract
  Enums/                        -- ActivityStatus, ActivityType, ActivityObjectType,
  |                                FollowerStatus
  Events/                       -- 6 event classes
  Http/
    Controllers/                -- Actor, Inbox, Outbox, Followers, Following,
    |                               WebFinger, NodeInfo, HostMeta, Featured
    |   Concerns/RespondsToAccept.php
    |   Fediverse/              -- 8 Blade UI controllers
    Middleware/
      VerifyHttpSignature.php   -- Incoming signature verification
    Requests/                   -- InboxRequest, WebFingerRequest, ProfileUpdateRequest
    Resources/                  -- Actor, Activity, WebFinger resources, OrderedCollection
  Jobs/
    DeliverActivity.php         -- Queued outbound delivery with backoff
    FetchRemoteActor.php        -- Remote actor resolution
    PruneOldActivities.php      -- Cleanup job
  Models/
    Actor.php                   -- Local actor with RSA key pair
    RemoteActor.php             -- Cached remote actor data
    Follower.php                -- Follower relationships
    Following.php               -- Outbound follow tracking
    Activity.php                -- Federated activity log
  Services/
    ActivityPubService.php      -- Main service (facade backed)
    HttpSignatureService.php    -- Outbound request signing
    DeliveryClient.php          -- Shared HTTP + signing delivery
    RemoteActorResolver.php     -- Fetch/upsert remote actors
    WebFingerService.php        -- Remote WebFinger resolution
    ActivityBuilder.php         -- Build ActivityStreams payloads
  Traits/
    FederatesContent.php        -- Model event hooks for auto-federation
    ResolvesLocalActor.php
  Console/Commands/             -- 3 artisan commands
```

## Testing

```bash
vendor/bin/pest
```

40 Pest tests across 7 test files covering actors, inbox processing, WebFinger, console commands, content delivery, and unit-tested activity building.

## Roadmap

- [ ] Account migration (Move / AlsoKnownAs)
- [ ] Tailwind CSS build step (currently CDN)
- [ ] Block activity filtering from timeline
- [ ] JSON-LD compaction for Pleroma/Akkoma compatibility
- [ ] Filament-based admin dashboard

## License

MIT
