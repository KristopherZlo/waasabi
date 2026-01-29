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

$badgeCatalog = app(BadgeCatalogService::class)->all();

$generateUserSlug = static function (string $name): string {
    return app(UserSlugService::class)->generate($name);
};

$preparePostStats = static function (iterable $posts): array {
    return FeedService::preparePostStats($posts, Auth::user());
};

$demoContent = app(\App\Services\DemoContentService::class);
$projects = $demoContent->projects();
$profile = $demoContent->profile();
$showcase = $demoContent->showcase();
$qa_questions = $demoContent->questions();
$mapPostToProject = static function (Post $post, array $stats): array {
    return FeedService::mapPostToProjectWithStats($post, $stats);
};

$mapPostToQuestion = static function (Post $post, array $stats): array {
    return FeedService::mapPostToQuestionWithStats($post, $stats);
};

$postSlugExists = static function (string $slug) use ($projects, $qa_questions): bool {
    if (safeHasTable('posts')) {
        return Post::where('slug', $slug)->exists();
    }
    return in_array($slug, collect($projects)->pluck('slug')->merge(collect($qa_questions)->pluck('slug'))->all(), true);
};

$searchIndex = app(\App\Services\FeedViewService::class)->buildSearchIndex($projects, $qa_questions);
$topbarPromo = app(TopbarPromoService::class)->pickPromo();

view()->composer(['layouts.app', 'layouts.support'], function ($view) {
    $payload = app(\App\Services\NotificationService::class)
        ->buildPayload((array) config('notifications.seed', []));
    $view->with([
        'unreadNotifications' => $payload['unreadPreview'],
        'unreadCount' => $payload['unreadCount'],
    ]);
});

view()->share([
    'searchIndex' => $searchIndex,
    'topbar_promo' => $topbarPromo,
]);



