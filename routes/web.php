<?php

use App\Http\Controllers\Admin\MediaProxyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterSubscriptionController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;

Route::get('/up', function () {
    return response()->noContent();
})->middleware(Sample::never());

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

Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (Request $request, string $id) {
    $user = User::findOrFail($id);

    if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
        abort(403);
    }

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    return redirect('office');
})->middleware(['signed'])->name('verification.verify');

Route::get('login', function () {
    return redirect('office');
})->name('login');
