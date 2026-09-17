<?php

use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/promos/{promo}/click', [PromoController::class, 'click'])->name('promos.click');

Route::get('/feed/chunk', [FeedController::class, 'chunk'])->name('feed.chunk');
Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

Route::get('/', [CommunityPageController::class, 'feed'])->name('feed');

Route::get('/collaboration', [CommunityPageController::class, 'collaborations'])->name('collaboration');
Route::get('/collaboration/create', [CommunityPageController::class, 'helpEditor'])
    ->middleware(['auth', 'can:publish'])
    ->name('collaboration.create');
Route::get('/collaboration/{collaborationRequest}', [CommunityPageController::class, 'collaboration'])
    ->name('collaboration.show');
Route::post('/collaboration', [CollaborationController::class, 'store'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('collaboration.store');
Route::post('/collaboration/{collaborationRequest}/comments', [CollaborationController::class, 'storeComment'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('collaboration.comments.store');
Route::post('/collaboration/applications/{application}/messages', [CollaborationController::class, 'storeApplicationMessage'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('collaboration.applications.messages.store');
Route::delete('/collaboration/comments/{collaborationComment}', [CollaborationController::class, 'destroyComment'])
    ->middleware(['auth', 'throttle:comments'])
    ->name('collaboration.comments.destroy');
Route::middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])->group(function (): void {
    Route::post('/collaboration/{collaborationRequest}/applications', [CollaborationController::class, 'apply'])
        ->name('collaboration.applications.store');
    Route::patch('/collaboration/applications/{application}', [CollaborationController::class, 'decide'])
        ->name('collaboration.applications.decide');
    Route::delete('/collaboration/applications/{application}', [CollaborationController::class, 'withdraw'])
        ->name('collaboration.applications.withdraw');
    Route::patch('/collaboration/{collaborationRequest}/status', [CollaborationController::class, 'updateStatus'])
        ->name('collaboration.status');
    Route::delete('/collaboration/{collaborationRequest}', [CollaborationController::class, 'destroy'])
        ->name('collaboration.destroy');
});
