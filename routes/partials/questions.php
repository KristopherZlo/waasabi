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

Route::get('/questions/{slug}', function (string $slug) use ($qa_questions, $mapPostToQuestion, $preparePostStats) {
    $viewer = Auth::user();
    $question = collect($qa_questions)->firstWhere('slug', $slug);
    if (safeHasTable('posts')) {
        $dbPost = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'question')->first();
        if ($dbPost) {
            if (safeHasColumn('users', 'is_banned') && $dbPost->user?->is_banned) {
                abort(404);
            }
            if (!app(VisibilityService::class)->canViewHidden($viewer, $dbPost->user_id)) {
                $isHidden = (bool) ($dbPost->is_hidden ?? false);
                $status = (string) ($dbPost->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    abort(404);
                }
            }
            $stats = $preparePostStats([$dbPost]);
            $question = $mapPostToQuestion($dbPost, $stats);
        }
    }
    abort_unless($question, 404);
    $questionMarkdown = (string) ($question['body_markdown'] ?? $question['body'] ?? '');
    if (trim($questionMarkdown) !== '') {
        $question['body_html'] = app(\App\Services\MarkdownService::class)->render($questionMarkdown);
    }

    $commentPageSize = 15;
    $answers = $question['answers'] ?? [];
    $answerTotal = 0;

    if (safeHasTable('post_comments')) {
        $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        app(VisibilityService::class)->applyToQuery($answerCountQuery, 'post_comments', $viewer);
        $answerTotal = $answerCountQuery->count();
        if ($answerTotal > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            app(VisibilityService::class)->applyToQuery($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->limit($commentPageSize)
                ->get();
            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    app(VisibilityService::class)->applyToQuery($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();

            $replyMap = $replyRows
                ->map(function (PostComment $comment) {
                    $author = $comment->user;
                    return [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());

            $answers = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    $entry = [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                    $replies = $replyMap->get($comment->id, []);
                    if (!empty($replies)) {
                        $entry['replies'] = $replies;
                    }
                    return $entry;
                })
                ->values()
                ->all();
        }
    }

    if ($answerTotal === 0) {
        $demoAnswers = $question['answers'] ?? [];
        $answerTotal = count($demoAnswers);
        $answers = array_slice($demoAnswers, 0, $commentPageSize);
    }

    $question['answers'] = $answers;
    $question['answers_total'] = $answerTotal;
    $question['answers_offset'] = count($answers);

    $current_user = app(\App\Services\UserPayloadService::class)->currentUserPayload();
    return view('questions.show', ['question' => $question, 'current_user' => $current_user]);
})->name('questions.show');

Route::get('/questions/{slug}/comments/chunk', function (Request $request, string $slug) use ($qa_questions) {
    $viewer = $request->user();
    $limit = max(1, min(30, (int) $request->query('limit', 15)));
    $offset = max(0, (int) $request->query('offset', 0));
    $answers = [];
    $total = 0;
    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);

    $question = collect($qa_questions)->firstWhere('slug', $slug);
    $dbExists = safeHasTable('posts')
        && Post::query()->where('slug', $slug)->where('type', 'question')->exists();

    if (!$question && !$dbExists) {
        return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
    }

    if (safeHasTable('posts')) {
        $post = Post::where('slug', $slug)->where('type', 'question')->first();
        if ($post && !app(VisibilityService::class)->canViewHidden($viewer, $post->user_id)) {
            $isHidden = (bool) ($post->is_hidden ?? false);
            $status = (string) ($post->moderation_status ?? 'approved');
            if ($isHidden || $status !== 'approved') {
                return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
            }
        }
    }

    if (safeHasTable('post_comments')) {
        $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        app(VisibilityService::class)->applyToQuery($answerCountQuery, 'post_comments', $viewer);
        $total = $answerCountQuery->count();
        if ($total > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            app(VisibilityService::class)->applyToQuery($parentRowsQuery, 'post_comments', $viewer);
            $parentRows = $parentRowsQuery
                ->latest()
                ->skip($offset)
                ->take($limit)
                ->get();
            $parentIds = $parentRows->pluck('id')->values();
            $replyRows = $parentIds->isEmpty()
                ? collect()
                : tap(PostComment::with('user')
                    ->where('post_slug', $slug)
                    ->whereIn('parent_id', $parentIds), function ($query) use ($viewer) {
                    app(VisibilityService::class)->applyToQuery($query, 'post_comments', $viewer);
                })
                    ->orderBy('created_at')
                    ->get();
            $replyMap = $replyRows
                ->map(function (PostComment $comment) {
                    $author = $comment->user;
                    return [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());
            $answers = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;
                    $entry = [
                        'author' => [
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->useful ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                    $replies = $replyMap->get($comment->id, []);
                    if (!empty($replies)) {
                        $entry['replies'] = $replies;
                    }
                    return $entry;
                })
                ->values()
                ->all();
        }
    }

    if ($total === 0) {
        $demoAnswers = $question['answers'] ?? [];
        $total = count($demoAnswers);
        $answers = array_slice($demoAnswers, $offset, $limit);
    }

    $items = [];
    foreach (array_values($answers) as $index => $answer) {
        $items[] = view('partials.qa-answer', [
            'answer' => $answer,
            'answerIndex' => $offset + $index,
            'questionSlug' => $slug,
            'roleKeys' => $roleKeys,
        ])->render();
    }

    $nextOffset = $offset + count($answers);

    return response()->json([
        'items' => $items,
        'total' => $total,
        'next_offset' => $nextOffset,
        'has_more' => $nextOffset < $total,
    ]);
})->name('questions.comments.chunk');

