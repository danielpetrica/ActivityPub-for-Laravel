<?php

use App\Http\Controllers\Admin\MediaProxyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get(uri: '/admin/media-proxy', action: MediaProxyController::class)
    ->middleware(middleware: ['auth'])
    ->name(name: 'admin.media-proxy');

Route::post(uri: '/contact', action: [ContactController::class, 'submit'])
    ->name(name: 'contact.submit');

Route::post('/subscribe', [NewsletterSubscriptionController::class, 'store'])
    ->name('newsletter.subscribe');

Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
})->name('csrf-token');
