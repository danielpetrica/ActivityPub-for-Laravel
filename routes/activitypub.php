<?php

use DanielPetrica\LaravelActivityPub\Http\Controllers\ActorController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\FeaturedController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\FollowersController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\FollowingController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\HostMetaController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\InboxController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\NodeInfoController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\OutboxController;
use DanielPetrica\LaravelActivityPub\Http\Controllers\WebFingerController;
use Illuminate\Support\Facades\Route;

Route::get(uri: '/.well-known/host-meta', action: HostMetaController::class)
    ->name(name: 'activitypub.host-meta');

Route::get(uri: '/.well-known/nodeinfo', action: [NodeInfoController::class, 'discovery'])
    ->name(name: 'activitypub.nodeinfo.discovery');

Route::get(uri: '/nodeinfo/2.0', action: [NodeInfoController::class, 'index'])
    ->name(name: 'activitypub.nodeinfo');

Route::get(uri: '/.well-known/webfinger', action: WebFingerController::class)
    ->name(name: 'activitypub.webfinger');

Route::get(uri: '/users/{actor:username}', action: ActorController::class)
    ->name(name: 'activitypub.actor');

Route::get(uri: '/users/{actor:username}/outbox', action: OutboxController::class)
    ->name(name: 'activitypub.actor.outbox');

Route::get(uri: '/users/{actor:username}/followers', action: FollowersController::class)
    ->name(name: 'activitypub.actor.followers');

Route::get(uri: '/users/{actor:username}/following', action: FollowingController::class)
    ->name(name: 'activitypub.actor.following');

Route::get(uri: '/users/{actor:username}/featured', action: FeaturedController::class)
    ->name(name: 'activitypub.actor.featured');

Route::post(uri: '/inbox', action: [InboxController::class, 'sharedInbox'])
    ->name(name: 'activitypub.shared-inbox')
    ->middleware(middleware: [
        'throttle:activitypub-inbox',
        'activitypub.verify-signature',
    ]);

Route::match(
    methods: ['GET', 'HEAD'],
    uri: '/users/{actor:username}/inbox',
    action: [InboxController::class, 'index'],
)->name(name: 'activitypub.actor.inbox');

Route::post(uri: '/users/{actor:username}/inbox', action: InboxController::class)
    ->name(name: 'activitypub.actor.inbox.store')
    ->middleware(middleware: [
        'throttle:activitypub-inbox',
        'activitypub.verify-signature',
    ]);
