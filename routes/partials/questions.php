<?php

use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::get('/questions/{slug}', [CommunityPageController::class, 'work'])
    ->name('questions.show');

Route::post('/questions/{slug}/comments', [ProjectController::class, 'storeComment'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('questions.comments.store');

Route::get('/questions/{slug}/comments/chunk', [QuestionController::class, 'commentsChunk'])
    ->name('questions.comments.chunk');
