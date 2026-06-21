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
        'user_agent' => 'danielpetrica/activitypub-for-laravel (+https://danielpetrica.com)',
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
];
