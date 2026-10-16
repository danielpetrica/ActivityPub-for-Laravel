<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;

return [

    /*
    |--------------------------------------------------------------------------
    | Domain
    |--------------------------------------------------------------------------
    |
    | The domain used for ActivityPub identifiers. This should match the
    | domain where the application is hosted. All actor URIs will be
    | constructed relative to this domain.
    |
    */
    'domain' => env('ACTIVITYPUB_DOMAIN', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | Actor Type
    |--------------------------------------------------------------------------
    |
    | The default ActivityPub actor type. Can be 'Person', 'Group', 'Service',
    | 'Application', or 'Organization'. Consumers can override this per-actor
    | by setting the 'type' column on their Actor model, or globally here.
    |
    */
    'actor_type' => 'Person',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure whether ActivityPub routes are automatically registered,
    | their URL prefix, and the middleware stack they should use.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model class used for ActivityPub actors. You can swap
    | this with your own model if it implements the required interface.
    |
    */
    'actor_model' => Actor::class,

    /*
    |--------------------------------------------------------------------------
    | Federatable Models
    |--------------------------------------------------------------------------
    |
    | List of Eloquent model classes that implement FederatableContentContract.
    | These models are queried when forwarding posts to new followers.
    |
    */
    'federatable_models' => [],

    /*
    |--------------------------------------------------------------------------
    | HTTP Signatures
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP Signature verification and generation used when
    | receiving activities from remote servers and delivering activities
    | to remote inboxes.
    |
    */
    'http_signatures' => [
        'enabled' => true,
        'max_clock_skew' => 120,

        // When a signed request arrives from an actor that is not yet cached,
        // queue a background FetchRemoteActor job instead of performing a
        // synchronous outbound fetch on the verification request path. The
        // request is rejected with 401 and retried once the actor is stored.
        'queue_unknown_actors' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Fetch Security (SSRF)
    |--------------------------------------------------------------------------
    |
    | Hardening for every outbound federation fetch and inbox delivery. Remote
    | URLs must be HTTPS and must resolve exclusively to public IP addresses.
    | Private, loopback, link-local, carrier-grade NAT and other reserved
    | ranges are always rejected.
    |
    */
    'security' => [
        // Hosts exempt from DNS resolution checks (private IP literals are
        // still rejected). Intended for trusted internal test targets only.
        'allowed_hosts' => [],

        // Maximum accepted response body size (bytes) for remote fetches.
        'max_response_bytes' => env('ACTIVITYPUB_MAX_RESPONSE_BYTES', 2_000_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Federation
    |--------------------------------------------------------------------------
    |
    | Outbound federation settings. When enabled, activities will be
    | delivered to followers' inboxes.
    |
    */
    'federation' => [
        'enabled' => env('ACTIVITYPUB_FEDERATION_ENABLED', true),
        'max_delivery_attempts' => 3,
        'delivery_timeout' => 10,
        'resolve_timeout' => env('ACTIVITYPUB_RESOLVE_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbox Admission Control
    |--------------------------------------------------------------------------
    |
    | Optional, normalized controls enforced centrally by InboxProcessor for
    | every inbound activity type (before handlers run).
    |
    | `allowlist`: when `enabled` is true, an inbound activity whose sender
    | domain is not listed in `domains` is dropped (logged, HTTP 202 already
    | returned). Domains are matched through DomainNormalizer, so casing,
    | trailing dots, ports and `www.` are all normalized. Off by default. When
    | enabled with an empty list the check is treated as "not configured" and
    | allows traffic, so an accidental enable does not black-hole the instance.
    |
    | `quotas`: windowed per-actor/per-domain caps that bound how much a single
    | hostile instance can accumulate. `per_actor_per_minute` and
    | `per_domain_per_minute` are inbox caps; `per_actor_per_day` is a longer
    | inbox cap; `interactions_per_actor_per_day` additionally caps Like and
    | Announce interactions. Counters live in the cache and the check fails
    | open (a broken cache never blocks federation); the HTTP-level inbox rate
    | limiter still bounds raw request volume. Set `enabled` to false to
    | disable entirely, or set a limit to 0 to disable that single window.
    |
    */
    'inbox' => [
        'allowlist' => [
            'enabled' => env('ACTIVITYPUB_INBOX_ALLOWLIST_ENABLED', false),
            'domains' => [],
        ],

        'quotas' => [
            'enabled' => env('ACTIVITYPUB_INBOX_QUOTAS_ENABLED', true),
            'per_actor_per_minute' => env('ACTIVITYPUB_INBOX_PER_ACTOR_PER_MINUTE', 120),
            'per_domain_per_minute' => env('ACTIVITYPUB_INBOX_PER_DOMAIN_PER_MINUTE', 600),
            'per_actor_per_day' => env('ACTIVITYPUB_INBOX_PER_ACTOR_PER_DAY', 5000),
            'interactions_per_actor_per_day' => env('ACTIVITYPUB_INBOX_INTERACTIONS_PER_ACTOR_PER_DAY', 1000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and queue name used for delivering
    | activities. Leave connection null to use the application default.
    | Set the queue name to route deliveries to a specific queue.
    |
    | `process_inbox` controls whether received inbox activities are handled
    | on the queue (ProcessInboxActivity) instead of synchronously inside the
    | HTTP request. Queued handling is strongly recommended in production: the
    | request returns 202 immediately and remote resolution runs on a worker.
    | Set to false only for local development or when you explicitly need the
    | handler outcome inline.
    |
    */
    'queue' => [
        'connection' => env('ACTIVITYPUB_QUEUE_CONNECTION'),
        'queue' => env('ACTIVITYPUB_QUEUE_NAME', 'default'),
        'process_inbox' => env('ACTIVITYPUB_QUEUE_PROCESS_INBOX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Version
    |--------------------------------------------------------------------------
    |
    | The software version reported in NodeInfo responses.
    |
    */
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Open Registrations
    |--------------------------------------------------------------------------
    |
    | Whether the instance allows open registration, reported in NodeInfo.
    |
    */
    'open_registrations' => false,

    /*
    |--------------------------------------------------------------------------
    | Fediverse Admin Dashboard
    |--------------------------------------------------------------------------
    |
    | Settings for the Blade-based Fediverse dashboard. When enabled, routes
    | under the configured prefix are registered with 'web' and 'auth'
    | middleware. The authenticated user must implement ActorContract.
    |
    | `gate` optionally restricts access to the whole dashboard. When set, the
    | named gate must pass for every Fediverse route.
    |
    | Mutating routes that change instance-wide state (blocking domains and
    | actors, pruning, rescheduling deliveries, refreshing followed accounts
    | and forwarding posts) additionally require an admin authorization. By
    | default the package checks the `activitypub.fediverse.admin` gate:
    |
    |   Gate::define('activitypub.fediverse.admin', fn ($user) => $user->isAdmin());
    |
    | Set `admin_gate` to any other gate name to customise the check. When the
    | configured ability is not defined the mutating routes deny by default
    | (403), so a fresh install must define the ability before it can use them.
    |
    */
    'fediverse' => [
        'enabled' => env('ACTIVITYPUB_FEDIVERSE_ENABLED', true),
        'prefix' => 'fediverse',
        'middleware' => ['web', 'auth'],
        'gate' => env('ACTIVITYPUB_FEDIVERSE_GATE'),
        'admin_gate' => env('ACTIVITYPUB_FEDIVERSE_ADMIN_GATE', 'activitypub.fediverse.admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbox Audit Log
    |--------------------------------------------------------------------------
    |
    | Rejected inbox requests (missing/invalid signatures, validation
    | failures) are recorded in the `inbox_logs` table so operators have an
    | audit trail. This is independent of `debug_display` and is on by
    | default; disable it if the table write is too costly for your traffic
    | profile. Logging failures never affect the HTTP response.
    |
    */
    'inbox_log' => [
        'enabled' => env('ACTIVITYPUB_INBOX_LOG_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long federation data is kept before `activitypub:prune-activities`
    | removes it. Every window defaults to 30 days and can be overridden with
    | the matching environment variable, in the config file, or per run with
    | the matching command option (see
    | `php artisan activitypub:prune-activities --help`).
    |
    | `activitypub:prune-activities` is registered on Laravel's scheduler to
    | run daily automatically (without overlapping); no cron wiring beyond
    | `php artisan schedule:run` is required.
    |
    | Note: incoming activities are used to deduplicate redelivered
    | activities, so a shorter window means an old activity that is
    | redelivered after pruning may be processed again.
    |
    */
    'retention' => [
        'delivered_activities_days' => env('ACTIVITYPUB_RETENTION_DELIVERED_DAYS', 30),
        'received_activities_days' => env('ACTIVITYPUB_RETENTION_RECEIVED_DAYS', 30),
        'inbox_logs_days' => env('ACTIVITYPUB_RETENTION_INBOX_LOGS_DAYS', 30),
        'interactions_days' => env('ACTIVITYPUB_RETENTION_INTERACTIONS_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache-Control headers for ActivityPub responses. Disable if you need
    | immediate propagation of profile or actor changes.
    |
    */
    'cache' => [
        'enabled' => env('ACTIVITYPUB_CACHE_ENABLED', true),
        'ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging for federation operations. When enabled, the
    | package logs every step of remote actor resolution, WebFinger lookups,
    | HTTP responses from remote servers, and delivery outcomes.
    |
    | Use a dedicated log channel to keep federation logs separate from your
    | application logs. Define the channel in config/logging.php:
    |
    |   'activitypub' => [
    |       'driver' => 'daily',
    |       'path' => storage_path('logs/activitypub.log'),
    |       'days' => 14,
    |   ],
    |
    */
    'logging' => [
        'enabled' => env('ACTIVITYPUB_LOGGING_ENABLED', false),
        'channel' => env('ACTIVITYPUB_LOG_CHANNEL'),
        'level' => env('ACTIVITYPUB_LOG_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Display
    |--------------------------------------------------------------------------
    |
    | When enabled, the Fediverse inbox and outbox views will show debug
    | information including response codes, error messages from remote
    | servers, and delivery attempt details. Useful during development.
    |
    */
    'debug_display' => env('ACTIVITYPUB_DEBUG_DISPLAY', false),
];
