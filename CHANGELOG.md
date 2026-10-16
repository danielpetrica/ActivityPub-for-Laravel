# Changelog

## [Unreleased]

### Added

- **Mentions**: `@user@host` mentions found in published content are resolved and delivered to each mention's inbox; mention `tag` objects and flat `to`/`cc` addressing are emitted on the wire.
- **Object-level `sensitive` and custom emoji tags (opt-in)**: the built object now carries `sensitive: true` when the content model (or actor) exposes `getActivityPubSensitive()` / `isActivityPubSensitive()` / a `sensitive` or `activitypub_sensitive` attribute, and custom emoji as ActivityStreams `Emoji` tags when it exposes `getActivityPubEmoji()` / `getActivityPubEmojis()` / an `activitypub_emoji` map. Both are absent by default, so existing models keep byte-identical wire output; malformed emoji entries are skipped, never thrown.
- **Outbound `Block` / `Undo(Block)`**: `ActivityPub::sendBlock()` builds and delivers a `Block` activity to the target inbox; `ActivityPub::sendUnblock($actor, $remoteActor)` records and delivers the matching `Undo(Block)`. Inbound `Block`/`Undo(Block)` handling is symmetric.
- **`Move` and `Update(Person)`**: `ActivityPub::sendMove($actor, $targetUrl, $alsoKnownAs)` emits a `Move` (delivered to followers and, when known, the target inbox); `ActivityPub::updateActorProfile($actor)` emits an `Update(Person)`. `alsoKnownAs` is exposed on the actor resource.
- **`Flag`/Report moderation**: inbound `Flag` activities create `Report` rows, surface on `/fediverse/status`, and fire the new `FlagReceived` event.
- **Inbox admission control**: domain allowlist and windowed per-actor/per-domain quotas enforced centrally, both failing open on infrastructure errors.
- **Per-inbox delivery tracking**: the new `activity_deliveries` table records every inbox attempt; the Status page can reschedule pending deliveries and shows dead-lettered ones.
- **Real collection pagination**: inbox/outbox/followers/following/featured return a top-level `OrderedCollection` whose items live on `OrderedCollectionPage` documents at `?page=N`, per the ActivityStreams spec.
- **Inbox audit log** (`inbox_logs`), always on, recording rejected inbox requests with a configurable retention window.
- **Retention/pruning**: `activitypub:prune-activities` honours `retention.*` for delivered/received activities, inbox logs and interactions, with per-run command options. The command is also registered on Laravel's scheduler to run **daily** (`withoutOverlapping()`), so retention is enforced automatically once `php artisan schedule:run` is wired to cron.
- **Real interaction dedup**: a unique index on `(type, object_type, object_id, actor_id, remote_actor_id)` now enforces the interaction dedup semantics at the database level, in addition to the application-level checks. The `interactions.type` column is tightened to 64 characters so the composite key fits within InnoDB's 3072-byte index limit.
- **Admin gate** (`fediverse.admin_gate`) for mutating Fediverse routes.
- **Queued inbox processing**: `ProcessInboxActivity` and the `queue.process_inbox` toggle; received activities return `202` immediately and process on a worker.
- **Extension seams**: configurable `actor_model` binding (also used for route binding), `HandlerRegistry` for custom activity handlers, swappable `ActivityBuilderContract`, `ActivityDispatcher`/`ActivityRecorder`, and `SignatureParser`/`SignatureVerifier`.
- New helpers: `SsrfGuard`, `DomainNormalizer`, `PostStats`, `InboxLogger`.
- New `ActivityType` cases `Flag` and `Move`; new `FlagReceived` event.
- CI workflow, `pint.json`, and a PHPStan baseline.

### Changed

- The public facade now documents **every** current public `ActivityPubService` method (`sendBlock`, `sendMove`, `approveFollower`, `rejectFollower`, `updateActorProfile`, …).
- Actor `inbox`/`outbox` are resolved to plain IRI strings.
- Collection endpoints return a top-level `OrderedCollection`; items moved to `OrderedCollectionPage` (`?page=N`).
- Outbound targeting resolves the real object author (`deliverToObjectAuthor`) instead of short-circuiting to a shared inbox.
- `sharedInbox` is a delivery optimization, not a resolution shortcut.
- Inbox processing is queued by default.
- The inbox audit log is always on and independent of `debug_display`.
- Third-party API calls use positional arguments; the package's own APIs keep their documented named-argument style.
- The default `vendor/bin/pest` run now executes the `Unit` suite in addition to `Feature`.
- **Retention defaults to 30 days for every window** (`delivered_activities_days`, `received_activities_days`, `inbox_logs_days`, `interactions_days`). Previously received activities and interactions defaulted to 90 days. Every window stays overridable per environment/config and per run.
- `fediverse.followers.approve` / `fediverse.followers.reject` now require the follower to be owned by the authenticated user's actor (or the caller to hold the configured admin ability).

### Fixed

- Delivery retries are tracked per inbox and can be rescheduled or dead-lettered instead of being retried blindly.
- The synchronous delivery path (`ActivityDispatcher::dispatchToFollowersSync`, used by `activitypub:deliver-content --debug`) now records per-inbox `activity_deliveries` rows and derives the aggregate activity status from them, matching the queued `DeliverActivity` path instead of marking the activity delivered on the first successful inbox. Transient 429/5xx sync attempts stay pending (reschedulable); terminal 4xx/3xx and refused inboxes fail.
- Activity-id based idempotency is now scoped per local actor and reserved *before* the handler runs, so concurrent redeliveries cannot double-process while the same activity is still handled once for each addressed actor. When the handler throws, the reservation is released before the exception propagates, so the queued retry can reprocess the activity instead of being silently dropped as a duplicate.
- `followers.approve`/`followers.reject` now reject a follower owned by another actor with an explicit `403` (previously a foreign follower was reported as `404`), closing an authorization gap on the Fediverse followers screen.
- A `Follow` to a locked (`manually_approves_followers`) account no longer forwards posts to the pending follower: forwarding happens only on `Accepted`, including when a pending follower is later approved.
- Block enforcement is normalized (casing, trailing dots, ports, `www.`, IPv4-mapped IPv6) and applied to every inbound activity type.
- Inbound `Block` now also removes any `Following` relationship we held for the blocking actor, not just a local `Follower`.
- `HostMetaController` derives the WebFinger template scheme and host from the same configured ActivityPub domain (previously the scheme came from `app.url`).
- The HTTP-signature replay slot is only consumed *after* successful verification, so a request rejected as "Unknown remote actor" (or any pre-verification failure) can be retried byte-identically within the TTL.
- Mention resolution is defensive: an unresolvable mention is skipped, never thrown.
- `Update(Person)` is only emitted on explicit profile changes and never on `last_active_at` bookkeeping writes.
- `ActorProfileContract::getProfileUrl()` documentation now matches the bundled behavior: the bundled `Actor` returns its ActivityPub actor IRI, which doubles as the content-negotiated HTML profile document (an HTML `Accept` header is served the profile page). Overriding it to return a separate HTML URL remains supported.

### Security

- RFC 9421 (`Signature-Input`/structured fields) support plus minimum signed-component enforcement.
- `SsrfGuard` enforces HTTPS-only, public-IP-only outbound fetches with a `security.allowed_hosts` escape hatch, and now pins the actual connection to a validated IP (`CURLOPT_RESOLVE`) so Guzzle cannot re-resolve the hostname after validation (DNS rebinding). The curl extension is now a hard requirement (`ext-curl`), so the pin is always active instead of silently degrading to the pre-flight resolution check only.
- Unknown signers are fetched off the verification request path (`http_signatures.queue_unknown_actors`).
- Central normalized block enforcement for all inbound activities.
- Inbox allowlist and quotas bound hostile instances.
- Always-on rejected-request audit log.
- Mutating Fediverse routes require the admin ability.

### Breaking

- `ActorProfileContract::getProfileUrl()` is now required.
- `ActivityBuilderContract` gained `undo()`, `block()`, `move()`, `undoBlock()`, `buildObject()`, `buildActivity()`, `updateActor()` and `attachMentions()`, and `undoFollow()` gained an optional `$followId`; custom implementers must add them.
- New `ActivityType` enum cases: `Flag`, `Move`.
- Actor `inbox`/`outbox` are now plain IRI strings.
- Collection endpoints now return a top-level `OrderedCollection`; items are only on `?page=N`.
- `sharedInbox` no longer short-circuits address resolution.
- Inbox processing is queued by default.
- Mutating Fediverse routes require the new admin ability.
- Stricter inbound HTTP-signature requirements (minimum signed components; unknown actors fetched off-path).
- `ActivityRecorder::markDelivered()` was removed. It had no in-tree callers; the per-inbox delivery primitives (`recordInboxDelivery()` / `syncAggregateStatus()`) are the supported way to advance activity status.
- `ext-curl` is now a required platform dependency (see `composer.json`).
- Retention windows now default to 30 days for received activities and interactions (previously 90). Set the `ACTIVITYPUB_RETENTION_*` env vars to keep longer histories.

---

### Earlier in this cycle

### Cache headers with config toggle
- `config/activitypub.php` now has `cache.enabled` and `cache.ttl` settings
- `RespondsToAccept` trait, WebFinger, NodeInfo, and HostMeta controllers set `Cache-Control` when enabled
- Disable via `ACTIVITYPUB_CACHE_ENABLED=false`

### Actor type configurable
- New `config('activitypub.actor_type')` key (default `'Person'`)
- `ActorResource` now reads type from config instead of hardcoding

### Queue payload optimization
- `DeliverActivity` now stores only `activityModelId` and `actorId` instead of full ActivityPub JSON + Actor model
- Queue storage per job reduced from ~5-50KB to ~8 bytes (two integers + URL string)
- **BREAKING:** If you dispatch `DeliverActivity` manually, use `activityModelId:` and `actorId:` named arguments

---

## [v0.2.0] - 2026-06-21

### Architecture

#### Strategy pattern for inbox processing
- **BREAKING:** `InboxProcessor` no longer has `handleFollow()`, `handleLike()`, etc. methods. The `match` block was replaced with 10 handler classes under `Actions/Handlers/`, each implementing `ActivityHandler`:
  - `HandleFollowAction`
  - `HandleLikeAction`
  - `HandleAnnounceAction`
  - `HandleUndoAction`
  - `HandleCreateAction`
  - `HandleDeleteAction`
  - `HandleUpdateAction`
  - `HandleBlockAction`
  - `HandleAcceptAction`
  - `HandleRejectAction`
- Handlers are tagged in the container as `activitypub.activity-handlers` and injected into `InboxProcessor`
- `InboxProcessor` reduced from 342 to ~80 lines

#### Events system (6 new classes)
- `ActivityDelivered` — fired on successful outbound delivery
- `ActivityDeliveryFailed` — fired on permanent delivery failure
- `FollowReceived` — fired when remote actor follows local actor
- `FollowRemoved` — fired when remote actor unfollows local actor
- `BlockReceived` — fired when remote actor blocks local actor
- `InboxActivityReceived` — fired for every incoming activity

#### ActivityBuilder injectable
- **BREAKING:** `ActivityBuilder` methods changed from `public static` to instance methods
- New `ActivityBuilderContract` interface with all 7 method signatures
- Consumers calling `ActivityBuilder::follow()` (static) must now inject `ActivityBuilderContract`:
  ```php
  // Before
  use DanielPetrica\LaravelActivityPub\Services\ActivityBuilder;
  $activity = ActivityBuilder::follow(actor: $actor, objectUrl: $url);
  // After
  use DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract;
  public function __construct(private ActivityBuilderContract $activityBuilder) {}
  $activity = $this->activityBuilder->follow(actor: $actor, objectUrl: $url);
  ```
- `ActivityPubService` and `InteractController` updated to use injected `ActivityBuilderContract`

#### ActorContract segregation
- `ActorContract` now extends `ActorProfileContract` (profile methods) and `FederatedActorContract` (federation methods)
- **BREAKING:** `ActorContract` has a new method: `getPrivateKeyPem(): ?string`
- Consumers implementing `ActorContract` must add this method

#### DeliveryClient extraction
- HTTP delivery + signing logic extracted from duplicated code in `ActivityPubService` and `DeliverActivity` into `src/Services/DeliveryClient.php`
- Used by both async (queue) and sync (debug mode) delivery paths

### Protocol

#### Object fetching
- `HandleCreateAction` now fetches remote object data when `payload.object` is a bare URL string (ActivityPub spec §3.1)
- Uses `RemoteActorResolver::fetchActorData()` (SSRF-safe)

#### Shared inbox optimization
- **Migration needed:** `2026_06_21_000004_add_shared_inbox_to_remote_actors.php` adds `shared_inbox_url` column to `remote_actors`
- `RemoteActorResolver::upsertFromData()` now extracts and stores `endpoints.sharedInbox` from remote actor profiles
- All 5 delivery points (`deliverToFollowers`, `deliverSync`, `deliverToObjectAuthor`, `sendFollow`, `sendUnfollow`) now prefer `shared_inbox_url` over personal `inbox_url`
- Follower delivery grouped by unique inbox URL — one HTTP request per remote instance instead of one per follower

#### Block/Reject activity handling
- `Block` activities now remove follower records and fire `BlockReceived` event
- `Reject` activities now remove follower and following records
- `Accept` activities now update `Following` status to `Accepted`

#### Following model/table
- **Migration needed:** `2026_06_21_000003_create_following_table.php` creates the `following` table
- `FollowingController` API now returns real paginated data instead of an empty collection
- Fediverse UI `following.blade.php` updated to use `Following` model

#### ActorResource compliance
- Added `url`, `manuallyApprovesFollowers`, `endpoints.sharedInbox`, `type: Key` on publicKey
- `@context` now includes both `security/v1` and `security/v2`
- **BREAKING:** Actor JSON now includes these additional fields

#### OrderedCollection always includes first/last
- Single-page collections now always include `first` and `last` pointers (ActivityPub spec §5)

#### Accept header negotiation
- New `Concerns/RespondsToAccept` trait with `wantsJson()` and `activityPubResponse()` methods
- 6 controllers (Actor, Inbox GET, Outbox, Followers, Following, Featured) now check Accept header
- Browsers (Accept: text/html) receive 406 instead of raw JSON
- All ActivityPub responses include `Vary: Accept` header

#### WebFinger Content-Type
- Changed from `application/json` to `application/jrd+json` (RFC 7033)

#### NodeInfo configurable
- `version` and `openRegistrations` now read from config with env overrides

#### HostMeta scheme
- Uses actual scheme from `config('app.url')` instead of hardcoded `https://`

#### Activity IDs use UUID
- `ActivityBuilder` replaced `time()` with `Str::uuid()` for activity IDs, preventing collisions under concurrent creation

#### Announce/Undo/CreateNote protocol fixes
- `announce()` adds followers collection URL to `cc`
- `createNote()` accepts `$cc` parameter, adds `url` to Note object
- `undoFollow()` includes original Follow activity `id` in wrapped object

### Security

#### SSRF protection
- `WebFingerService` and `RemoteActorResolver` block requests to private/reserved IP ranges (127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, ::1)
- DNS resolution before HTTP fetch to validate target isn't internal

#### Signature replay protection
- `VerifyHttpSignature` caches verified signatures with 120s TTL via `Cache::add()`

#### Digest enforcement
- Digest header required for all non-empty POST bodies to inbox
- Rejects unknown or weak digest algorithms

#### keyId validation
- `keyId` from Signature header validated for `https://` scheme
- Rejects URLs with user-info components or non-https schemes

#### Host header injection fix
- Singing string now uses canonical host from `config('activitypub.domain')` instead of `$request->getHost()`

#### Idempotency
- Inbox POST has cache-based idempotency keyed on `(keyId, signature, date)`

#### Content-Length limits
- Inbox endpoints reject payloads > 1MB with HTTP 413

#### Rate limiting per-IP
- Inbox rate limiter changed from global 60/min to per-IP 60/min
- Fediverse interaction routes throttled at 30/min

### Data layer

#### ActivityStatus enum
- **BREAKING:** `Activity::status` changed from plain string to `ActivityStatus` backed enum
- Values: `Pending`, `Delivered`, `Received`, `Failed`
- Comparisons against `$activity->status` now return `ActivityStatus` instances:
  ```php
  // Before
  if ($activity->status === 'pending') { ... }
  // After
  if ($activity->status === ActivityStatus::Pending) { ... }
  // Or compare by value
  if ($activity->status->value === 'pending') { ... }
  ```

#### Form Requests (validation extracted)
- **BREAKING:** Validation failure status codes changed from 400 to 422 for:
  - Inbox POST (`InboxRequest`): missing/invalid `type`, `actor`, `object`, `@context`
  - WebFinger GET (`WebFingerRequest`): missing `resource`, invalid `acct:` URI
  - Profile update (`ProfileUpdateRequest`): invalid `name`, `summary`, `icon_url`, `image_url`
- Clients expecting 400 for these errors must now expect 422

#### Performance indexes
- **Migration needed:** `2026_06_21_000002_add_performance_indexes.php` adds:
  - Composite `(actor_id, is_incoming, created_at)` on activities
  - Composite `(remote_actor_id, is_incoming, type, created_at)` on activities (for feed queries)
  - Composite `(remote_actor_id, actor_id, status)` on followers

#### Federation indexes
- **Migration needed:** `2026_06_21_000001_add_federation_indexes.php` adds:
  - Index on `remote_actors.domain`
  - Composite `(actor_id, status)` on followers
  - Composite `(status, created_at)` on activities

#### N+1 query fixes
- Fediverse inbox/outbox controllers now eager-load `actor` relation alongside `remoteActor`
- Timeline uses subquery instead of `pluck` + `whereIn`

### Performance

#### Chunked follower delivery
- `deliverToFollowers()` and `deliverSync()` use `->chunk(200)` instead of `->get()`
- Prevents OOM for actors with 10K+ followers (was ~500MB peak memory, now ~8MB)

#### Memoized actor resolution
- `ActivityPubService::resolveLocalActor()` memoizes per-request (avoiding redundant `actors` table queries)
- `RemoteActorResolver::resolveFromPayload()` memoizes per-request (avoiding redundant `remote_actors` queries)
- `InboxProcessor::resolveLocalActor()` now does single `whereIn()` query instead of up to 5 sequential queries

#### Pagination optimization
- Inbox/outbox API controllers skip the data query when `page > 1` (only execute COUNT)
- Followers endpoint now paginated (was loading all followers into memory)
- Page parameter bounded to prevent absurd OFFSET values

### Configuration

#### New env vars
- `ACTIVITYPUB_CACHE_ENABLED` (default `true`) — toggle cache headers
- `ACTIVITYPUB_FEDIVERSE_ENABLED` (default `true`) — toggle web UI
- **BREAKING:** Previous env var name `ACTIVITYPUB_FEDERIVERSE_ENABLED` (typo) is no longer read. Use `ACTIVITYPUB_FEDIVERSE_ENABLED` instead.

#### New config keys
```
'version' => '1.0.0'
'open_registrations' => false
'actor_type' => 'Person'
'cache' => ['enabled' => true, 'ttl' => 86400]
```

### ActivityBuilder protocol fixes
- `createNote()` now accepts `$cc` parameter; adds `url` property to Note object
- `announce()` includes `$actor->getFollowersUrl()` in `cc` field
- `undoFollow()` includes original Follow activity `id` in the nested object

### Delivery job resilience
- `DeliverActivity` backoff: [30s, 120s, 600s] instead of fixed 60s
- `failed()` method marks activity status as `failed` and logs warning
- `PruneOldActivities` uses `chunkById(1000)` for safe bulk deletion

### Rate limiting
- Inbox: per-IP, 60 requests/min (was global)
- Fediverse POST routes: 30 requests/min (new)
- `fediverse-interact` rate limiter registered in service provider

### Sync delivery type hints
- `sendCreateForActorSync()` returns typed array `array{record: Activity, results: list<array{actor_url, inbox_url, response_code}>}`

### Config publish
- Run `php artisan vendor:publish --tag=activitypub-config` to get the updated config with new keys
- Run `php artisan vendor:publish --tag=activitypub-migrations` to get the new migration files

---

## [v0.1.0] - 2026-06-21

### Initial release
- ActivityPub actor profiles (Person type)
- WebFinger discovery (`/.well-known/webfinger`)
- Inbox/Outbox as OrderedCollection with pagination
- HTTP Signatures (RSA-SHA256) for incoming verification and outbound signing
- Outbound federation: Create, Update, Delete, Follow, Accept, Like, Announce, Undo, replies
- Follower management (track followers from remote servers)
- Shared inbox (`POST /inbox` for instance-level delivery)
- NodeInfo 2.0 (`/.well-known/nodeinfo` and `/nodeinfo/2.0`)
- host-meta (`/.well-known/host-meta`)
- Blade-based Fediverse web UI (dashboard, timeline, inbox, discover, profile editing)
- Artisan commands: `activitypub:create-actor`, `activitypub:prune-activities`
- Queue jobs: async delivery with retries, remote actor fetching with deduplication
