<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CommunityPageController;
use App\Http\Controllers\ProfileBadgeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileFollowController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ProfileWallController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [CommunityPageController::class, 'settings'])
    ->middleware('auth')
    ->name('settings');

Route::get('/profile', [ProfileController::class, 'index'])
    ->name('profile');
Route::get('/profile/settings', [CommunityPageController::class, 'settings'])
    ->middleware('auth')
    ->name('profile.settings');

Route::post('/profile/settings', [ProfileSettingsController::class, 'update'])
    ->middleware('auth')
    ->name('profile.settings.update');
Route::patch('/account/password', [AccountController::class, 'updatePassword'])
    ->middleware(['auth', 'throttle:6,1'])->name('account.password.update');
Route::get('/account/export', [AccountController::class, 'export'])
    ->middleware(['auth', 'throttle:3,1'])->name('account.export');
Route::delete('/account', [AccountController::class, 'destroy'])
    ->middleware(['auth', 'throttle:3,1'])->name('account.destroy');

Route::post('/profile/{slug}/banner', [ProfileSettingsController::class, 'updateBanner'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.banner.update');

Route::delete('/profile/{slug}/banner', [ProfileSettingsController::class, 'deleteBanner'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.banner.delete');

Route::post('/profile/{slug}/avatar', [ProfileSettingsController::class, 'updateAvatar'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.avatar.update');

Route::delete('/profile/{slug}/avatar', [ProfileSettingsController::class, 'deleteAvatar'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:profile-media'])
    ->name('profile.avatar.delete');

Route::get('/profile/{slug}', [CommunityPageController::class, 'profile'])
    ->name('profile.show');
Route::post('/profile/{slug}/badges', [ProfileBadgeController::class, 'grant'])
    ->middleware(['auth', 'can:admin', 'throttle:10,1'])
    ->name('profile.badges.grant');

Route::delete('/profile/{slug}/badges/{badgeId}', [ProfileBadgeController::class, 'revoke'])
    ->middleware(['auth', 'can:admin', 'throttle:10,1'])
    ->name('profile.badges.revoke');

Route::post('/profile/{slug}/follow', [ProfileFollowController::class, 'toggle'])
    ->middleware(['auth', 'verified', 'throttle:profile-follow'])
    ->name('profile.follow');

Route::post('/profile/{user:slug}/wall', [ProfileWallController::class, 'store'])
    ->middleware(['auth', 'verified', 'account.age', 'throttle:comments'])
    ->name('profile.wall.store');
Route::delete('/profile/wall/{profileWallPost}', [ProfileWallController::class, 'destroy'])
    ->middleware(['auth', 'throttle:comments'])
    ->name('profile.wall.destroy');
