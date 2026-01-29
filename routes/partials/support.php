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

Route::get('/showcase', function () use ($showcase) {
    return view('showcase', ['showcase' => $showcase, 'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload()]);
})->name('showcase');

Route::get('/notifications', [NotificationsController::class, 'index'])
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

