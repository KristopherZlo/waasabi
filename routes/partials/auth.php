<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/two-factor-challenge', [AuthController::class, 'twoFactorForm'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:6,1')->name('two-factor.verify');
});

Route::get('/locale/{locale}', [AuthController::class, 'locale'])->name('locale');
Route::get('/login', [AuthController::class, 'loginForm'])->middleware('guest')->name('login');
Route::get('/register', [AuthController::class, 'registerForm'])->middleware('guest')->name('register');
Route::get('/verify-email', [AuthController::class, 'verificationForm'])->middleware('auth')->name('verification.notice');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:login'])->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:register'])->name('register.store');
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:verification'])->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth', 'throttle:verification'])->name('verification.send');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
