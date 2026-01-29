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

Route::post('/reading-progress', function (Request $request) use ($postSlugExists) {
    $data = $request->validate([
        'post_id' => ['required', 'string', 'max:190'],
        'percent' => ['required', 'integer', 'min:0', 'max:100'],
        'anchor' => ['nullable', 'string', 'max:190'],
    ]);

    if (!$postSlugExists($data['post_id'])) {
        return response()->json(['ok' => true]);
    }

    $timestamp = now();
    if (safeHasTable('reading_activity')) {
        $ip = (string) ($request->ip() ?? '');
        if ($ip !== '') {
            $salt = (string) config('app.key');
            $ipHash = hash('sha256', $salt . '|' . $ip);
            DB::table('reading_activity')->upsert(
                [
                    [
                        'ip_hash' => $ipHash,
                        'post_id' => $data['post_id'],
                        'updated_at' => $timestamp,
                        'created_at' => $timestamp,
                    ],
                ],
                ['ip_hash', 'post_id'],
                ['updated_at'],
            );
        }
    }

    if (!safeHasTable('reading_progress')) {
        return response()->json(['ok' => true]);
    }

    $userId = Auth::id();
    if (!$userId || !DB::table('users')->where('id', $userId)->exists()) {
        return response()->json(['ok' => true]);
    }

    DB::table('reading_progress')->upsert(
        [
            [
                'user_id' => $userId,
                'post_id' => $data['post_id'],
                'percent' => $data['percent'],
                'anchor' => $data['anchor'],
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ],
        ],
        ['user_id', 'post_id'],
        ['percent', 'anchor', 'updated_at'],
    );

    return response()->json(['ok' => true]);
})->middleware('throttle:reading-progress')->name('reading-progress');

Route::post('/uploads/images', [UploadController::class, 'storeImage'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:uploads'])
    ->name('uploads.images');

Route::post('/posts/{slug}/save', function (Request $request, string $slug) {
    if (!safeHasTable('post_saves')) {
        return response()->json(['message' => 'Saves table missing'], 503);
    }
    if (!safeHasTable('posts')) {
        return response()->json(['message' => 'Posts table missing'], 503);
    }

    $post = Post::where('slug', $slug)->firstOrFail();
    if (!app(VisibilityService::class)->canViewHidden($request->user(), $post->user_id)) {
        $isHidden = (bool) ($post->is_hidden ?? false);
        $status = (string) ($post->moderation_status ?? 'approved');
        if ($isHidden || $status !== 'approved') {
            return response()->json(['message' => 'Post not found'], 404);
        }
    }
    $userId = $request->user()->id;
    $exists = DB::table('post_saves')
        ->where('user_id', $userId)
        ->where('post_id', $post->id)
        ->exists();

    if ($exists) {
        DB::table('post_saves')
            ->where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();
    } else {
        DB::table('post_saves')->insert([
            'user_id' => $userId,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $count = DB::table('post_saves')->where('post_id', $post->id)->count();
    return response()->json(['saved' => !$exists, 'count' => $count]);
})->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.save');

Route::post('/posts/{slug}/upvote', function (Request $request, string $slug) {
    if (!safeHasTable('post_upvotes')) {
        return response()->json(['message' => 'Upvotes table missing'], 503);
    }
    if (!safeHasTable('posts')) {
        return response()->json(['message' => 'Posts table missing'], 503);
    }

    $post = Post::where('slug', $slug)->firstOrFail();
    if (!app(VisibilityService::class)->canViewHidden($request->user(), $post->user_id)) {
        $isHidden = (bool) ($post->is_hidden ?? false);
        $status = (string) ($post->moderation_status ?? 'approved');
        if ($isHidden || $status !== 'approved') {
            return response()->json(['message' => 'Post not found'], 404);
        }
    }
    $userId = $request->user()->id;
    $exists = DB::table('post_upvotes')
        ->where('user_id', $userId)
        ->where('post_id', $post->id)
        ->exists();

    if ($exists) {
        DB::table('post_upvotes')
            ->where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();
    } else {
        DB::table('post_upvotes')->insert([
            'user_id' => $userId,
            'post_id' => $post->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $author = User::find($post->user_id);
        if ($author) {
            app(MakerPromotionService::class)->maybePromote($author);
        }
    }

    $count = DB::table('post_upvotes')->where('post_id', $post->id)->count();
    return response()->json(['upvoted' => !$exists, 'count' => $count]);
})->middleware(['auth', 'verified', 'throttle:post-actions'])->name('posts.upvote');

Route::post('/reports', [ReportsController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:reports'])
    ->name('reports.store');

Route::get('/publish', [PublishController::class, 'create'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age'])
    ->name('publish');

Route::get('/posts/{slug}/edit', [PublishController::class, 'edit'])
    ->middleware(['auth', 'verified', 'account.age'])
    ->name('posts.edit');

Route::post('/publish', [PublishController::class, 'store'])
    ->middleware(['auth', 'can:publish', 'verified', 'account.age', 'throttle:publish'])
    ->name('publish.store');

