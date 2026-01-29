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

Route::middleware(['auth', 'can:admin'])->group(function () {
    Route::post('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])
        ->name('admin.users.role');

    Route::delete('/admin/comments/{comment}', [AdminContentController::class, 'deleteComment'])
        ->name('admin.comments.delete');

    Route::delete('/admin/reviews/{review}', [AdminContentController::class, 'deleteReview'])
        ->name('admin.reviews.delete');

    Route::delete('/admin/posts/{post}', [AdminContentController::class, 'deletePost'])
        ->name('admin.posts.delete');

    Route::post('/admin/promos', function (Request $request) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unlimited = (bool) ($data['unlimited'] ?? false);
        TopbarPromo::create([
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_impressions' => $unlimited ? null : (isset($data['max_impressions']) ? (int) $data['max_impressions'] : null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.store');

    Route::put('/admin/promos/{promo}', function (Request $request, TopbarPromo $promo) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unlimited = (bool) ($data['unlimited'] ?? false);
        $promo->update([
            'label' => $data['label'],
            'url' => $data['url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_impressions' => $unlimited ? null : (isset($data['max_impressions']) ? (int) $data['max_impressions'] : null),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.update');

    Route::delete('/admin/promos/{promo}', function (TopbarPromo $promo) {
        if (!safeHasTable('topbar_promos')) {
            abort(503);
        }
        $promo->delete();
        Cache::forget('topbar.promos.v1');
        return redirect()->route('admin', ['tab' => 'promos']);
    })->name('admin.promos.delete');
});

