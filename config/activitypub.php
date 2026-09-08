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
        'enabled' => env('ACTIVITYPUB_FEDERATION_ENABLED', false),
        'max_delivery_attempts' => 3,
        'delivery_timeout' => 10,
        'resolve_timeout' => env('ACTIVITYPUB_RESOLVE_TIMEOUT', 10),
        'user_agent' => 'danielpetrica/activitypub-for-laravel (+https://danielpetrica.com)',
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
    */
    'queue' => [
        'connection' => env('ACTIVITYPUB_QUEUE_CONNECTION'),
        'queue' => env('ACTIVITYPUB_QUEUE_NAME', 'default'),
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
    */
    'fediverse' => [
        'enabled' => env('ACTIVITYPUB_FEDIVERSE_ENABLED', true),
        'prefix' => 'fediverse',
        'middleware' => ['web', 'auth'],
        'gate' => env('ACTIVITYPUB_FEDIVERSE_GATE'),
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
