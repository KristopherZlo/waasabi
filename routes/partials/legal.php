<?php

use Illuminate\Support\Facades\Route;

Route::prefix('legal')->group(function () {
    Route::get('/terms', fn () => redirect()->route('support.docs', ['slug' => 'terms-of-service']))->name('legal.terms');
    Route::get('/privacy', fn () => redirect()->route('support.docs', ['slug' => 'privacy-policy']))->name('legal.privacy');
    Route::get('/cookies', fn () => redirect()->route('support.docs', ['slug' => 'cookie-policy']))->name('legal.cookies');
    Route::get('/guidelines', fn () => redirect()->route('support.docs', ['slug' => 'community-guidelines']))->name('legal.guidelines');
    Route::get('/notice-and-action', fn () => redirect()->route('support.docs', ['slug' => 'notice-and-action']))->name('legal.notice');
    Route::get('/legal-notice', fn () => redirect()->route('support.docs', ['slug' => 'legal-notice']))->name('legal.legal-notice');
});
