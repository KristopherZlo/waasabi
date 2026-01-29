<?php

use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ProfileBadgeController;
use App\Http\Controllers\ProfileFollowController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReadLaterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Services\AutoModerationService;
use App\Services\BadgePayloadService;
use App\Services\BadgeCatalogService;
use App\Services\ContentModerationService;
use App\Services\FeedService;
use App\Services\ImageUploadService;
use App\Services\VisibilityService;
use App\Services\MakerPromotionService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\TopbarPromoService;
use App\Services\UserSlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Route::get('/profile', [ProfileController::class, 'index'])
    ->name('profile');
Route::get('/profile/settings', [ProfileSettingsController::class, 'edit'])
    ->middleware('auth')
    ->name('profile.settings');

Route::post('/profile/settings', [ProfileSettingsController::class, 'update'])
    ->middleware('auth')
    ->name('profile.settings.update');

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

Route::get('/profile/{slug}', [ProfileController::class, 'show'])
    ->name('profile.show');
Route::post('/profile/{slug}/badges', [ProfileBadgeController::class, 'grant'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('profile.badges.grant');

Route::delete('/profile/{slug}/badges/{badgeId}', [ProfileBadgeController::class, 'revoke'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('profile.badges.revoke');

Route::post('/profile/{slug}/follow', [ProfileFollowController::class, 'toggle'])
    ->middleware(['auth', 'verified', 'throttle:profile-follow'])
    ->name('profile.follow');



