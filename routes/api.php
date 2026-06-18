<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TrackerController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/status', [AuthController::class, 'status']);

Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
Route::post('/comments', [CommentController::class, 'store'])
    ->middleware('throttle:comments');

Route::post('/posts/{post}/like', [LikeController::class, 'store'])
    ->middleware('throttle:likes');

Route::get('/tracker/logo.gif', [TrackerController::class, 'track'])->name('tracker');

Route::get('/posts/{slug}.md', [PostController::class, 'show'])->where('slug', '.*');
Route::get('/posts/{slug}', [PostController::class, 'show']);

Route::get('/pages/{slug}', [PageController::class, 'show']);
