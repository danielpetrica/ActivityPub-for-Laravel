<?php

use DanielPetrica\LaravelActivityPub\Models\Actor;

return [
    'domain' => env('ACTIVITYPUB_DOMAIN', env('APP_URL')),

    'routes' => [
        'enabled' => true,
        'prefix' => '',
        'middleware' => ['api'],
    ],

    'actor_model' => Actor::class,

    'http_signatures' => [
        'enabled' => true,
        'max_clock_skew' => 300,
    ],

    'federation' => [
        'enabled' => env('ACTIVITYPUB_FEDERATION_ENABLED', false),
        'max_delivery_attempts' => 3,
        'delivery_timeout' => 10,
        'user_agent' => 'danielpetrica/laravel-activitypub (+https://danielpetrica.com)',
    ],

    'fediverse' => [
        'enabled' => true,
        'prefix' => 'fediverse',
        'middleware' => ['web', 'auth'],
    ],
];
