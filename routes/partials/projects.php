<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\InteractionContentController;
use App\Http\Controllers\InteractionVoteController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/people', [CommunityPageController::class, 'people'])->name('people');
Route::put('/projects/{post}/follow', [CommunityController::class, 'follow'])
    ->middleware(['auth', 'verified', 'throttle:post-actions'])->name('projects.follow');
Route::get('/projects/{slug}/updates/create', [CommunityPageController::class, 'journalEditor'])
    ->middleware(['auth', 'verified'])->name('projects.updates.create');
Route::get('/projects/{slug}/updates/{projectUpdate}/edit', [CommunityPageController::class, 'journalEditor'])
    ->middleware(['auth', 'verified'])->name('projects.updates.edit');
Route::put('/projects/{slug}/updates/{projectUpdate}', [JournalController::class, 'update'])
    ->middleware(['auth', 'verified', 'throttle:publish'])->name('projects.updates.update');
Route::patch('/projects/{post}/members/{projectMember}', [ProjectMemberController::class, 'permissions'])
    ->middleware(['auth', 'verified'])->name('project-members.permissions');

Route::post('/projects/{post}/start', [CommunityPageController::class, 'startProject'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('projects.start');

Route::get('/projects/{slug}', [CommunityPageController::class, 'work'])->name('project');

Route::post('/projects/{slug}/comments', [ProjectController::class, 'storeComment'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('project.comments.store');

Route::get('/projects/{slug}/comments/chunk', [ProjectController::class, 'commentsChunk'])
    ->name('project.comments.chunk');

Route::post('/projects/{slug}/reviews', [ProjectController::class, 'storeReview'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:reviews'])
    ->name('project.reviews.store');

Route::put('/comments/{postComment}/vote', [InteractionVoteController::class, 'comment'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:post-actions'])
    ->name('comments.vote');
Route::put('/reviews/{postReview}/vote', [InteractionVoteController::class, 'review'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:post-actions'])
    ->name('reviews.vote');
Route::patch('/comments/{postComment}', [InteractionContentController::class, 'updateComment'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('comments.update');
Route::delete('/comments/{postComment}', [InteractionContentController::class, 'destroyComment'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('comments.destroy');
Route::patch('/reviews/{postReview}', [InteractionContentController::class, 'updateReview'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:reviews'])
    ->name('reviews.update');
Route::delete('/reviews/{postReview}', [InteractionContentController::class, 'destroyReview'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('reviews.destroy');

Route::post('/projects/{slug}/updates', [JournalController::class, 'store'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('projects.updates.store');
Route::delete('/projects/{slug}/updates/{projectUpdate}', [JournalController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('projects.updates.destroy');

Route::patch('/project-members/{projectMember}/accept', [ProjectMemberController::class, 'accept'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('project-members.accept');
Route::patch('/project-members/{projectMember}/decline', [ProjectMemberController::class, 'decline'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('project-members.decline');
Route::delete('/projects/{post}/members/{projectMember}', [ProjectMemberController::class, 'destroy'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('project-members.destroy');
