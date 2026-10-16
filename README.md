# danielpetrica/activitypub-for-laravel

[![LaraPlugins.io: danielpetrica/activitypub-for-laravel info card](https://laraplugins.io/api/infocard/danielpetrica/activitypub-for-laravel?design=facts&theme=light)](https://laraplugins.io/plugins/danielpetrica/activitypub-for-laravel)

A **server-to-server (S2S)** ActivityPub federation implementation for Laravel 13. It lets your Laravel application publish actors, inboxes, and outboxes that federate with the wider Fediverse (Mastodon, Pleroma/Akkoma, Misskey, Pixelfed, PeerTube, etc.).

> **Scope:** This package implements the ActivityPub **server-to-server (S2S)** profile only. Client-to-server (C2S) and OAuth 2.0 authorization are explicitly **out of scope**. Mobile and desktop clients such as Tusky or Elk authenticate against an instance using the C2S API and OAuth, neither of which this package provides, so they cannot log into your application through it.

---

## Requirements / Support policy

| Requirement | Version |
| --- | --- |
| PHP | `^8.5` |
| Laravel | `^13.0` |
| PHP extension | `ext-curl`, `ext-openssl`, `ext-json` |

`ext-curl` is required: it backs the outbound federation transport and the
`SsrfGuard` connection pin (`CURLOPT_RESOLVE`) that closes the DNS-rebinding
window between the pre-flight SSRF check and the actual connect.

The PHP 8.5 and Laravel 13 floor is **intentional**, not an oversight. This package targets current language and framework features and does not backport to older releases. Older PHP and Laravel versions are **unsupported**: bug reports and pull requests whose only purpose is to re-enable older runtimes will not be accepted.

---

[![LaraPlugins.io: danielpetrica/activitypub-for-laravel](https://laraplugins.io/api/infocard/danielpetrica/activitypub-for-laravel?design=badge&theme=light)](https://laraplugins.io/plugins/danielpetrica/activitypub-for-laravel)

## Features

**Protocol**
- Actor profiles (Person, ActivityStreams JSON-LD) with stable, absolute actor IRIs
- Collection endpoints (inbox, outbox, followers, following, featured) return a top-level `OrderedCollection`; the items live on `OrderedCollectionPage` documents reachable through `?page=N` (real, spec-compliant pagination)
- WebFinger discovery (JRD format), NodeInfo 2.0 and host-meta endpoints completed (scheme, profile-page/subscribe links)
- Featured/endorsed collections
- Mastodon spec compliance (discoverable, published fields)
- Content fidelity: flat `to`/`cc` addressing, `tag`/mention objects, note `url`, opt-in object-level `sensitive` and custom emoji tags
- Accept activity always delivered even when federation is disabled

**Federation**
- Outbound delivery of Create, Update, Delete, Follow, Accept, Reject, Like, Announce, Undo, Block, Undo(Block) and Move
- Address resolution to the real object author (`deliverToObjectAuthor`) instead of short-circuiting to the shared inbox
- `@user@host` mentions resolved and delivered to each mention's inbox
- Queue-driven delivery via the `DeliverActivity` job with exponential backoff and per-inbox `activity_deliveries` tracking (reschedule and dead-letter handling)
- Shared inbox optimization: delivery grouped by `shared_inbox_url`, prefers the shared inbox over the personal inbox
- Follower management with Accept/Block/Reject handling and locked-account approval (`approveFollower` / `rejectFollower`)
- Following model for outbound follows
- Post forwarding on first follow from a domain (`ForwardPostsToNewServerJob`)
- Block domains and users with auto-rejection of follows, and outbound `Block` / `Undo(Block)`
- `Update(Person)` profile federation and `Move` account migration with `alsoKnownAs`
- `Activity` model tracks all federated activities with a backed `ActivityStatus` enum (pending, delivered, received, failed)
- Queued inbox processing by default (`ProcessInboxActivity`), central normalized block enforcement and activity-id idempotency

**Interaction Tracking**
- Polymorphic `Interaction` model for likes, boosts, bookmarks, quotes, comments
- `InteractionType` enum (Like, Announce, Bookmark, Quote, Comment)
- `InteractionStatus` enum (Active, Undone) for undo support
- Incoming Like/Announce automatically tracked as interactions
- Incoming Undo(Like)/Undo(Announce) properly reverses interactions
- The `ActivityInteractions` helper deduplicates repeated local like/boost/bookmark calls and reactivates a previously undone interaction
- Static `ActivityInteractions` API for integrating clients (count, check, mutate, query)
- Favorites tab in the Fediverse UI

**HTTP Signatures**
- RSA-SHA256 incoming verification and outbound signing
- Minimum signed-component enforcement (required covered headers) and RFC 9421 (`signature-input` / structured fields) support
- Digest header required for non-empty POST bodies
- Signature replay cache with 120s TTL for idempotency (constant-time comparison)
- keyId HTTPS scheme validation
- Canonical host from config used in signing strings
- Parsing and verification split into `SignatureParser` and `SignatureVerifier`

**Strategy Pattern Handlers**
12 handler classes (Follow, Like, Announce, Undo, Create, Delete, Update, Block, Accept, Reject, Flag, Move) implement `ActivityHandler`, tagged via the service container, and dispatched by `InboxProcessor`. Custom handlers can be registered through `HandlerRegistry`.

**Events**
7 event classes: `ActivityDelivered`, `ActivityDeliveryFailed`, `FollowReceived`, `FollowRemoved`, `BlockReceived`, `InboxActivityReceived`, `FlagReceived`.

**Moderation**
- Incoming `Flag` activities are recorded as `Report` rows and surface on `/fediverse/status`
- Incoming `Flag` also fires the `FlagReceived` event for application hooks

**Security**
- SSRF hardening (`SsrfGuard`): outbound fetches are HTTPS-only and must resolve exclusively to public IPs; `security.allowed_hosts` is the explicit escape hatch
- Unknown signers are fetched off the verification path (`http_signatures.queue_unknown_actors`)
- Central normalized block enforcement for every inbound activity (not just follows)
- Inbox allowlist (normalized domains) and windowed per-actor/per-domain quotas that fail open
- Always-on rejected-request audit log (`inbox_logs`) with configurable retention
- Admin gate (`fediverse.admin_gate`) required for mutating Fediverse routes
- Per-IP rate limiting on inbox endpoints (60 requests/min)
- Fediverse interaction rate limiting (30 requests/min)
- Content-Length limit of 1 MB on inbox POST bodies
- Accept header negotiation via `RespondsToAccept` trait (browsers get 406)

**Operations**
- Queued inbox processing by default (`queue.process_inbox`): the request returns `202` immediately and remote resolution runs on a worker
- Per-inbox delivery records (`activity_deliveries`) with reschedule and dead-letter handling from `/fediverse/status`
- Always-on inbox audit log with configurable retention windows
- `activitypub:prune-activities` honours `retention.*` for delivered/received activities, inbox logs and interactions

**Extension points**
- Swap the actor model via `activitypub.actor_model` (bound in the container and used for route binding)
- Register custom activity handlers through `HandlerRegistry`
- Swap the activity builder (`ActivityBuilderContract`), dispatcher (`ActivityDispatcher`) and recorder (`ActivityRecorder`) bindings

**Performance**
- Chunked follower delivery (`chunk(200)`) instead of loading all into memory
- Memoized actor resolution via request-level caches
- Subquery-based timeline queries instead of pluck+whereIn
- Composite indexes for feed performance
- Eager-loading of `remoteActor` relation on followers (N+1 fix)

**Web UI**
- Blade-based Fediverse dashboard with 12 views (dashboard, timeline, inbox, discover, profile, outbox, following, followers, servers, status, favorites, layout)
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
- Delivers 3 newest published posts from registered content models
- `isActivityPubPinned()` method on FederatableContentContract

**Blocking**
- Block entire domains — every inbound activity from that domain is dropped centrally by `InboxProcessor`; incoming follow requests are additionally auto-rejected with a `Reject` activity and no follower is created
- Block individual remote actors — every inbound activity from that actor is dropped centrally; incoming follow requests are auto-rejected
- Outbound `Block` and `Undo(Block)` activities are delivered to the blocked actor's inbox
- Blocks are stored in the `blocked_domains` and `blocked_remote_actors` database tables and managed from the Fediverse UI

> Blocking is enforced centrally for **all** inbound activity types, through normalized domain matching (`DomainNormalizer`), so casing, trailing dots, ports, `www.` and IPv4-mapped IPv6 hosts cannot be used to bypass it. It does not retroactively remove existing followers.

**NodeInfo 2.0**
- Full NodeInfo 2.0 compliance with software name, version, homepage, repository
- `usage.localPosts` — count of outbound Create activities
- `usage.users.activeMonth` — actors active in last 30 days
- `last_active_at` tracking on outbound activities

**Artisan Commands**
- `activitypub:create-actor` — creates a local actor with RSA key pair
- `activitypub:deliver-content` — manually delivers content with `--debug` mode for synchronous delivery with response table
- `activitypub:prune-activities` — prunes old federation data (delivered/received activities, the inbox audit log and stale interactions) using the `retention.*` windows (all default to 30 days); per-run overrides via `--days`, `--received-days`, `--inbox-logs-days`, `--interactions-days` and `--dry-run`. Registered on Laravel's scheduler to run **daily** (`withoutOverlapping()`), so it is enforced automatically once `php artisan schedule:run` is wired to cron.

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
# Identity / federation
ACTIVITYPUB_DOMAIN=https://your-domain.com               # Actor identifiers domain (defaults to APP_URL)
ACTIVITYPUB_FEDERATION_ENABLED=true                      # Enable outbound federation
ACTIVITYPUB_FEDIVERSE_ENABLED=true                       # Enable Blade-based fediverse web UI
ACTIVITYPUB_RESOLVE_TIMEOUT=10                           # Timeout for WebFinger/actor resolution (seconds)

# Queue (delivery + inbound processing)
ACTIVITYPUB_QUEUE_CONNECTION=redis                       # Queue connection (null = app default)
ACTIVITYPUB_QUEUE_NAME=default                           # Queue name for activity delivery
ACTIVITYPUB_QUEUE_PROCESS_INBOX=true                     # Process inbox activities on the queue (recommended in production)

# Cache / logging / debug
ACTIVITYPUB_CACHE_ENABLED=true                           # Cache-Control headers on ActivityPub responses
ACTIVITYPUB_LOGGING_ENABLED=true                         # Enable detailed federation logging
ACTIVITYPUB_LOG_CHANNEL=activitypub                      # Log channel (optional, defaults to app default)
ACTIVITYPUB_LOG_LEVEL=info                               # Log level: debug, info, warning, error
ACTIVITYPUB_DEBUG_DISPLAY=false                          # Show debug info in inbox/outbox views

# Fediverse dashboard authorization
ACTIVITYPUB_FEDIVERSE_GATE=null                          # Gate for dashboard access (null = any authenticated user)
ACTIVITYPUB_FEDIVERSE_ADMIN_GATE=activitypub.fediverse.admin  # Gate required for mutating routes

# Inbox audit log
ACTIVITYPUB_INBOX_LOG_ENABLED=true                       # Record rejected inbox requests (always-on audit trail)

# Inbox admission control
ACTIVITYPUB_INBOX_ALLOWLIST_ENABLED=false                # Restrict inbound federation to allowed domains
ACTIVITYPUB_INBOX_QUOTAS_ENABLED=true                    # Windowed per-actor/per-domain inbox quotas
ACTIVITYPUB_INBOX_PER_ACTOR_PER_MINUTE=120
ACTIVITYPUB_INBOX_PER_DOMAIN_PER_MINUTE=600
ACTIVITYPUB_INBOX_PER_ACTOR_PER_DAY=5000
ACTIVITYPUB_INBOX_INTERACTIONS_PER_ACTOR_PER_DAY=1000

# Retention (used by activitypub:prune-activities, scheduled daily by default)
ACTIVITYPUB_RETENTION_DELIVERED_DAYS=30
ACTIVITYPUB_RETENTION_RECEIVED_DAYS=30
ACTIVITYPUB_RETENTION_INBOX_LOGS_DAYS=30
ACTIVITYPUB_RETENTION_INTERACTIONS_DAYS=30

# Outbound fetch hardening
ACTIVITYPUB_MAX_RESPONSE_BYTES=2000000                   # Max remote fetch response size (bytes)
```

A few of the new settings are config-file only (no environment variable), because they are lists or security policy rather than scalars:

- `security.allowed_hosts` — hosts exempt from DNS resolution checks during outbound fetches (private IP literals are still rejected). Intended for trusted internal test targets only.
- `http_signatures.queue_unknown_actors` (default `true`) — queue a `FetchRemoteActor` job instead of fetching synchronously on the signature-verification request path.
- `inbox.allowlist.domains` — the allowed domain list, matched through `DomainNormalizer`. Enabling the allowlist with an empty list is treated as "not configured" and allows traffic.
- `activitypub.actor_model` — the actor model class used for DI and route binding.

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

### Admin gate for mutating routes

Mutating Fediverse routes that change instance-wide state (blocking domains and actors, pruning, rescheduling deliveries, refreshing followed accounts and forwarding posts) require an additional admin authorization. By default the package checks the `activitypub.fediverse.admin` gate:

```php
// app/Providers/AuthServiceProvider.php
Gate::define('activitypub.fediverse.admin', fn ($user) => $user->isAdmin());
```

Configure a different ability with `ACTIVITYPUB_FEDIVERSE_ADMIN_GATE` (config key `fediverse.admin_gate`). When the configured ability is **not defined**, the mutating routes deny by default (`403`), so a fresh install must define the ability before those controls can be used.

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

The facade also exposes the interaction and moderation surface:

```php
use DanielPetrica\LaravelActivityPub\ActivityPub;

// Interactions
ActivityPub::sendLike($actor, $objectUrl);
ActivityPub::sendAnnounce($actor, $objectUrl);
ActivityPub::sendNote($actor, $content, $inReplyToUrl);

// Block / unblock (delivered to the target inbox)
ActivityPub::sendBlock($actor, $remoteActorUrl);

// Locked account: approve or reject a pending follower
ActivityPub::approveFollower($actor, $follower);
ActivityPub::rejectFollower($actor, $follower);

// Profile change and account migration
ActivityPub::updateActorProfile($actor);                 // emits Update(Person) to followers
ActivityPub::sendMove($actor, $newActorUrl, $alsoKnownAs); // emits Move to followers and target
```

`@user@host` mentions found in published content are resolved automatically and delivered to each mentioned actor's inbox; no extra call is required.

#### Content fidelity (sensitive and custom emoji)

Object-level `sensitive` (NSFW) and custom emoji are **opt-in**: the built object is unchanged unless your content model (or actor) explicitly exposes them, so existing models keep byte-identical wire output.

```php
class Post extends Model implements FederatableContentContract
{
    // Object-level NSFW flag. Any of these is detected:
    //   getActivityPubSensitive(): bool
    //   isActivityPubSensitive(): bool
    //   the `sensitive` or `activitypub_sensitive` attribute
    public function isActivityPubSensitive(): bool
    {
        return $this->is_nsfw;
    }

    // Shortcode => URL map. Any of these is detected:
    //   getActivityPubEmoji(): array
    //   getActivityPubEmojis(): array
    //   the `activitypub_emoji` attribute
    public function getActivityPubEmoji(): array
    {
        return ['blobcat' => 'https://example.com/emoji/blobcat.png'];
    }
}
```

Custom emoji are emitted as ActivityStreams `Emoji` tags (`{"type":"Emoji","name":":blobcat:","icon":{"type":"Image","url":"..."}}`) merged after any tags returned by `getActivityPubTags()`. Malformed or empty entries are skipped, never thrown.

### Activity Interactions

The package tracks likes, boosts, bookmarks, and other interactions via a polymorphic `Interaction` model. This data is available for both the Fediverse UI and integrating clients (e.g., your blog's frontend).

#### Static API

```php
use DanielPetrica\LaravelActivityPub\Services\ActivityInteractions;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;

// ── Counts ──
ActivityInteractions::likeCount($post);      // int
ActivityInteractions::boostCount($post);     // int
ActivityInteractions::bookmarkCount($post);  // int
ActivityInteractions::commentCount($post);   // int
ActivityInteractions::quoteCount($post);     // int

// ── Boolean checks ──
ActivityInteractions::hasLiked($post, $user);    // bool
ActivityInteractions::hasBoosted($post, $user);   // bool
ActivityInteractions::hasBookmarked($post, $user); // bool

// ── Mutations ──
ActivityInteractions::like($user, $post);      // Interaction
ActivityInteractions::unlike($user, $post);    // void
ActivityInteractions::boost($user, $post);     // Interaction
ActivityInteractions::unboost($user, $post);   // void
ActivityInteractions::bookmark($user, $post);  // Interaction
ActivityInteractions::unbookmark($user, $post); // void

// ── Queries ──
ActivityInteractions::likers($post);           // Collection<Actor>
ActivityInteractions::boosters($post);          // Collection<Actor>
ActivityInteractions::bookmarks($user);         // Collection<Interaction>
ActivityInteractions::recentForModel($post, InteractionType::Like, 20); // Collection
```

#### Displaying counts in your app

```blade
{{-- In a Blade view for a blog post --}}
@php
    use DanielPetrica\LaravelActivityPub\Services\ActivityInteractions;
@endphp

<button>
    ❤️ {{ ActivityInteractions::likeCount($post) }}
</button>
<button>
    🔁 {{ ActivityInteractions::boostCount($post) }}
</button>
<button>
    🔖 {{ ActivityInteractions::bookmarkCount($post) }}
</button>
```

#### How incoming interactions work

When a remote user likes or boosts your content, the package:
1. Receives the Like/Announce activity via the inbox
2. Resolves the remote actor
3. Creates an `Interaction` record (type=Like/Announce, status=Active)
4. Stores it alongside the Activity log record

When a remote user undoes a like/boost:
1. Receives the Undo activity
2. Finds the matching active Interaction
3. Sets its status to `Undone` (soft undo, preserves history)

#### Object linking

Interactions link to content via polymorphic columns:
- `object_type` — the model class (e.g., `App\Models\Post`) for local content, or `'remote_url'` for remote content
- `object_id` — the model's primary key for local content, or the full URL string for remote content

This means `likeCount($post)` works for both local models and remote URLs referenced in the timeline.

#### Favorites

The Fediverse UI includes a `/fediverse/favorites` page that shows all bookmarked posts. Use `ActivityInteractions::bookmarks($user)` to build custom favorites views.

### Post Forwarding

When the first user from a remote server follows your account, the plugin can automatically forward your newest posts to populate their server with your content.

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
- It queries your registered `federatable_models` for the 3 newest published posts (`shouldFederate() == true`)
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
    InboxProcessor.php          -- Central admission control + dispatches to handlers by activity type
    Handlers/                   -- 12 ActivityHandler implementations
  Contracts/                    -- ActorContract, FederatableContentContract,
  |                                ActivityBuilderContract, ActorProfileContract,
  |                                FederatedActorContract
  Enums/                        -- ActivityStatus, ActivityType, ActivityObjectType,
  |                                FollowerStatus, InteractionType, InteractionStatus
  Events/                       -- 7 event classes
  Handlers/
    HandlerRegistry.php         -- Registry for activity handlers (extension seam)
  Helpers/
    SsrfGuard.php               -- HTTPS/public-IP fetch guard
    DomainNormalizer.php        -- Normalized domain matching
    InboxLogger.php             -- Rejected-request audit log
    PostStats.php               -- Aggregate interaction/activity counts
    Http/
    Controllers/                -- Actor, Inbox, Outbox, Followers, Following,
    |                               WebFinger, NodeInfo, HostMeta, Featured, FederatedServersController
    |   Concerns/RespondsToAccept.php
    |   Fediverse/              -- Blade UI controllers (StatusController, FollowersController, ...)
    Middleware/
      VerifyHttpSignature.php   -- Incoming signature verification (Draft Cavage + RFC 9421)
    Signatures/
      SignatureParser.php       -- Parse Signature / Signature-Input headers
      SignatureVerifier.php     -- Build signing strings, verify digests/signatures
    Requests/                   -- InboxRequest, WebFingerRequest, ProfileUpdateRequest
    Resources/                  -- Actor, Activity, WebFinger resources, OrderedCollection
  Jobs/
    DeliverActivity.php         -- Queued outbound delivery with backoff + per-inbox records
    ProcessInboxActivity.php    -- Queued inbound activity processing
    FetchRemoteActor.php        -- Remote actor resolution
    PruneOldActivities.php      -- Retention cleanup job
    ForwardPostsToNewServerJob.php -- Forward posts to new follower's server
  Models/
    Actor.php                   -- Local actor with RSA key pair
    RemoteActor.php             -- Cached remote actor data
    Follower.php                -- Follower relationships
    Following.php               -- Outbound follow tracking
    Activity.php                -- Federated activity log
    ActivityDelivery.php        -- Per-inbox delivery attempts (reschedule/dead-letter)
    Interaction.php             -- Polymorphic interaction tracking
    Report.php                  -- Moderation reports (incoming Flag)
    InboxLog.php                -- Rejected inbox-request audit trail
    BlockedDomain.php           -- Domain blocklist
    BlockedRemoteActor.php      -- Actor blocklist
  Services/
    ActivityPubService.php      -- Main service (facade backed)
    ActivityDispatcher.php      -- Fan-out + inbox dispatch
    ActivityRecorder.php        -- Activity persistence + resolution failures
    HttpSignatureService.php    -- Outbound request signing
    DeliveryClient.php          -- Shared HTTP + signing delivery
    RemoteActorResolver.php     -- Fetch/upsert remote actors
    WebFingerService.php        -- Remote WebFinger resolution
    ActivityBuilder.php         -- Build ActivityStreams payloads
    ActivityInteractions.php    -- Static API for interaction queries
  Traits/
    FederatesContent.php        -- Model event hooks for auto-federation
    ResolvesLocalActor.php
  Console/Commands/             -- 3 artisan commands
```

## Testing

```bash
vendor/bin/pest
```

356 Pest tests (1029 assertions) across the `Feature` and `Unit` suites, covering actors, inbox processing, WebFinger, NodeInfo, console commands, content delivery, Mastodon spec compliance, HTTP signatures (including RFC 9421), blocking/moderation, allowlist and quotas, mentions, pagination, post forwarding, and unit-tested activity building. Both suites run by default.

## Roadmap

- [ ] Tailwind CSS build step (currently CDN)
- [ ] JSON-LD compaction for Pleroma/Akkoma compatibility
- [ ] Filament-based admin dashboard

## License

MIT
