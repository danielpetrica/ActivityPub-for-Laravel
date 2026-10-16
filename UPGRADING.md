# Upgrade Guide

This document describes the breaking changes, new configuration, schema changes
and behaviour changes you need to know about when upgrading
`danielpetrica/activitypub-for-laravel`.

This guide is self-contained: you can upgrade by following it without reading
[`CHANGELOG.md`](CHANGELOG.md) first. Where a change has a migration, a
verification step, or a rollback caveat, it is called out explicitly.

## v0.15.0 (Unreleased)

`v0.15.0` is a large hardening and federation-correctness release on top of the
`v0.14.x` line. It adds a required platform dependency (`ext-curl`), a batch of
new migrations, an always-on inbox audit log, inbox admission control, queued
inbox handling by default, an admin ability for mutating Fediverse routes, and
a number of strict-but-correct wire-format changes (collections, actor IRIs,
HTTP signatures).

### Overview / TL;DR

The upgrade is a standard library bump plus `composer require` / `composer
update`, but **three things will bite you if you skip them**:

1. `ext-curl` is now a hard requirement (installation fails without it).
2. The inbox is processed on the queue by default — you need a worker running,
   or inbound federation stops being handled (the request still returns `202`).
3. Mutating Fediverse routes now require an **admin** ability that must be
   defined, otherwise those routes deny with `403`.

**Who is affected.** This package implements ActivityPub **server-to-server
(S2S)** federation. Everything below concerns S2S. **Client-to-server (C2S) and
OAuth remain out of scope** for this package and are not covered by this guide.

#### Upgrade checklist

```text
[ ] Back up the database and APP_KEY.
[ ] composer require "danielpetrica/activitypub-for-laravel:^0.15"
    (or: composer update danielpetrica/activitypub-for-laravel)
[ ] Confirm ext-curl is loaded:  php -m | grep -i curl
[ ] Run a queue worker (inbox processing is queued by default).
[ ] Ensure `php artisan schedule:run` is wired to cron (daily prune).
[ ] Publish the config if you have not customised activitypub.php:
        php artisan vendor:publish --tag=activitypub-config
    otherwise merge the new keys manually.
[ ] Publish and run migrations:
        php artisan vendor:publish --tag=activitypub-migrations
        php artisan migrate
[ ] Define the admin gate:  Gate::define('activitypub.fediverse.admin', ...)
[ ] php artisan config:clear && php artisan route:clear
[ ] Review the Breaking changes section below for code-level changes.
```

### Requirements

| Requirement | Version / value | Notes |
| --- | --- | --- |
| PHP | `^8.5` | unchanged |
| `laravel/framework` | `^13.0` | unchanged |
| `ext-openssl` | any | unchanged (HTTP signatures) |
| `ext-json` | any | unchanged |
| `ext-curl` | any | **new in v0.15.0** |

`ext-curl` is now declared in `composer.json` (`"ext-curl": "*"`) and is what
backs:

- `SsrfGuard`'s `CURLOPT_RESOLVE` connection pinning — it pins the actual
  outbound connection to an already-validated IP so Guzzle cannot re-resolve
  the hostname after validation (defence against DNS rebinding between the
  pre-flight check and the real request); and
- the inbox delivery transport.

Because curl is now required rather than optional, the pin is always active
instead of silently degrading to the pre-flight resolution check only.

Verify the extension is present before upgrading:

```bash
php -m | grep -i curl
```

If that prints nothing, install the extension for your PHP build (for example
`php8.5-curl`) before running `composer require` — Composer will refuse the
install otherwise.

### Upgrade steps

1. **Back up the database and your `APP_KEY`.** The migrations in this release
   alter existing columns (`interactions.type`, `inbox_logs.actor_id`,
   `activities.activity_uri`) and add unique indexes, so take a dump and keep a
   copy of `APP_KEY`. The actor private key (`actors.private_key_pem`) is stored
   with an `encrypted` cast and is unrecoverable without the original
   `APP_KEY`.

2. **Require the new version.**

   ```bash
   composer require "danielpetrica/activitypub-for-laravel:^0.15"
   # or, if you track it loosely:
   composer update danielpetrica/activitypub-for-laravel
   ```

   Composer will fail fast if `ext-curl` is missing.

3. **Publish the config.**

   ```bash
   php artisan vendor:publish --tag=activitypub-config
   ```

   If you already have a published and customised `config/activitypub.php`,
   **do not** blindly re-run this with `--force`: it overwrites your file.
   Instead, re-publish to a scratch location or diff against
   `vendor/danielpetrica/activitypub-for-laravel/config/activitypub.php` and
   merge the new keys from the **New configuration reference** section manually.
   The new blocks are `security`, `inbox`, `inbox_log`, and `retention`, plus
   the `http_signatures.queue_unknown_actors`, `queue.process_inbox` and
   `fediverse.admin_gate` keys.

4. **Publish and run the migrations.**

   ```bash
   php artisan vendor:publish --tag=activitypub-migrations
   php artisan migrate
   ```

   Seven new migrations are included (see **New migrations**). They also apply
   automatically when the package is loaded as a dependency, but publishing
   keeps them under your version control.

5. **Define the admin gate.** Mutating Fediverse routes (blocking domains and
   actors, pruning, rescheduling deliveries, refreshing followed accounts,
   forwarding posts) now deny by default until the configured ability exists:

   ```php
   // e.g. in a service provider
   Gate::define('activitypub.fediverse.admin', fn ($user) => $user->isAdmin());
   ```

   Failing to define it (and not overriding `fediverse.admin_gate`) means those
   routes return `403`.

6. **Run a queue worker.** Inbox processing is now queued by default
   (`queue.process_inbox`), and the HTTP request returns `202` immediately.
   Without a worker, received activities are never handled:

   ```bash
   php artisan queue:work
   ```

   Make sure your worker's supervisor/tooling is in place in production. The
   delivery jobs (`DeliverActivity`) and `FetchRemoteActor` also run on the
   same queue connection.

7. **Ensure the scheduler runs.** `activitypub:prune-activities` is now
   registered on Laravel's scheduler to run **daily** (`withoutOverlapping()`).
   Retention is only enforced once the scheduler is actually driven:

   ```cron
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```

8. **Clear compiled config and routes.** Config and route caches are not
   invalidated automatically by a library upgrade:

   ```bash
   php artisan config:clear
   php artisan route:clear
   # or, if you cache in production: config:cache && route:cache
   ```

### Breaking changes

Summary, followed by per-item detail.

| Change | Impact | Action |
| --- | --- | --- |
| `ActorProfileContract::getProfileUrl()` now required | Custom contract implementers break | Add the method |
| `ActivityBuilderContract` gained methods | Custom implementers break | Implement `undo()`, `block()`, `move()`, `undoBlock()`, `buildObject()`, `buildActivity()`, `updateActor()`, `attachMentions()`, and the new `undoFollow()` parameter |
| New `ActivityType` cases `Flag`, `Move` | Exhaustive `match` over the enum throws | Handle the new cases or add a default arm |
| Actor `inbox`/`outbox` are plain IRI strings | Consumers of the embedded object shape break | Read the string |
| Collection endpoints return a top-level `OrderedCollection` | Clients reading `orderedItems` from the root break | Follow `first` / `?page=N` |
| `sharedInbox` no longer short-circuits address resolution | Delivery targets change (more correct) | Re-check delivery expectations |
| Inbox processing is queued by default | Handlers no longer run inline; request returns `202` | Run a worker, or opt out with `ACTIVITYPUB_QUEUE_PROCESS_INBOX=false` |
| Mutating Fediverse routes require the admin ability | Those routes `403` until the gate exists | Define `activitypub.fediverse.admin` |
| Stricter inbound HTTP-signature requirements | Unknown-actor requests get one `401` then succeed | Ensure remote servers retry; tune `http_signatures.queue_unknown_actors` |
| `ActivityRecorder::markDelivered()` removed | Calls to it fatal | Use `recordInboxDelivery()` / `syncAggregateStatus()` |
| `ext-curl` required | Install fails without it | Install the extension |
| Retention windows default to 30 days | Data pruned sooner than before | Set `ACTIVITYPUB_RETENTION_*` to keep longer histories |

#### `ActorProfileContract::getProfileUrl()` is now required

**What changed.** `DanielPetrica\LaravelActivityPub\Contracts\ActorProfileContract`
gained a required `getProfileUrl(): string` method. It returns the public,
human-readable HTML profile URL for the actor (for example
`https://example.com/@alice`) and is used in place of the ActivityPub actor ID
where a profile link is displayed.

**Who is affected.** Any class that implements the contract directly.

**Action required.** Add the method. Classes that only *consume* the contract
(type-hint it) need no changes.

```php
use DanielPetrica\LaravelActivityPub\Contracts\ActorProfileContract;

final class Profile implements ActorProfileContract
{
    // ...existing methods...

    public function getProfileUrl(): string
    {
        return url("/@{$this->username}");
    }
}
```

The bundled `Actor` returns its ActivityPub actor IRI, which doubles as the
content-negotiated HTML profile document (an HTML `Accept` header is served the
profile page). Overriding the method to return a separate HTML URL is still
supported.

#### `ActivityBuilderContract` gained methods

**What changed.** The contract gained eight methods and one changed signature:

- `undo(ActorContract $actor, array $activity): array`
- `block(ActorContract $actor, string $objectUrl): array`
- `move(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): array`
- `undoBlock(ActorContract $actor, string $objectUrl): array`
- `buildObject(FederatableContentContract $content, array $mentions = []): array`
- `buildActivity(string $type, ActorContract $actor, array $object): array`
- `updateActor(ActorContract $actor, array $object): array`
- `attachMentions(array $activity, array $mentions): array`
- `undoFollow()` now accepts an optional third parameter:
  `undoFollow(ActorContract $actor, string $objectUrl, ?string $followId = null): array`

**Who is affected.** Classes that implement the contract directly (rather than
extending the bundled `ActivityBuilder`), or that swap the builder through the
container.

**Action required.** Implement the new methods and update `undoFollow()`.
Verify any container-bound replacement still satisfies the expanded interface.

#### New `ActivityType` enum cases: `Flag` and `Move`

**What changed.** `DanielPetrica\LaravelActivityPub\Enums\ActivityType` gained
the `Flag` and `Move` cases.

**Who is affected.** Code that switches **exhaustively** over `ActivityType`.

**Action required.** Handle the new cases, or add a default arm. A `match`
without a default that does not cover `Flag`/`Move` will now throw
`UnhandledMatchError` at runtime.

```php
// Before: match ($type) { ActivityType::Create => ..., ... }  // now throws on Flag/Move
// After:
match ($type) {
    ActivityType::Create => '...',
    ActivityType::Flag   => '...',
    ActivityType::Move   => '...',
    default              => '...',
};
```

#### Actor `inbox`/`outbox` are plain IRI strings

**What changed.** The actor resource (and `ActorResource`) now emits `inbox`
and `outbox` as plain IRI strings rather than embedded objects.

**Who is affected.** Any consumer or test that reads the embedded object shape
(for example `$actor['inbox']['id']`).

**Action required.** Read the string value instead.

```jsonc
// Before
"inbox": { "id": "https://example.com/users/alice/inbox", "type": "OrderedCollection" }
// After
"inbox": "https://example.com/users/alice/inbox"
```

#### Collection endpoints return a top-level `OrderedCollection`

**What changed.** Inbox, outbox, followers, following and featured endpoints now
return a top-level `OrderedCollection` carrying metadata only (`totalItems`,
`first`, `last`). The actual items live on `OrderedCollectionPage` documents
reachable through `?page=N`, per the ActivityStreams spec.

**Who is affected.** Clients that read `orderedItems` from the collection root.

**Action required.** Follow `first` (or request `?page=1`) to get the page
document that carries `orderedItems`.

#### `sharedInbox` no longer short-circuits address resolution

**What changed.** When delivering to an actor, the package now resolves the
real object author (`deliverToObjectAuthor`) and delivers to that actor's own
inbox. A shared inbox is still preferred for follower fan-out, but it is no
longer used as a substitute for resolving the addressed actor.

**Who is affected.** Anyone who relied on delivery short-circuiting to a shared
inbox. Deliveries now go to the correct per-actor inbox.

**Action required.** None for correctness — but re-check any tests that assert
on a shared-inbox URL in delivery output.

#### Inbox processing is queued by default

**What changed.** Received inbox activities are dispatched to the queue
(`ProcessInboxActivity`) by default, and the HTTP request returns `202`
immediately.

**Who is affected.** Everyone. Without a running worker, inbound activities are
accepted (`202`) but never processed.

**Action required.** Run a queue worker. Set
`ACTIVITYPUB_QUEUE_PROCESS_INBOX=false` (config key `queue.process_inbox`) only
when you explicitly need the handler outcome inline, such as in local
development or tests.

#### Mutating Fediverse routes require the admin ability

**What changed.** Mutating Fediverse routes (blocking domains/actors, pruning,
rescheduling deliveries, refreshing followed accounts, forwarding posts) now
require an admin authorization in addition to the dashboard gate. By default
the package checks the `activitypub.fediverse.admin` gate.

**Who is affected.** Anyone using the Fediverse dashboard in production.

**Action required.** Define the ability before upgrading:

```php
Gate::define('activitypub.fediverse.admin', fn ($user) => $user->isAdmin());
```

Configure a different ability with `ACTIVITYPUB_FEDIVERSE_ADMIN_GATE` (config
key `fediverse.admin_gate`). When the configured ability is **undefined the
mutating routes deny by default (`403`)** — the check is fail-closed.

#### Stricter inbound HTTP-signature requirements

**What changed.** Inbound requests must satisfy stricter verification:

- a minimum set of signed components is required;
- RFC 9421 (`Signature-Input` / structured fields) requests are supported in
  addition to the legacy `Signature` header; and
- actors that are not yet cached are fetched **off** the verification request
  path (`http_signatures.queue_unknown_actors`).

**Who is affected.** Instances federating with actors the app has never seen.

**Action required.** None if remote servers retry. A request signed by an
unknown actor is rejected with `401`, a `FetchRemoteActor` job is queued, and
the request succeeds once the actor is stored and the remote retries. The replay
slot is only consumed *after* successful verification, so the retry can be
byte-identical within the TTL. If you want the old synchronous fetch behaviour,
set `http_signatures.queue_unknown_actors` to `false`.

#### `ActivityRecorder::markDelivered()` removed

**What changed.**
`DanielPetrica\LaravelActivityPub\Services\ActivityRecorder::markDelivered()`
has been removed. It was retained for backward compatibility but had no
in-tree callers.

**Action required.** Advance activity delivery state through
`recordInboxDelivery()` (per inbox) or `syncAggregateStatus()` (recompute the
aggregate from the per-inbox rows) instead.

#### `ext-curl` is now a required platform dependency

See **Requirements**. Install the extension before upgrading, or Composer will
refuse the install.

#### Retention windows default to 30 days

**What changed.** Every `activitypub.retention.*` window now defaults to **30
days** — `delivered_activities_days`, `received_activities_days`,
`inbox_logs_days` and `interactions_days`. Received activities and interactions
previously defaulted to 90. `activitypub:prune-activities` is also registered
on the scheduler to run daily.

**Action required.** If you relied on the old 90-day defaults, set the
environment variables explicitly:

```env
ACTIVITYPUB_RETENTION_RECEIVED_DAYS=90
ACTIVITYPUB_RETENTION_INTERACTIONS_DAYS=90
```

Note that incoming activities are used to deduplicate redelivered activities,
so a shorter window means an old activity that is redelivered after pruning may
be processed again.

### Notable behavior changes (non-breaking)

These do not change a public signature, but they change runtime behaviour and
are worth knowing before you upgrade.

- **Inbox audit log is always on.** Rejected inbox requests (missing/invalid
  signatures, validation failures) are recorded in `inbox_logs`, independent of
  `debug_display`. Disable with `inbox_log.enabled` if the write cost is too
  high for your traffic profile. Logging failures never affect the HTTP
  response.
- **Follower approve/reject is ownership-scoped.** `fediverse.followers.approve`
  and `fediverse.followers.reject` act only on followers owned by the
  authenticated user's actor. Acting on a foreign follower returns `403` unless
  the caller holds the admin ability. (Previously a foreign follower was
  reported as `404`, which closed an authorization gap.)
- **Auto-forward only on `Accepted`.** A `Follow` to a locked
  (`manually_approves_followers`) account no longer forwards posts to the
  pending follower. Forwarding happens only on `Accepted`, including when a
  pending follower is later approved.
- **Normalized block enforcement.** Block matching is normalized (casing,
  trailing dots, ports, `www.`, IPv4-mapped IPv6) and applied centrally to
  **every** inbound activity type. Inbound `Block` also removes any `Following`
  relationship the instance held for the blocking actor, not just a local
  `Follower`.
- **Per-inbox delivery tracking + aggregate semantics.** The new
  `activity_deliveries` table records every inbox attempt. Delivery retries are
  tracked per inbox and can be rescheduled or dead-lettered. Aggregate activity
  status is derived from the per-inbox rows.
- **Sync delivery parity.** The synchronous path
  (`ActivityDispatcher::dispatchToFollowersSync`, used by
  `activitypub:deliver-content --debug`) now records per-inbox
  `activity_deliveries` rows and derives the aggregate status the same way the
  queued `DeliverActivity` path does, instead of marking the activity delivered
  on the first successful inbox. Transient `429`/`5xx` sync attempts stay
  pending (reschedulable); terminal `4xx`/`3xx` and refused inboxes fail.
- **Replay slot only consumed after successful verification.** A request
  rejected as "Unknown remote actor" (or any pre-verification failure) can be
  retried byte-identically within the TTL.
- **Activity-id idempotency is actor-scoped.** The reservation is claimed
  before the handler runs, so concurrent redeliveries cannot double-process,
  while the same activity is still handled once per addressed actor. When the
  handler throws, the reservation is released before the exception propagates
  so the queued retry can reprocess it.
- **Mentions.** `@user@host` mentions in published content are resolved and
  delivered to each mention's inbox; mention `tag` objects and flat `to`/`cc`
  addressing are emitted on the wire. Resolution is defensive: an unresolvable
  mention is skipped, never thrown.
- **Opt-in `sensitive` and custom emoji.** The built object carries
  `sensitive: true` when the content model (or actor) exposes
  `getActivityPubSensitive()` / `isActivityPubSensitive()` / a `sensitive` or
  `activitypub_sensitive` attribute, and custom emoji as ActivityStreams
  `Emoji` tags when it exposes `getActivityPubEmoji()` /
  `getActivityPubEmojis()` / an `activitypub_emoji` map. Both are absent by
  default, so existing models keep byte-identical wire output; malformed emoji
  entries are skipped, never thrown.
- **NodeInfo / WebFinger / HostMeta fixes.** `HostMetaController` now derives
  the WebFinger template scheme and host from the same configured ActivityPub
  domain (previously the scheme came from `app.url`).
- **New activity support.** Outbound `Block`/`Undo(Block)`
  (`ActivityPub::sendBlock()` / `sendUnblock()`), `Move` and `Update(Person)`
  (`ActivityPub::sendMove()` / `updateActorProfile()`, with `alsoKnownAs` on the
  actor resource), and inbound `Flag` moderation (creates `Report` rows,
  surfaces on `/fediverse/status`, fires the `FlagReceived` event). Inbound
  `Block`/`Undo(Block)` handling is symmetric. `Update(Person)` is only emitted
  on explicit profile changes, never on `last_active_at` bookkeeping writes.
- **Interaction dedup at the database level.** A unique index on
  `(type, object_type, object_id, actor_id, remote_actor_id)` enforces the
  interaction dedup semantics in addition to the application-level checks. The
  `interactions.type` column is tightened to 64 characters so the composite key
  fits within InnoDB's 3072-byte index limit.
- **Third-party calls use positional arguments.** The package's own APIs keep
  their documented named-argument style. Calls into third-party APIs (Laravel,
  Eloquent, Carbon, OpenSSL, `Str`, `Http`, `Route`, …) were converted back to
  positional arguments to remove coupling to dependency parameter names. This
  changes no public signature and is not breaking; it is listed for
  completeness.
- **Test suite scope.** The default `vendor/bin/pest` run now executes the
  `Unit` suite in addition to `Feature`.

### New configuration reference

Every new or behaviourally changed key. "env" is the exact environment variable
name from `config/activitypub.php`; "—" means the key has no environment
variable and must be set in the published config file.

| Config key | env | Default | Purpose |
| --- | --- | --- | --- |
| `http_signatures.queue_unknown_actors` | — | `true` | Fetch actors missing from the cache off the verification request path (queue `FetchRemoteActor`, reject with `401`, retry after stored) instead of a synchronous outbound fetch. |
| `security.allowed_hosts` | — | `[]` | Hosts exempt from DNS resolution checks (private IP literals are still rejected). Intended for trusted internal test targets only. |
| `security.max_response_bytes` | `ACTIVITYPUB_MAX_RESPONSE_BYTES` | `2000000` | Maximum accepted response body size (bytes) for remote fetches. |
| `inbox.allowlist.enabled` | `ACTIVITYPUB_INBOX_ALLOWLIST_ENABLED` | `false` | When true, inbound activities from unlisted sender domains are dropped (logged; HTTP `202` already returned). |
| `inbox.allowlist.domains` | — | `[]` | Allowed sender domains, matched through `DomainNormalizer` (casing, trailing dots, ports, `www.` normalized). Enabled with an empty list is treated as "not configured" and allows traffic. |
| `inbox.quotas.enabled` | `ACTIVITYPUB_INBOX_QUOTAS_ENABLED` | `true` | Master switch for windowed per-actor/per-domain inbox caps. Fails open on infrastructure errors. |
| `inbox.quotas.per_actor_per_minute` | `ACTIVITYPUB_INBOX_PER_ACTOR_PER_MINUTE` | `120` | Per-actor per-minute inbox cap. `0` disables the window. |
| `inbox.quotas.per_domain_per_minute` | `ACTIVITYPUB_INBOX_PER_DOMAIN_PER_MINUTE` | `600` | Per-domain per-minute inbox cap. `0` disables the window. |
| `inbox.quotas.per_actor_per_day` | `ACTIVITYPUB_INBOX_PER_ACTOR_PER_DAY` | `5000` | Per-actor per-day inbox cap. `0` disables the window. |
| `inbox.quotas.interactions_per_actor_per_day` | `ACTIVITYPUB_INBOX_INTERACTIONS_PER_ACTOR_PER_DAY` | `1000` | Additionally caps Like/Announce interactions per actor per day. `0` disables the window. |
| `queue.process_inbox` | `ACTIVITYPUB_QUEUE_PROCESS_INBOX` | `true` | Handle received inbox activities on the queue (`ProcessInboxActivity`) and return `202` immediately. Set false only for local development / inline handler outcome. |
| `fediverse.admin_gate` | `ACTIVITYPUB_FEDIVERSE_ADMIN_GATE` | `activitypub.fediverse.admin` | Named gate required by mutating Fediverse routes. Fail-closed: undefined ability denies (`403`). |
| `inbox_log.enabled` | `ACTIVITYPUB_INBOX_LOG_ENABLED` | `true` | Always-on rejected-request audit log written to `inbox_logs`. |
| `retention.delivered_activities_days` | `ACTIVITYPUB_RETENTION_DELIVERED_DAYS` | `30` | Retention for delivered activities. |
| `retention.received_activities_days` | `ACTIVITYPUB_RETENTION_RECEIVED_DAYS` | `30` | Retention for received activities (was 90). |
| `retention.inbox_logs_days` | `ACTIVITYPUB_RETENTION_INBOX_LOGS_DAYS` | `30` | Retention for inbox audit-log rows. |
| `retention.interactions_days` | `ACTIVITYPUB_RETENTION_INTERACTIONS_DAYS` | `30` | Retention for interactions (was 90). |
| `actor_model` | — | `Actor::class` | Now actually honoured for container resolution **and** route binding (`{actor:username}`), so swapping it changes both DI and route model binding. |

The retention windows are also overridable per run with the matching command
options — see `php artisan activitypub:prune-activities --help`.

### New migrations

Seven migrations are added in this release (published under the
`activitypub-migrations` tag, and loaded automatically when the package is
installed):

| Migration | What it does |
| --- | --- |
| `2026_10_15_000001_create_activity_deliveries_table.php` | Creates `activity_deliveries` (per-inbox delivery attempts): `activity_id`, `inbox_url`, `status`, `attempts`, `response_code`, `last_error`, `delivered_at`; unique on `(activity_id, inbox_url)`, index on `status`. |
| `2026_10_15_000002_add_activity_uri_to_activities_table.php` | Adds nullable, initially-unique `activities.activity_uri` (the ActivityStreams `id` of an incoming activity) so redeliveries can be detected and ignored. |
| `2026_10_15_000003_make_inbox_logs_actor_id_nullable.php` | Makes `inbox_logs.actor_id` nullable, because rejected requests are not always addressable to a local actor (shared inbox, malformed payload). |
| `2026_10_15_000004_create_reports_table.php` | Creates `reports` (inbound `Flag` moderation): local `actor_id`, `remote_actor_id`, reported `object_type`/`object_id`, `reason`, `status` (default `open`), `activity_uri`, `payload`; indexes on `(status, created_at)` and `object_id`. |
| `2026_10_15_000005_add_identity_columns_to_actors_tables.php` | Adds `actors.also_known_as` (JSON) and `actors.moved_to`, plus `remote_actors.moved_to` and `remote_actors.also_known_as`. |
| `2026_10_15_000006_create_incoming_activities_table.php` | Creates `incoming_activities` (actor-scoped idempotency reservation: unique `(actor_id, activity_uri)`), and re-scopes `activities.activity_uri` uniqueness to `(actor_id, activity_uri)`. |
| `2026_10_16_000001_add_unique_interaction_dedup_index.php` | Tightens `interactions.type` to 64 chars and adds the composite unique index `interactions_type_object_actor_remote_unique` on `(type, object_type, object_id, actor_id, remote_actor_id)`. |

### Post-upgrade verification checklist

Run through these after deploying. Each maps to a change above.

- [ ] **Migrations applied cleanly.**

  ```bash
  php artisan migrate:status
  ```

  All `2026_10_15_*` and `2026_10_16_*` migrations should show `Ran`.

- [ ] **Scheduler is registered** (prune should appear as daily):

  ```bash
  php artisan schedule:list
  ```

  You should see `activitypub:prune-activities` scheduled daily.

- [ ] **Follow round-trip works.** From a remote instance, follow a local actor,
  then have the local actor approve it, and confirm the `Accept` is delivered.
  Also confirm a new follower is not forwarded posts until `Accepted`.

- [ ] **Inbox accepts then processes on the worker.** POST a correctly signed
  activity to the local inbox: the HTTP response is `202`. Then confirm the
  worker picked up `ProcessInboxActivity` (queue dashboard / logs) and that the
  activity was actually handled.

- [ ] **Mutating Fediverse route is fail-closed.** With the admin ability
  **not** defined, hitting a mutating route (e.g. blocking a domain or pruning)
  returns `403`; after defining `activitypub.fediverse.admin` it succeeds for an
  admin and `403`s for a non-admin.

- [ ] **Collections paginate.** `GET` an actor's outbox returns a top-level
  `OrderedCollection` with `totalItems`/`first`; `?page=1` returns the
  `OrderedCollectionPage` with the items.

- [ ] **If you are developing the package**, run the test suite:

  ```bash
  vendor/bin/pest
  ```

### Rollback notes

The migrations have `down()` methods, but a few rollbacks have caveats. Always
take a database backup before rolling back.

- **`inbox_logs.actor_id` (2026_10_15_000003).** Rolling back re-imposes
  `NOT NULL`. If the audit log has gained rows with a `NULL actor_id` (shared
  inbox or malformed payloads), the rollback will fail until those rows are
  updated or deleted.
- **Activity-URI uniqueness (2026_10_15_000002 / 2026_10_15_000006).**
  Rollback of `2026_10_15_000006` drops the actor-scoped unique index and
  re-creates a global `UNIQUE(activity_uri)`. If the same `activity_uri` has
  legitimately been recorded for more than one local actor, that rollback will
  fail on the duplicate key. `2026_10_15_000002`'s rollback then drops the
  column entirely.
- **Interaction dedup index (2026_10_16_000001).** Dropping the unique index is
  safe, and the `down()` restores `interactions.type` to its default length.
  The duplicate-row hazard is on the *forward* path: if pre-existing duplicate
  interactions exist, the `up()` (and therefore a roll-back-then-forward-again)
  fails when the unique index is created. Deduplicate
  `(type, object_type, object_id, actor_id, remote_actor_id)` before re-running
  it.
- **New tables** (`activity_deliveries`, `reports`, `incoming_activities`) drop
  cleanly, but dropping them discards the per-inbox delivery, moderation and
  idempotency state that the current code relies on. Roll back the package code
  together with the schema.

### Further reading

- [`CHANGELOG.md`](CHANGELOG.md) — the per-release change log, including the
  full `Unreleased` section this guide is derived from.
- `vendor/danielpetrica/activitypub-for-laravel/config/activitypub.php` — the
  canonical config file with inline documentation for every key.
- `php artisan activitypub:prune-activities --help` — per-run retention
  overrides.
