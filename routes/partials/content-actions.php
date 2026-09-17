<?php

use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\ContentActionController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/reading-progress', [ContentActionController::class, 'readingProgress'])
    ->middleware('throttle:reading-progress')->name('reading-progress');

Route::post('/uploads/images', [UploadController::class, 'storeImage'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:uploads'])
    ->name('uploads.images');

Route::post('/posts/{slug}/save', [ContentActionController::class, 'toggleSave'])
    ->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.save');

Route::post('/posts/{slug}/upvote', [ContentActionController::class, 'toggleUpvote'])
    ->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.upvote');

Route::post('/reports', [ReportsController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:reports'])
    ->name('reports.store');

Route::get('/create', [CommunityPageController::class, 'choose'])->name('create');

Route::get('/publish', [CommunityPageController::class, 'editor'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age'])
    ->name('publish');

Route::get('/posts/{slug}/edit', [CommunityPageController::class, 'editor'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('posts.edit');

Route::post('/publish', [PublishController::class, 'store'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('publish.store');
