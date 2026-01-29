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

Route::get('/profile', function () use ($projects, $profile, $badgeCatalog) {
    $user = Auth::user();
    if ($user && safeHasColumn('users', 'slug') && empty($user->slug)) {
        $user->slug = $generateUserSlug($user->name ?? 'user');
        $user->save();
    }
    if ($user && !empty($user->slug)) {
        return redirect()->route('profile.show', $user->slug);
    }

    return view('profile', [
        'projects' => $projects,
        'questions' => [],
        'comments' => [],
        'profile_user' => $profile,
        'is_owner' => false,
        'followers_count' => 0,
        'following_count' => 0,
        'is_following' => false,
        'badges' => [],
        'badge_catalog' => $badgeCatalog,
        'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
    ]);
})->name('profile');

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

Route::get('/profile/{slug}', function (string $slug) use ($projects, $profile, $mapPostToProject, $mapPostToQuestion, $preparePostStats, $badgeCatalog) {
    $viewer = Auth::user();
    $user = null;
    if (safeHasTable('users')) {
        $user = User::where('slug', $slug)->first();
        if (!$user) {
            $user = User::all()->first(function (User $candidate) use ($slug) {
                return Str::slug($candidate->name ?? '') === $slug;
            });
        }
    }

    if (!$user) {
        $fallbackSlug = Str::slug($profile['name'] ?? '');
        if ($fallbackSlug !== $slug) {
            abort(404);
        }
        return view('profile', [
            'projects' => $projects,
            'questions' => [],
            'comments' => [],
            'profile_user' => $profile,
            'is_owner' => false,
            'followers_count' => 0,
            'following_count' => 0,
            'is_following' => false,
            'badges' => [],
            'badge_catalog' => $badgeCatalog,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    if (safeHasColumn('users', 'slug') && empty($user->slug)) {
        $user->slug = $generateUserSlug($user->name ?? 'user');
        $user->save();
    }

    $isBanned = safeHasColumn('users', 'is_banned') ? (bool) $user->is_banned : false;

    $profileUser = [
        'id' => $user->id,
        'name' => $user->name,
        'slug' => $user->slug,
        'bio' => $user->bio ?? '',
        'role' => $user->roleKey(),
        'avatar' => $user->avatar ?? '/images/avatar-default.svg',
        'banner_url' => safeHasColumn('users', 'banner_url') ? ($user->banner_url ?: null) : null,
        'is_banned' => $isBanned,
        'allow_follow' => safeHasColumn('users', 'connections_allow_follow')
            ? (bool) $user->connections_allow_follow
            : true,
        'show_follow_counts' => safeHasColumn('users', 'connections_show_follow_counts')
            ? (bool) $user->connections_show_follow_counts
            : true,
    ];

    $projectsList = [];
    $questionsList = [];
    if (!$isBanned && safeHasTable('posts')) {
        $userPostsQuery = Post::with(['user', 'editedBy'])->where('user_id', $user->id);
        if (!app(VisibilityService::class)->canViewHidden($viewer, $user->id)) {
            app(VisibilityService::class)->applyToQuery($userPostsQuery, 'posts', $viewer);
        }
        $userPosts = $userPostsQuery->latest()->get();
        $stats = $preparePostStats($userPosts);
        $projectsList = $userPosts
            ->where('type', 'post')
            ->map(static fn (Post $post) => $mapPostToProject($post, $stats))
            ->values()
            ->all();
        $questionsList = $userPosts
            ->where('type', 'question')
            ->map(static fn (Post $post) => $mapPostToQuestion($post, $stats))
            ->values()
            ->all();
    }

    $commentsList = [];
    if (safeHasTable('post_comments')) {
        $commentQuery = PostComment::with('user')
            ->where('user_id', $user->id);
        if (!app(VisibilityService::class)->canViewHidden($viewer, $user->id)) {
            app(VisibilityService::class)->applyToQuery($commentQuery, 'post_comments', $viewer);
        }
        $commentRows = $commentQuery
            ->latest()
            ->take(20)
            ->get();

        $postMap = collect();
        if (safeHasTable('posts') && $commentRows->isNotEmpty()) {
            $postMapQuery = Post::whereIn('slug', $commentRows->pluck('post_slug')->all());
            if (!app(VisibilityService::class)->canViewHidden($viewer, $user->id)) {
                app(VisibilityService::class)->applyToQuery($postMapQuery, 'posts', $viewer);
            }
            $postMap = $postMapQuery
                ->get(['slug', 'title', 'type'])
                ->keyBy('slug');
        }

        $commentsList = $commentRows
            ->map(function (PostComment $comment) use ($postMap) {
                $post = $postMap->get($comment->post_slug);
                return [
                    'body' => $comment->body,
                    'time' => $comment->created_at?->diffForHumans() ?? '',
                    'post_slug' => $comment->post_slug,
                    'post_title' => $post?->title ?? $comment->post_slug,
                    'post_type' => $post?->type ?? 'post',
                ];
            })
            ->values()
            ->all();
    }

    $followersCount = 0;
    $followingCount = 0;
    $isFollowing = false;
    if (safeHasTable('user_follows')) {
        $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
        $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
        if (Auth::check()) {
            $isFollowing = DB::table('user_follows')
                ->where('following_id', $user->id)
                ->where('follower_id', Auth::id())
                ->exists();
        }
    }

    $badges = app(BadgePayloadService::class)->forUser($user, $badgeCatalog);

    return view('profile', [
        'projects' => $projectsList,
        'questions' => $questionsList,
        'comments' => $commentsList,
        'profile_user' => $profileUser,
        'is_owner' => Auth::id() === $user->id,
        'followers_count' => $followersCount,
        'following_count' => $followingCount,
        'is_following' => $isFollowing,
        'badges' => $badges,
        'badge_catalog' => $badgeCatalog,
        'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
    ]);
})->name('profile.show');

Route::post('/profile/{slug}/badges', [ProfileBadgeController::class, 'grant'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('profile.badges.grant');

Route::delete('/profile/{slug}/badges/{badgeId}', [ProfileBadgeController::class, 'revoke'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('profile.badges.revoke');

Route::post('/profile/{slug}/follow', [ProfileFollowController::class, 'toggle'])
    ->middleware(['auth', 'verified', 'throttle:profile-follow'])
    ->name('profile.follow');

