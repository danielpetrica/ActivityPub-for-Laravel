# Upgrade Guide

This document describes breaking changes and the steps required to upgrade
between releases of `danielpetrica/activitypub-for-laravel`.

## Unreleased

### `ext-curl` is now required

The package now declares `"ext-curl": "*"` in `composer.json`. The curl
extension is what backs `SsrfGuard`'s `CURLOPT_RESOLVE` connection pinning
(guarding against DNS rebinding between the pre-flight check and the actual
outbound federation request), and the inbox delivery transport. Ensure the
extension is installed before upgrading:

```bash
php -m | grep -i curl
composer require danielpetrica/activitypub-for-laravel
```

### `ActivityRecorder::markDelivered()` removed

`DanielPetrica\LaravelActivityPub\Services\ActivityRecorder::markDelivered()`
has been removed. It was retained for backward compatibility but had no
callers. Advance activity delivery state through `recordInboxDelivery()` (per
inbox) or `syncAggregateStatus()` (recompute the aggregate from the per-inbox
rows) instead.

### Retention defaults to 30 days

Every `activitypub.retention.*` window now defaults to **30 days** —
`delivered_activities_days`, `received_activities_days`, `inbox_logs_days`
and `interactions_days` (received activities and interactions previously
defaulted to 90). `activitypub:prune-activities` is also registered on
Laravel's scheduler to run daily. If you relied on the old 90-day defaults,
set the environment variables explicitly:

```env
ACTIVITYPUB_RETENTION_RECEIVED_DAYS=90
ACTIVITYPUB_RETENTION_INTERACTIONS_DAYS=90
```

### Follower approval/rejection is ownership-scoped

`fediverse.followers.approve` and `fediverse.followers.reject` now act only on
followers owned by the authenticated user's actor. Acting on a follower owned
by another actor returns `403` unless the configured admin ability
(`fediverse.admin_gate`) allows the caller to act instance-wide.

### `ActorProfileContract` now requires `getProfileUrl()`

Any class that implements `DanielPetrica\LaravelActivityPub\Contracts\ActorProfileContract`
must now provide a `getProfileUrl(): string` method. It returns the public,
human-readable HTML profile URL for the actor (for example
`https://example.com/@alice`) and is used in place of the ActivityPub actor ID
where a profile link is displayed.

Classes that only *consume* the contract do not need changes.

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

### `ActivityBuilderContract` gained methods

`DanielPetrica\LaravelActivityPub\Contracts\ActivityBuilderContract` has new
methods and one changed signature. Any class that implements the contract
directly (rather than extending the bundled `ActivityBuilder`) must add them:

- `undo(ActorContract $actor, array $activity): array`
- `block(ActorContract $actor, string $objectUrl): array`
- `move(ActorContract $actor, string $targetUrl, array $alsoKnownAs = []): array`
- `undoBlock(ActorContract $actor, string $objectUrl): array`
- `buildObject(FederatableContentContract $content, array $mentions = []): array`
- `buildActivity(string $type, ActorContract $actor, array $object): array`
- `updateActor(ActorContract $actor, array $object): array`
- `attachMentions(array $activity, array $mentions): array`
- `undoFollow()` now accepts an optional third parameter: `undoFollow(ActorContract $actor, string $objectUrl, ?string $followId = null)`.

If you swap the builder through the container, verify it still satisfies the
expanded interface.

### New `ActivityType` enum cases

`DanielPetrica\LaravelActivityPub\Enums\ActivityType` gained the `Flag` and
`Move` cases. Code that switches exhaustively over `ActivityType` must handle
them (for example a `match` without a default arm will now throw
`UnhandledMatchError`).

### Actor `inbox`/`outbox` are plain IRI strings

The actor resource now emits `inbox` and `outbox` as plain IRI strings rather
than embedded objects. Systems that read the embedded object shape must read
the string value instead.

### Collection endpoints return a top-level `OrderedCollection`

Inbox, outbox, followers, following and featured endpoints now return a
top-level `OrderedCollection` carrying metadata only (`totalItems`, `first`,
`last`). The actual items are on `OrderedCollectionPage` documents reachable
through `?page=N`. Clients that read `orderedItems` from the collection root
must follow `first`/`?page=1` instead.

### `sharedInbox` no longer short-circuits address resolution

When delivering to an actor, the package now resolves the real object author
(`deliverToObjectAuthor`) and delivers to that actor's own inbox. A shared
inbox is still preferred for follower fan-out, but it is no longer used as a
substitute for resolving the addressed actor.

### Inbox processing is queued by default

Received inbox activities are dispatched to the queue
(`ProcessInboxActivity`) by default and the HTTP request returns `202`
immediately. Set `ACTIVITYPUB_QUEUE_PROCESS_INBOX=false` (config key
`queue.process_inbox`) only when you explicitly need the handler outcome
inline, such as in local development.

### Mutating Fediverse routes require the new admin ability

Mutating Fediverse routes (blocking domains/actors, pruning, rescheduling
deliveries, refreshing followed accounts, forwarding posts) now require an
admin authorization in addition to the dashboard gate. By default the package
checks the `activitypub.fediverse.admin` gate. Define it before upgrading:

```php
Gate::define('activitypub.fediverse.admin', fn ($user) => $user->isAdmin());
```

Configure a different ability with `ACTIVITYPUB_FEDIVERSE_ADMIN_GATE` (config
key `fediverse.admin_gate`). When the configured ability is undefined the
mutating routes deny by default (`403`).

### Stricter inbound HTTP-signature requirements

Inbound requests must satisfy stricter verification: a minimum set of signed
components is required, RFC 9421 (`signature-input`) requests are supported,
and actors that are not yet cached are fetched off the verification request
path (`http_signatures.queue_unknown_actors`). A request signed by an unknown
actor is rejected with `401` and retried once the actor has been stored.

### Third-party calls use positional arguments

The package's own APIs keep their documented named-argument style. Calls into
third-party APIs (Laravel, Eloquent, Carbon, OpenSSL, `Str`, `Http`, `Route`,
…) were converted back to positional arguments to remove coupling to
dependency parameter names. This does not change any public signature and is
not a breaking change for consumers; it is listed here for completeness.

## Release history

Older releases predate this guide. See [`CHANGELOG.md`](CHANGELOG.md) for the
per-release change log.
