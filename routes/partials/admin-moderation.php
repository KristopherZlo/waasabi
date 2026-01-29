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

Route::middleware(['auth', 'can:moderate'])->group(function () {
    Route::get('/admin', function (Request $request) {
        $perPage = 20;
        $search = trim((string) $request->query('q', ''));
        $like = $search !== '' ? '%' . $search . '%' : null;
        $moderationSort = (string) $request->query('sort', 'reporters');
        $moderationSort = in_array($moderationSort, ['reporters', 'recent'], true) ? $moderationSort : 'reporters';

        $users = User::query()
            ->when($search !== '', function ($query) use ($like) {
                $query->where(function ($subQuery) use ($like) {
                    $subQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('slug', 'like', $like);
                });
            })
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'users_page');
        $comments = safeHasTable('post_comments')
            ? PostComment::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('body', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'comments_page')
            : collect();
        $reviews = safeHasTable('post_reviews')
            ? PostReview::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('improve', 'like', $like)
                            ->orWhere('why', 'like', $like)
                            ->orWhere('how', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'reviews_page')
            : collect();
        $reportedPosts = collect();
        if (safeHasTable('content_reports') && safeHasTable('posts')) {
            $reportsHaveWeight = safeHasColumn('content_reports', 'weight');
            $weightSelect = $reportsHaveWeight
                ? DB::raw('coalesce(sum(weight), 0) as weight_total')
                : DB::raw('count(*) as weight_total');
            $reportRowsQuery = DB::table('content_reports')
                ->select('content_id', DB::raw('count(*) as report_count'), $weightSelect)
                ->where('content_type', 'post')
                ->whereNotNull('content_id');

            if ($search !== '') {
                $matchedPostIds = Post::query()
                    ->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->pluck('id');
                $matchedPostSlugs = Post::query()
                    ->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->pluck('slug');

                $reportRowsQuery->where(function ($subQuery) use ($like, $matchedPostIds, $matchedPostSlugs) {
                    if ($matchedPostIds->isNotEmpty()) {
                        $subQuery->orWhereIn('content_id', $matchedPostIds->map(fn ($id) => (string) $id)->all());
                    }
                    if ($matchedPostSlugs->isNotEmpty()) {
                        $subQuery->orWhereIn('content_id', $matchedPostSlugs->all());
                    }
                    if (safeHasColumn('content_reports', 'details')) {
                        $subQuery->orWhere('details', 'like', $like);
                    }
                });
            }

            $reportRows = $reportRowsQuery
                ->groupBy('content_id')
                ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                ->orderByDesc('report_count')
                ->paginate($perPage, ['*'], 'reports_page');

            $contentIds = collect($reportRows->items())
                ->map(fn ($row) => (string) $row->content_id)
                ->filter()
                ->unique()
                ->values();

            $numericIds = $contentIds->filter(fn ($id) => ctype_digit($id))->values();
            $slugIds = $contentIds->reject(fn ($id) => ctype_digit($id))->values();

            $postsById = $numericIds->isNotEmpty()
                ? Post::query()->whereIn('id', $numericIds->map(fn ($id) => (int) $id)->all())->get()->keyBy('id')
                : collect();
            $postsBySlug = $slugIds->isNotEmpty()
                ? Post::query()->whereIn('slug', $slugIds->all())->get()->keyBy('slug')
                : collect();

            $detailRows = $contentIds->isNotEmpty()
                ? DB::table('content_reports')
                    ->select('content_id', 'details', 'created_at')
                    ->where('content_type', 'post')
                    ->whereIn('content_id', $contentIds->all())
                    ->whereNotNull('details')
                    ->where('details', '<>', '')
                    ->orderByDesc('created_at')
                    ->get()
                : collect();

            $detailsById = $detailRows->groupBy('content_id')->map(function ($rows) {
                return $rows->first();
            });

            $mappedReports = collect($reportRows->items())
                ->map(function ($row) use ($postsById, $postsBySlug, $detailsById, $reportsHaveWeight) {
                    $contentId = (string) $row->content_id;
                    $post = ctype_digit($contentId)
                        ? $postsById->get((int) $contentId)
                        : $postsBySlug->get($contentId);
                    if (!$post) {
                        return null;
                    }
                    $detail = $detailsById->get($contentId);
                    $pointsRaw = $reportsHaveWeight ? (float) ($row->weight_total ?? 0) : (float) ($row->report_count ?? 0);
                    $points = $reportsHaveWeight ? round($pointsRaw, 1) : (int) $pointsRaw;
                    return [
                        'post' => $post,
                        'count' => (int) $row->report_count,
                        'points' => $points,
                        'details' => $detail?->details,
                        'reported_at' => $detail?->created_at,
                    ];
                })
                ->filter()
                ->values();
            $reportRows->setCollection($mappedReports);
            $reportedPosts = $reportRows;
        }

        $mediaReports = collect();
        if (safeHasTable('content_reports')) {
            $mediaReports = ContentReport::with('user')
                ->where('content_type', 'content')
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('content_url', 'like', $like)
                            ->orWhere('details', 'like', $like);
                    });
                })
                ->latest()
                ->paginate($perPage, ['*'], 'media_page');
        }

        $moderationFeed = collect();
        if (safeHasTable('content_reports')) {
            $reportTypes = ['post', 'question', 'comment', 'review'];
            $reportsHaveWeight = safeHasColumn('content_reports', 'weight');
            $weightSelect = $reportsHaveWeight
                ? DB::raw('coalesce(sum(weight), 0) as weight_total')
                : DB::raw('count(*) as weight_total');
            $reportQuery = DB::table('content_reports')
                ->select(
                    'content_type',
                    'content_id',
                    DB::raw('count(*) as report_count'),
                    DB::raw('count(distinct coalesce(user_id, id)) as reporters_count'),
                    DB::raw('max(created_at) as last_report_at'),
                    DB::raw('max(content_url) as content_url'),
                    $weightSelect,
                )
                ->whereIn('content_type', $reportTypes)
                ->whereNotNull('content_id')
                ->where('content_id', '<>', '');

            if ($search !== '' && $like !== null) {
                $postMatches = collect();
                $questionMatches = collect();
                if (safeHasTable('posts')) {
                    $postMatches = Post::query()
                        ->where('type', 'post')
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('title', 'like', $like)
                                ->orWhere('slug', 'like', $like)
                                ->orWhere('subtitle', 'like', $like);
                        })
                        ->get(['id', 'slug']);
                    $questionMatches = Post::query()
                        ->where('type', 'question')
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('title', 'like', $like)
                                ->orWhere('slug', 'like', $like)
                                ->orWhere('subtitle', 'like', $like);
                        })
                        ->get(['id', 'slug']);
                }

                $commentMatchIds = safeHasTable('post_comments')
                    ? PostComment::query()
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('body', 'like', $like)
                                ->orWhere('post_slug', 'like', $like);
                        })
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                    : collect();

                $reviewMatchIds = safeHasTable('post_reviews')
                    ? PostReview::query()
                        ->where(function ($query) use ($like) {
                            $query
                                ->where('improve', 'like', $like)
                                ->orWhere('why', 'like', $like)
                                ->orWhere('how', 'like', $like)
                                ->orWhere('post_slug', 'like', $like);
                        })
                        ->pluck('id')
                        ->map(fn ($id) => (string) $id)
                    : collect();

                $postMatchIds = $postMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $postMatchSlugs = $postMatches->pluck('slug')->filter();
                $questionMatchIds = $questionMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $questionMatchSlugs = $questionMatches->pluck('slug')->filter();

                $reportQuery->where(function ($query) use ($like, $postMatchIds, $postMatchSlugs, $questionMatchIds, $questionMatchSlugs, $commentMatchIds, $reviewMatchIds) {
                    $query
                        ->where('content_url', 'like', $like)
                        ->orWhere('details', 'like', $like)
                        ->orWhere('content_id', 'like', $like);

                    if ($postMatchIds->isNotEmpty() || $postMatchSlugs->isNotEmpty()) {
                        $postMatches = $postMatchIds->merge($postMatchSlugs)->unique()->values()->all();
                        $query->orWhere(function ($subQuery) use ($postMatches) {
                            $subQuery->where('content_type', 'post')->whereIn('content_id', $postMatches);
                        });
                    }
                    if ($questionMatchIds->isNotEmpty() || $questionMatchSlugs->isNotEmpty()) {
                        $questionMatches = $questionMatchIds->merge($questionMatchSlugs)->unique()->values()->all();
                        $query->orWhere(function ($subQuery) use ($questionMatches) {
                            $subQuery->where('content_type', 'question')->whereIn('content_id', $questionMatches);
                        });
                    }
                    if ($commentMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($commentMatchIds) {
                            $subQuery->where('content_type', 'comment')->whereIn('content_id', $commentMatchIds->all());
                        });
                    }
                    if ($reviewMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($reviewMatchIds) {
                            $subQuery->where('content_type', 'review')->whereIn('content_id', $reviewMatchIds->all());
                        });
                    }
                });
            }

            $reportQuery->groupBy('content_type', 'content_id');

            if ($moderationSort === 'reporters') {
                $reportQuery
                    ->orderByDesc('reporters_count')
                    ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                    ->orderByDesc('last_report_at');
            } else {
                $reportQuery
                    ->orderByDesc($reportsHaveWeight ? 'weight_total' : 'report_count')
                    ->orderByDesc('last_report_at');
            }

            $moderationFeed = $reportQuery->paginate($perPage, ['*'], 'moderation_page');
            $reportRows = collect($moderationFeed->items());
            $detailsByKey = collect();
            if (safeHasColumn('content_reports', 'details') && $reportRows->isNotEmpty()) {
                $detailContentIds = $reportRows->pluck('content_id')->filter()->unique()->values();
                if ($detailContentIds->isNotEmpty()) {
                    $detailRows = DB::table('content_reports')
                        ->select('content_type', 'content_id', 'details', 'created_at')
                        ->whereIn('content_type', $reportTypes)
                        ->whereIn('content_id', $detailContentIds->all())
                        ->whereNotNull('details')
                        ->where('details', '<>', '')
                        ->orderByDesc('created_at')
                        ->get();
                    $detailsByKey = $detailRows->groupBy(fn ($row) => $row->content_type . ':' . $row->content_id)
                        ->map(fn ($rows) => $rows->first());
                }
            }

            $postContentIds = $reportRows
                ->filter(fn ($row) => in_array($row->content_type, ['post', 'question'], true))
                ->pluck('content_id')
                ->filter()
                ->values();
            $numericPostIds = $postContentIds->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->values();
            $slugPostIds = $postContentIds->reject(fn ($id) => ctype_digit((string) $id))->values();

            $posts = collect();
            if (safeHasTable('posts') && ($numericPostIds->isNotEmpty() || $slugPostIds->isNotEmpty())) {
                $posts = Post::with(['user', 'editedBy'])
                    ->where(function ($query) use ($numericPostIds, $slugPostIds) {
                        if ($numericPostIds->isNotEmpty()) {
                            $query->whereIn('id', $numericPostIds->all());
                        }
                        if ($slugPostIds->isNotEmpty()) {
                            $query->orWhereIn('slug', $slugPostIds->all());
                        }
                    })
                    ->get();
            }

            $postsById = $posts->keyBy('id');
            $postsBySlug = $posts->keyBy('slug');
            $stats = $posts->isNotEmpty()
                ? FeedService::preparePostStats($posts, $request->user())
                : [];

            $commentIds = $reportRows
                ->where('content_type', 'comment')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();
            $reviewIds = $reportRows
                ->where('content_type', 'review')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();

            $comments = $commentIds->isNotEmpty() && safeHasTable('post_comments')
                ? PostComment::with('user')->whereIn('id', $commentIds->all())->get()->keyBy('id')
                : collect();
            $reviews = $reviewIds->isNotEmpty() && safeHasTable('post_reviews')
                ? PostReview::with('user')->whereIn('id', $reviewIds->all())->get()->keyBy('id')
                : collect();

            $contextPostSlugs = collect()
                ->merge($comments->pluck('post_slug'))
                ->merge($reviews->pluck('post_slug'))
                ->filter()
                ->unique()
                ->values();
            $contextPosts = $contextPostSlugs->isNotEmpty() && safeHasTable('posts')
                ? Post::query()
                    ->whereIn('slug', $contextPostSlugs->all())
                    ->get(['id', 'slug', 'title', 'type', 'user_id'])
                    ->keyBy('slug')
                : collect();

            $moderationItems = $reportRows
                ->map(function ($row) use ($postsById, $postsBySlug, $stats, $comments, $reviews, $contextPosts, $detailsByKey, $reportsHaveWeight) {
                    $contentId = (string) $row->content_id;
                    $reportCount = (int) ($row->report_count ?? 0);
                    $reportersCount = (int) ($row->reporters_count ?? 0);
                    if ($reportsHaveWeight) {
                        $reportPoints = round((float) ($row->weight_total ?? 0), 1);
                    } else {
                        $reportPoints = $reportersCount > 0 ? $reportersCount : $reportCount;
                    }
                    $lastReportedAt = $row->last_report_at ?? null;
                    $contentUrl = $row->content_url ?? null;
                    $detailKey = $row->content_type . ':' . $contentId;
                    $detailRow = $detailsByKey->get($detailKey);
                    $detailText = is_object($detailRow) ? (string) ($detailRow->details ?? '') : '';
                    $moderationNsfwPending = false;
                    if ($detailText !== '') {
                        $detailLower = Str::lower($detailText);
                        $moderationNsfwPending = Str::contains($detailLower, 'rekognition')
                            && Str::contains($detailLower, 'unavailable');
                    }

                    if (in_array($row->content_type, ['post', 'question'], true)) {
                        $post = ctype_digit($contentId)
                            ? $postsById->get((int) $contentId)
                            : $postsBySlug->get($contentId);
                        if (!$post) {
                            return null;
                        }
                        $data = $post->type === 'question'
                            ? FeedService::mapPostToQuestionWithStats($post, $stats)
                            : FeedService::mapPostToProjectWithStats($post, $stats);
                        $data['report_count'] = $reportCount;
                        $data['report_points'] = $reportPoints;
                        $data['reporters_count'] = $reportersCount;
                        $data['last_report_at'] = $lastReportedAt;
                        $data['moderation_nsfw_pending'] = $moderationNsfwPending;

                        return [
                            'type' => $post->type === 'question' ? 'question' : 'project',
                            'data' => $data,
                        ];
                    }

                    if ($row->content_type === 'comment') {
                        $comment = $comments->get((int) $contentId);
                        if (!$comment) {
                            return null;
                        }
                        $author = $comment->user;
                        $post = $contextPosts->get($comment->post_slug);
                        $postUrl = $post
                            ? ($post->type === 'question' ? route('questions.show', $comment->post_slug) : route('project', $comment->post_slug))
                            : ($comment->post_slug ? app(ModerationService::class)->resolvePostUrl($comment->post_slug) : $contentUrl);

                        return [
                            'type' => 'comment',
                            'data' => [
                                'id' => $comment->id,
                                'text' => $comment->body,
                                'section' => $comment->section,
                                'time' => $comment->created_at?->diffForHumans() ?? '',
                                'author' => [
                                    'name' => $author?->name ?? __('ui.project.anonymous'),
                                    'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                                    'role' => $author?->role ?? 'user',
                                    'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                ],
                                'post_slug' => $comment->post_slug,
                                'post_title' => $post?->title ?? $comment->post_slug,
                                'post_url' => $postUrl,
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
                                'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                                'is_hidden' => (bool) ($comment->is_hidden ?? false),
                            ],
                        ];
                    }

                    if ($row->content_type === 'review') {
                        $review = $reviews->get((int) $contentId);
                        if (!$review) {
                            return null;
                        }
                        $author = $review->user;
                        $post = $contextPosts->get($review->post_slug);
                        $postUrl = $post
                            ? ($post->type === 'question' ? route('questions.show', $review->post_slug) : route('project', $review->post_slug))
                            : ($review->post_slug ? app(ModerationService::class)->resolvePostUrl($review->post_slug) : $contentUrl);

                        return [
                            'type' => 'review',
                            'data' => [
                                'id' => $review->id,
                                'improve' => $review->improve,
                                'why' => $review->why,
                                'how' => $review->how,
                                'time' => $review->created_at?->diffForHumans() ?? '',
                                'author' => [
                                    'name' => $author?->name ?? __('ui.project.anonymous'),
                                    'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                                    'role' => $author?->role ?? 'user',
                                    'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                ],
                                'post_slug' => $review->post_slug,
                                'post_title' => $post?->title ?? $review->post_slug,
                                'post_url' => $postUrl,
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
                                'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
                                'is_hidden' => (bool) ($review->is_hidden ?? false),
                            ],
                        ];
                    }

                    return null;
                })
                ->filter()
                ->values();

            $moderationFeed->setCollection($moderationItems);
        }

        $moderationLogs = collect();
        if (safeHasTable('moderation_logs')) {
            $moderationLogs = ModerationLog::query()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('moderator_name', 'like', $like)
                            ->orWhere('moderator_role', 'like', $like)
                            ->orWhere('action', 'like', $like)
                            ->orWhere('content_type', 'like', $like)
                            ->orWhere('content_id', 'like', $like)
                            ->orWhere('content_url', 'like', $like)
                            ->orWhere('notes', 'like', $like)
                            ->orWhere('ip_address', 'like', $like)
                            ->orWhere('location', 'like', $like);
                    });
                })
                ->latest()
                ->paginate($perPage, ['*'], 'moderation_log_page');
        }

        $supportTickets = collect();
        if (safeHasTable('support_tickets')) {
            $supportTickets = SupportTicket::query()
                ->with(['user', 'respondedBy'])
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery
                            ->where('subject', 'like', $like)
                            ->orWhere('body', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->orderByRaw("case status when 'open' then 0 when 'waiting' then 1 when 'answered' then 1 when 'closed' then 2 else 3 end")
                ->orderByDesc('updated_at')
                ->paginate($perPage, ['*'], 'support_page');
        }

        $topbarPromos = safeHasTable('topbar_promos')
            ? TopbarPromo::query()->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('admin.index', [
            'users' => $users,
            'comments' => $comments,
            'reviews' => $reviews,
            'reported_posts' => $reportedPosts,
            'media_reports' => $mediaReports,
            'moderation_feed' => $moderationFeed,
            'moderation_logs' => $moderationLogs,
            'moderation_sort' => $moderationSort,
            'support_tickets' => $supportTickets,
            'topbar_promos' => $topbarPromos,
            'admin_search' => $search,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    })->name('admin');

    Route::post('/admin/support-tickets/{ticket}/respond', [AdminSupportController::class, 'respond'])
        ->name('admin.support-tickets.respond');

    Route::post('/admin/users/{user}/ban', [AdminUserController::class, 'toggleBan'])
        ->name('admin.users.ban');

    Route::post('/admin/moderation/posts/{post}/queue', [ModerationController::class, 'queuePost'])
        ->name('moderation.posts.queue');

    Route::post('/admin/moderation/posts/{post}/hide', [ModerationController::class, 'hidePost'])
        ->name('moderation.posts.hide');

    Route::post('/admin/moderation/posts/{post}/restore', [ModerationController::class, 'restorePost'])
        ->name('moderation.posts.restore');

    Route::post('/admin/moderation/posts/{post}/nsfw', [ModerationController::class, 'nsfwPost'])
        ->name('moderation.posts.nsfw');

    Route::post('/admin/moderation/comments/{comment}/queue', [ModerationController::class, 'queueComment'])
        ->name('moderation.comments.queue');

    Route::post('/admin/moderation/comments/{comment}/hide', [ModerationController::class, 'hideComment'])
        ->name('moderation.comments.hide');

    Route::post('/admin/moderation/comments/{comment}/restore', [ModerationController::class, 'restoreComment'])
        ->name('moderation.comments.restore');

    Route::post('/admin/moderation/reviews/{review}/queue', [ModerationController::class, 'queueReview'])
        ->name('moderation.reviews.queue');

    Route::post('/admin/moderation/reviews/{review}/hide', [ModerationController::class, 'hideReview'])
        ->name('moderation.reviews.hide');

    Route::post('/admin/moderation/reviews/{review}/restore', [ModerationController::class, 'restoreReview'])
        ->name('moderation.reviews.restore');

});

