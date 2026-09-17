<?php

use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/showcase', [ShowcaseController::class, 'index'])->name('showcase');

Route::get('/notifications', [CommunityPageController::class, 'notifications'])
    ->middleware('auth')
    ->name('notifications');
Route::post('/notifications/{notification}/read', [NotificationsController::class, 'markRead'])
    ->middleware('auth')
    ->name('notifications.read');
Route::post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])
    ->middleware('auth')
    ->name('notifications.read_all');

Route::get('/support/kb/{slug}', [SupportController::class, 'kb'])->name('support.kb');
Route::get('/support/docs/{slug}', [SupportController::class, 'docs'])->name('support.docs');
Route::get('/support', [SupportController::class, 'index'])->name('support');
Route::get('/support/tickets/new', [SupportController::class, 'ticketNew'])
    ->middleware('auth')
    ->name('support.ticket');
Route::post('/support/tickets', [SupportTicketController::class, 'store'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:support-ticket'])
    ->name('support.ticket.store');
Route::post('/support/tickets/{ticket}/messages', [SupportTicketController::class, 'storeMessage'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:support-message'])
    ->name('support.ticket.message');
