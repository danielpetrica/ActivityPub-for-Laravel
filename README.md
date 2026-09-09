# danielpetrica/activitypub-for-laravel

[![LaraPlugins.io: danielpetrica/activitypub-for-laravel info card](https://laraplugins.io/api/infocard/danielpetrica/activitypub-for-laravel?design=facts&theme=light)](https://laraplugins.io/plugins/danielpetrica/activitypub-for-laravel)

A self-hosted ActivityPub server for Laravel 13 that enables federation with the Fediverse (Mastodon, Pleroma/Akkoma, Misskey, Pixelfed, PeerTube, etc.).

---

[![LaraPlugins.io: danielpetrica/activitypub-for-laravel](https://laraplugins.io/api/infocard/danielpetrica/activitypub-for-laravel?design=badge&theme=light)](https://laraplugins.io/plugins/danielpetrica/activitypub-for-laravel)

## Features

**Protocol**
- Actor profiles (Person, ActivityStreams JSON-LD)
- Inbox/Outbox with OrderedCollection pagination
- WebFinger discovery (JRD format)
- NodeInfo 2.0 and host-meta endpoints
- Featured/endorsed collections
- Mastodon spec compliance (discoverable, published fields)
- Accept activity always delivered even when federation is disabled

**Federation**
- Outbound delivery of Create, Update, Delete, Follow, Accept, Reject, Like, Announce, Undo
- Queue-driven delivery via `DeliverActivity` job with exponential backoff (30s, 2min, 10min) and 3 retries
- Shared inbox optimization: delivery grouped by `shared_inbox_url`, prefers shared inbox over personal inbox
- Follower management with Accept/Block/Reject handling
- Following model for outbound follows
- Post forwarding on first follow from a domain (`ForwardPostsToNewServerJob`)
- Block domains and users with auto-rejection of follows
- `reject()` method in ActivityBuilder
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
- Blade-based Fediverse dashboard with 11 views (dashboard, timeline, inbox, discover, profile, outbox, following, followers, servers, status, layout)
- Federated servers page (/fediverse/servers) showing all remote domains with follower counts
- Block/unblock servers and users from the UI
- Status/diagnostics page (/fediverse/status) showing queue health, federation status, activity counts
- Delivery status badges (Pending/Delivered/Failed) on outbox and dashboard
- Pinned and Published badges on activities
- Followers page with domain and status filters
- Profile editing, follow/unfollow, like, boost, and reply interactions

**Content Forwarding**
- Automatic post forwarding to new servers on first follow from a domain
- Configurable via `federatable_models` config option
- Delivers 3 oldest published posts from registered content models
- `isActivityPubPinned()` method on FederatableContentContract

**Blocking**
- Block entire domains — auto-rejects all follows from that domain
- Block individual remote actors — auto-rejects follows from that actor
- Reject activity sent to blocked actors
- Stored in `blocked_domains` and `blocked_remote_actors` database tables

**NodeInfo 2.0**
- Full NodeInfo 2.0 compliance with software name, version, homepage, repository
- `usage.localPosts` — count of outbound Create activities
- `usage.users.activeMonth` — actors active in last 30 days
- `last_active_at` tracking on outbound activities

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
ACTIVITYPUB_LOGGING_ENABLED=true                     # Enable detailed federation logging
ACTIVITYPUB_LOG_CHANNEL=activitypub                  # Log channel (optional, defaults to app default)
ACTIVITYPUB_LOG_LEVEL=info                           # Log level: debug, info, warning, error
ACTIVITYPUB_RESOLVE_TIMEOUT=10                       # Timeout for WebFinger/actor resolution (seconds)
ACTIVITYPUB_DEBUG_DISPLAY=false                      # Show debug info in inbox/outbox views
ACTIVITYPUB_QUEUE_CONNECTION=redis                   # Queue connection (null = app default)
ACTIVITYPUB_QUEUE_NAME=default                       # Queue name for activity delivery
ACTIVITYPUB_FEDIVERSE_GATE=null                       # Gate name for authorization (null = any authenticated user)
```

The full configuration is published to `config/activitypub.php` and includes settings for routes, HTTP signatures, federation timeouts, and the actor model class.

> **⚠️ Warning:** The plugin encrypts actor private keys using your `APP_KEY`. If you change or rotate your `APP_KEY`, all existing actors' HTTP signatures will break and federation will stop working. Back up your `APP_KEY` before rotating it.

## Authorization

The Fediverse dashboard is protected by Laravel's gate system. By default, any authenticated user can access it. To restrict access, define a gate in your `AuthServiceProvider` and configure it:

```php
// app/Providers/AuthServiceProvider.php
public function boot(): void
{
    Gate::define('viewFediverse', fn ($user) => $user->isAdmin);
}
```

```env
ACTIVITYPUB_FEDIVERSE_GATE=viewFediverse
```

If `ACTIVITYPUB_FEDIVERSE_GATE` is null (default), all authenticated users can access the dashboard.

## Debugging

Federation issues (failed follows, unreachable remote actors) can be hard to diagnose in production. Enable detailed logging to trace the full resolution flow:

```env
ACTIVITYPUB_LOGGING_ENABLED=true
ACTIVITYPUB_LOG_CHANNEL=activitypub   # optional — uses app default if omitted
ACTIVITYPUB_LOG_LEVEL=info            # debug|info|warning|error
```

When enabled, the package logs every step of remote actor resolution:

- WebFinger discovery requests and results
- HTTP status codes and response bodies from remote servers
- SSRF protection blocks
- HTTP signature signing decisions
- Activity delivery outcomes

Example log output:

```
[2026-09-06 12:00:00] local.INFO: ActivityPub: Resolving handle {"handle":"user@mastodon.social"}
[2026-09-06 12:00:00] local.INFO: ActivityPub: WebFinger lookup {"resource":"acct:user@mastodon.social"}
[2026-09-06 12:00:01] local.INFO: ActivityPub: WebFinger resolved {"href":"https://mastodon.social/users/user"}
[2026-09-06 12:00:01] local.INFO: ActivityPub: Fetching remote actor {"actorUri":"https://mastodon.social/users/user"}
[2026-09-06 12:00:02] local.WARNING: ActivityPub: Remote actor HTTP fetch failed {"actorUri":"...","statusCode":403}
```

Point your log channel to a file or external service to capture these in production:

```php
// config/logging.php
'channels' => [
    'activitypub' => [
        'driver' => 'daily',
        'path' => storage_path('logs/activitypub.log'),
        'days' => 14,
    ],
],
```

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

### Post Forwarding

When the first user from a remote server follows your account, the plugin can automatically forward your oldest posts to populate their server with your content.

#### Setup

1. Register your content models in the config:

```php
// config/activitypub.php
'federatable_models' => [
    \App\Models\Post::class,
    \App\Models\Article::class,
],
```

2. Each model must implement `FederatableContentContract` and use the `FederatesContent` trait (see above).

3. Enable federation:
```env
ACTIVITYPUB_FEDERATION_ENABLED=true
```

#### How it works

- When the first follower from a domain follows you, `ForwardPostsToNewServerJob` is dispatched
- It queries your registered `federatable_models` for the 3 oldest published posts (`shouldFederate() == true`)
- Recreates fresh `Create` activities and delivers them to the remote server's inbox
- Only fires once per domain (first follower trigger)

#### Manual forwarding

You can also manually trigger post forwarding from the Fediverse dashboard:

1. Go to `/fediverse/status`
2. Click "Forward Posts" in the Maintenance section
3. This sends your posts to all current follower servers

#### Pinned posts

Implement `isActivityPubPinned()` on your model to mark posts as pinned:

```php
public function isActivityPubPinned(): bool
{
    return $this->is_pinned;
}
```

Pinned posts are displayed with a "Pinned" badge in the outbox view.

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
    |                               WebFinger, NodeInfo, HostMeta, Featured, FederatedServersController
    |   Concerns/RespondsToAccept.php
    |   Fediverse/              -- 9 Blade UI controllers (StatusController, FollowersController, ...)
    Middleware/
      VerifyHttpSignature.php   -- Incoming signature verification
    Requests/                   -- InboxRequest, WebFingerRequest, ProfileUpdateRequest
    Resources/                  -- Actor, Activity, WebFinger resources, OrderedCollection
  Jobs/
    DeliverActivity.php         -- Queued outbound delivery with backoff
    FetchRemoteActor.php        -- Remote actor resolution
    PruneOldActivities.php      -- Cleanup job
    ForwardPostsToNewServerJob.php -- Forward posts to new follower's server
  Models/
    Actor.php                   -- Local actor with RSA key pair
    RemoteActor.php             -- Cached remote actor data
    Follower.php                -- Follower relationships
    Following.php               -- Outbound follow tracking
    Activity.php                -- Federated activity log
    BlockedDomain.php           -- Domain blocklist
    BlockedRemoteActor.php      -- Actor blocklist
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

119+ Pest tests across 12 test files covering actors, inbox processing, WebFinger, console commands, content delivery, Mastodon spec compliance, HTTP signatures, blocking, post forwarding, and unit-tested activity building.

## Roadmap

- [ ] Account migration (Move / AlsoKnownAs)
- [ ] Tailwind CSS build step (currently CDN)
- [ ] JSON-LD compaction for Pleroma/Akkoma compatibility
- [ ] Filament-based admin dashboard

## License

MIT
