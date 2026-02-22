<?php

use App\Http\Controllers\Admin\MediaProxyController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get(uri: '/admin/media-proxy', action: MediaProxyController::class)
    ->middleware(middleware: ['auth'])
    ->name(name: 'admin.media-proxy');

Route::post(uri: '/contact', action: [ContactController::class, 'submit'])
    ->name(name: 'contact.submit');
