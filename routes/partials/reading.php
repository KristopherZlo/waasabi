<?php

use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReadLaterController;
use Illuminate\Support\Facades\Route;

Route::delete('/posts/{post}', [PostController::class, 'destroy'])
    ->middleware('auth')
    ->name('posts.delete');

Route::get('/read-later', [CommunityPageController::class, 'saved'])
    ->middleware('auth')
    ->name('read-later');

Route::get('/read-later/list', [ReadLaterController::class, 'list'])
    ->middleware('auth')
    ->name('read-later.list');

Route::post('/read-later/sync', [ReadLaterController::class, 'sync'])
    ->middleware(['auth', 'throttle:read-later'])
    ->name('read-later.sync');

Route::get('/read-later/render', [ReadLaterController::class, 'render'])
    ->middleware(['auth', 'throttle:read-later'])
    ->name('read-later.render');
