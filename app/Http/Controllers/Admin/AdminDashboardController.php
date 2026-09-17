<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\ContentReport;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\SupportTicket;
use App\Models\TopbarPromo;
use App\Models\User;
use App\Services\FeedService;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $perPage = 20;
        $search = trim((string) $request->query('q', ''));
        $like = $search !== '' ? '%'.$search.'%' : null;
        $isAdmin = $request->user()?->isAdmin() ?? false;
        $sections = ['overview', 'content', 'collaborations', 'moderation', 'support', 'media', 'comments', 'reviews', 'log'];
        if ($isAdmin) {
            $sections = array_merge($sections, ['users', 'analytics', 'promos', 'system']);
        }
        $section = (string) $request->query('tab', 'overview');
        $section = in_array($section, $sections, true) ? $section : 'overview';
        $moderationSort = (string) $request->query('sort', 'reporters');
        $moderationSort = in_array($moderationSort, ['reporters', 'recent'], true) ? $moderationSort : 'reporters';

        $contentType = in_array($request->query('type'), ['post', 'question'], true)
            ? (string) $request->query('type')
            : '';
        $contentVisibility = in_array($request->query('visibility'), ['public', 'unlisted', 'draft'], true)
            ? (string) $request->query('visibility')
            : '';
        $contentModeration = in_array($request->query('moderation'), ['approved', 'pending', 'hidden'], true)
            ? (string) $request->query('moderation')
            : '';
        $content = collect();
        if ($section === 'content') {
            $content = Post::query()
                ->with('user:id,name,slug,role,is_banned,avatar')
                ->withCount(['comments', 'reviews', 'collaborationRequests'])
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('title', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhereHas('user', fn ($userQuery) => $userQuery
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like));
                    });
                })
                ->when($contentType !== '', fn ($query) => $query->where('type', $contentType))
                ->when($contentVisibility !== '', fn ($query) => $query->where('visibility', $contentVisibility))
                ->when($contentModeration !== '', fn ($query) => $query->where('moderation_status', $contentModeration))
                ->latest()
                ->paginate($perPage, ['*'], 'content_page');
        }

        $collaborationStatus = in_array($request->query('status'), ['open', 'filled', 'closed'], true)
            ? (string) $request->query('status')
            : '';
        $collaborations = collect();
        $selectedCollaboration = null;
        if ($section === 'collaborations') {
            $collaborations = CollaborationRequest::query()
                ->with(['user:id,name,slug,role,is_banned,avatar', 'post:id,slug,title,type'])
                ->withCount(['applications', 'comments'])
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhereHas('post', fn ($postQuery) => $postQuery->where('title', 'like', $like))
                            ->orWhereHas('user', fn ($userQuery) => $userQuery
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like));
                    });
                })
                ->when($collaborationStatus !== '', fn ($query) => $query->where('status', $collaborationStatus))
                ->latest()
                ->paginate($perPage, ['*'], 'collaborations_page');

            $selectedId = filter_var($request->query('request'), FILTER_VALIDATE_INT);
            if ($selectedId) {
                $selectedCollaboration = CollaborationRequest::query()
                    ->with([
                        'user:id,name,slug,role,is_banned',
                        'post:id,slug,title,type',
                        'applications' => fn ($query) => $query->with(['user:id,name,slug,role,is_banned', 'applicantPost:id,slug,title'])->latest(),
                        'comments' => fn ($query) => $query->with('user:id,name,slug,role,is_banned')->latest(),
                    ])
                    ->find($selectedId);
            }
        }

        $users = collect();
        $selectedUser = null;
        $selectedUserReports = collect();
        $selectedUserModeration = collect();
        $selectedUserAudit = collect();
        if ($section === 'users') {
            $users = User::query()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    });
                })
                ->withCount(['posts', 'postComments', 'collaborationApplications'])
                ->latest()
                ->paginate($perPage, ['*'], 'users_page');

            $selectedUserId = filter_var($request->query('user'), FILTER_VALIDATE_INT);
            if ($selectedUserId) {
                $selectedUser = User::query()
                    ->withCount([
                        'posts',
                        'postComments',
                        'postReviews',
                        'collaborationRequests',
                        'collaborationApplications',
                        'collaborationComments',
                        'supportTickets',
                        'uploadAssets',
                    ])
                    ->find($selectedUserId);

                if ($selectedUser) {
                    $selectedUser->load([
                        'posts' => fn ($query) => $query->latest()->limit(8),
                        'postComments' => fn ($query) => $query->latest()->limit(8),
                        'postReviews' => fn ($query) => $query->latest()->limit(8),
                        'collaborationRequests' => fn ($query) => $query->latest()->limit(8),
                        'collaborationApplications' => fn ($query) => $query->with('collaborationRequest:id,title')->latest()->limit(8),
                        'supportTickets' => fn ($query) => $query->latest()->limit(8),
                        'badges' => fn ($query) => $query->latest()->limit(12),
                    ]);
                    $selectedUserReports = ContentReport::query()
                        ->where('user_id', $selectedUser->id)
                        ->latest()
                        ->limit(12)
                        ->get();

                    $allUserPosts = $selectedUser->posts()->get(['id', 'slug']);
                    $postIds = $allUserPosts->pluck('id')->map(fn ($id) => (string) $id);
                    $postSlugs = $allUserPosts->pluck('slug')->filter();
                    $commentIds = $selectedUser->postComments()->pluck('id')->map(fn ($id) => (string) $id);
                    $reviewIds = $selectedUser->postReviews()->pluck('id')->map(fn ($id) => (string) $id);
                    $collaborationIds = $selectedUser->collaborationRequests()->pluck('id')->map(fn ($id) => (string) $id);
                    $selectedUserModeration = ModerationLog::query()
                        ->where(function ($query) use ($selectedUser, $postIds, $postSlugs, $commentIds, $reviewIds, $collaborationIds) {
                            $query->where(function ($subQuery) use ($selectedUser) {
                                $subQuery->where('content_type', 'user')->where('content_id', (string) $selectedUser->id);
                            });
                            if ($postIds->isNotEmpty() || $postSlugs->isNotEmpty()) {
                                $query->orWhere(function ($subQuery) use ($postIds, $postSlugs) {
                                    $subQuery->whereIn('content_type', ['post', 'question'])
                                        ->whereIn('content_id', $postIds->merge($postSlugs)->all());
                                });
                            }
                            if ($commentIds->isNotEmpty()) {
                                $query->orWhere(fn ($subQuery) => $subQuery->where('content_type', 'comment')->whereIn('content_id', $commentIds->all()));
                            }
                            if ($reviewIds->isNotEmpty()) {
                                $query->orWhere(fn ($subQuery) => $subQuery->where('content_type', 'review')->whereIn('content_id', $reviewIds->all()));
                            }
                            if ($collaborationIds->isNotEmpty()) {
                                $query->orWhere(fn ($subQuery) => $subQuery->where('content_type', 'collaboration')->whereIn('content_id', $collaborationIds->all()));
                            }
                        })
                        ->latest()
                        ->limit(12)
                        ->get();
                    $selectedUserAudit = AuditLog::query()
                        ->where(function ($query) use ($selectedUser) {
                            $query->where('user_id', $selectedUser->id)
                                ->orWhere(function ($subQuery) use ($selectedUser) {
                                    $subQuery->where('target_type', 'user')->where('target_id', (string) $selectedUser->id);
                                });
                        })
                        ->latest()
                        ->limit(12)
                        ->get();
                }
            }
        }

        $analytics = [];
        if ($section === 'analytics') {
            $analyticsStart = now()->startOfDay()->subDays(29);
            $dailyCounts = fn (string $table) => DB::table($table)
                ->selectRaw('date(created_at) as day, count(*) as aggregate')
                ->where('created_at', '>=', $analyticsStart)
                ->groupByRaw('date(created_at)')
                ->pluck('aggregate', 'day');
            $dailyUsers = $dailyCounts('users');
            $dailyPosts = $dailyCounts('posts');
            $dailyComments = $dailyCounts('post_comments');
            $dailyReports = $dailyCounts('content_reports');
            $activeUserIds = collect()
                ->merge(DB::table('posts')->where('created_at', '>=', $analyticsStart)->pluck('user_id'))
                ->merge(DB::table('post_comments')->where('created_at', '>=', $analyticsStart)->pluck('user_id'))
                ->merge(DB::table('post_reviews')->where('created_at', '>=', $analyticsStart)->pluck('user_id'))
                ->merge(DB::table('content_reports')->where('created_at', '>=', $analyticsStart)->pluck('user_id'))
                ->merge(DB::table('collaboration_applications')->where('created_at', '>=', $analyticsStart)->pluck('user_id'))
                ->filter()
                ->unique();
            $analyticsDays = collect(range(0, 29))->map(function (int $offset) use ($analyticsStart, $dailyUsers, $dailyPosts, $dailyComments, $dailyReports) {
                $date = $analyticsStart->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'date' => $key,
                    'users' => (int) ($dailyUsers[$key] ?? 0),
                    'content' => (int) ($dailyPosts[$key] ?? 0),
                    'comments' => (int) ($dailyComments[$key] ?? 0),
                    'reports' => (int) ($dailyReports[$key] ?? 0),
                ];
            });
            $analytics = [
                'days' => $analyticsDays,
                'totals' => [
                    'users' => $analyticsDays->sum('users'),
                    'content' => $analyticsDays->sum('content'),
                    'comments' => $analyticsDays->sum('comments'),
                    'reports' => $analyticsDays->sum('reports'),
                ],
                'active_users' => $activeUserIds->count(),
                'open_collaborations' => CollaborationRequest::query()->where('status', 'open')->count(),
            ];
        }

        $systemStatus = [];
        if ($section === 'system') {
            $databaseHealthy = true;
            try {
                DB::select('select 1');
            } catch (\Throwable) {
                $databaseHealthy = false;
            }
            $heartbeat = Cache::get('system:scheduler-heartbeat');
            $heartbeatAt = $heartbeat ? Carbon::parse($heartbeat) : null;
            $systemStatus = [
                'database' => $databaseHealthy,
                'storage' => is_writable(storage_path()) && is_writable(storage_path('app')),
                'public_storage' => is_link(public_path('storage')) || is_dir(public_path('storage')),
                'scheduler' => $heartbeatAt && $heartbeatAt->greaterThan(now()->subMinutes(5)),
                'scheduler_at' => $heartbeatAt,
                'queue' => (string) config('queue.default'),
                'queue_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
                'mail' => (string) config('mail.default'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'moderation_images' => (bool) config('moderation.enabled'),
                'moderation_text' => (bool) config('moderation.text.enabled'),
                'migration_batch' => Schema::hasTable('migrations') ? DB::table('migrations')->max('batch') : null,
            ];
        }

        $comments = collect();
        if ($section === 'comments') {
            $comments = PostComment::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('body', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'comments_page');
        }

        $reviews = collect();
        if ($section === 'reviews') {
            $reviews = PostReview::with('user')
                ->latest()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('improve', 'like', $like)
                            ->orWhere('why', 'like', $like)
                            ->orWhere('how', 'like', $like)
                            ->orWhere('post_slug', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->paginate($perPage, ['*'], 'reviews_page');
        }

        $mediaReports = collect();
        if ($section === 'media') {
            $mediaReports = ContentReport::with('user')
                ->where('content_type', 'content')
                ->where('resolved_status', 'pending')
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('content_url', 'like', $like)
                            ->orWhere('details', 'like', $like);
                    });
                })
                ->latest()
                ->paginate($perPage, ['*'], 'media_page');
        }

        $moderationFeed = collect();
        if ($section === 'moderation') {
            $reportTypes = ['post', 'question', 'comment', 'review', 'collaboration', 'collaboration_comment'];
            $weightSelect = DB::raw('coalesce(sum(weight), 0) as weight_total');
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
                ->where('resolved_status', 'pending')
                ->whereNotNull('content_id')
                ->where('content_id', '<>', '');

            if ($search !== '' && $like !== null) {
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

                $commentMatchIds = PostComment::query()
                    ->where(function ($query) use ($like) {
                        $query
                            ->where('body', 'like', $like)
                            ->orWhere('post_slug', 'like', $like);
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);

                $reviewMatchIds = PostReview::query()
                    ->where(function ($query) use ($like) {
                        $query
                            ->where('improve', 'like', $like)
                            ->orWhere('why', 'like', $like)
                            ->orWhere('how', 'like', $like)
                            ->orWhere('post_slug', 'like', $like);
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);

                $collaborationMatchIds = CollaborationRequest::query()
                    ->where(function ($query) use ($like) {
                        $query->where('title', 'like', $like)
                            ->orWhere('summary', 'like', $like)
                            ->orWhere('role', 'like', $like);
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);
                $collaborationCommentMatchIds = CollaborationComment::query()
                    ->where('body', 'like', $like)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id);

                $postMatchIds = $postMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $postMatchSlugs = $postMatches->pluck('slug')->filter();
                $questionMatchIds = $questionMatches->pluck('id')->map(fn ($id) => (string) $id)->filter();
                $questionMatchSlugs = $questionMatches->pluck('slug')->filter();

                $reportQuery->where(function ($query) use ($like, $postMatchIds, $postMatchSlugs, $questionMatchIds, $questionMatchSlugs, $commentMatchIds, $reviewMatchIds, $collaborationMatchIds, $collaborationCommentMatchIds) {
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
                    if ($collaborationMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($collaborationMatchIds) {
                            $subQuery->where('content_type', 'collaboration')->whereIn('content_id', $collaborationMatchIds->all());
                        });
                    }
                    if ($collaborationCommentMatchIds->isNotEmpty()) {
                        $query->orWhere(function ($subQuery) use ($collaborationCommentMatchIds) {
                            $subQuery->where('content_type', 'collaboration_comment')->whereIn('content_id', $collaborationCommentMatchIds->all());
                        });
                    }
                });
            }

            $reportQuery->groupBy('content_type', 'content_id');

            if ($moderationSort === 'reporters') {
                $reportQuery
                    ->orderByDesc('reporters_count')
                    ->orderByDesc('weight_total')
                    ->orderByDesc('last_report_at');
            } else {
                $reportQuery
                    ->orderByDesc('weight_total')
                    ->orderByDesc('last_report_at');
            }

            $moderationFeed = $reportQuery->paginate($perPage, ['*'], 'moderation_page');
            $reportRows = collect($moderationFeed->items());
            $detailsByKey = collect();
            if ($reportRows->isNotEmpty()) {
                $detailContentIds = $reportRows->pluck('content_id')->filter()->unique()->values();
                if ($detailContentIds->isNotEmpty()) {
                    $detailRows = DB::table('content_reports')
                        ->select('content_type', 'content_id', 'details', 'created_at')
                        ->whereIn('content_type', $reportTypes)
                        ->where('resolved_status', 'pending')
                        ->whereIn('content_id', $detailContentIds->all())
                        ->whereNotNull('details')
                        ->where('details', '<>', '')
                        ->orderByDesc('created_at')
                        ->get();
                    $detailsByKey = $detailRows->groupBy(fn ($row) => $row->content_type.':'.$row->content_id)
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
            if ($numericPostIds->isNotEmpty() || $slugPostIds->isNotEmpty()) {
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

            $reportedComments = $commentIds->isNotEmpty()
                ? PostComment::with('user')->whereIn('id', $commentIds->all())->get()->keyBy('id')
                : collect();
            $reportedReviews = $reviewIds->isNotEmpty()
                ? PostReview::with('user')->whereIn('id', $reviewIds->all())->get()->keyBy('id')
                : collect();
            $collaborationIds = $reportRows
                ->where('content_type', 'collaboration')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();
            $collaborationCommentIds = $reportRows
                ->where('content_type', 'collaboration_comment')
                ->pluck('content_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->values();
            $reportedCollaborations = $collaborationIds->isNotEmpty()
                ? CollaborationRequest::with(['user', 'post'])->whereIn('id', $collaborationIds->all())->get()->keyBy('id')
                : collect();
            $reportedCollaborationComments = $collaborationCommentIds->isNotEmpty()
                ? CollaborationComment::with(['user', 'collaborationRequest'])->whereIn('id', $collaborationCommentIds->all())->get()->keyBy('id')
                : collect();

            $contextPostSlugs = collect()
                ->merge($reportedComments->pluck('post_slug'))
                ->merge($reportedReviews->pluck('post_slug'))
                ->filter()
                ->unique()
                ->values();
            $contextPosts = $contextPostSlugs->isNotEmpty()
                ? Post::query()
                    ->whereIn('slug', $contextPostSlugs->all())
                    ->get(['id', 'slug', 'title', 'type', 'user_id'])
                    ->keyBy('slug')
                : collect();

            $moderationItems = $reportRows
                ->map(function ($row) use ($postsById, $postsBySlug, $stats, $reportedComments, $reportedReviews, $reportedCollaborations, $reportedCollaborationComments, $contextPosts, $detailsByKey) {
                    $contentId = (string) $row->content_id;
                    $reportCount = (int) ($row->report_count ?? 0);
                    $reportersCount = (int) ($row->reporters_count ?? 0);
                    $reportPoints = round((float) ($row->weight_total ?? 0), 1);
                    $lastReportedAt = $row->last_report_at ?? null;
                    $contentUrl = $row->content_url ?? null;
                    $detailKey = $row->content_type.':'.$contentId;
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
                        if (! $post) {
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
                        $comment = $reportedComments->get((int) $contentId);
                        if (! $comment) {
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
                        $review = $reportedReviews->get((int) $contentId);
                        if (! $review) {
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

                    if ($row->content_type === 'collaboration') {
                        $collaboration = $reportedCollaborations->get((int) $contentId);
                        if (! $collaboration) {
                            return null;
                        }

                        return [
                            'type' => 'collaboration',
                            'data' => [
                                'id' => $collaboration->id,
                                'title' => $collaboration->title,
                                'text' => $collaboration->summary,
                                'status' => $collaboration->status,
                                'author' => $collaboration->user,
                                'url' => route('collaboration.show', $collaboration),
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
                            ],
                        ];
                    }

                    if ($row->content_type === 'collaboration_comment') {
                        $comment = $reportedCollaborationComments->get((int) $contentId);
                        if (! $comment || ! $comment->collaborationRequest) {
                            return null;
                        }

                        return [
                            'type' => 'collaboration_comment',
                            'data' => [
                                'id' => $comment->id,
                                'request_id' => $comment->collaboration_request_id,
                                'title' => $comment->collaborationRequest->title,
                                'text' => $comment->body,
                                'author' => $comment->user,
                                'url' => route('collaboration.show', $comment->collaborationRequest).'#comment-'.$comment->id,
                                'report_count' => $reportCount,
                                'report_points' => $reportPoints,
                                'reporters_count' => $reportersCount,
                                'last_report_at' => $lastReportedAt,
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
        if ($section === 'log') {
            $moderationLogs = ModerationLog::query()
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('moderator_name', 'like', $like)
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
        if ($section === 'support') {
            $supportTickets = SupportTicket::query()
                ->with(['user', 'respondedBy'])
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('subject', 'like', $like)
                            ->orWhere('body', 'like', $like)
                            ->orWhereHas('user', function ($userQuery) use ($like) {
                                $userQuery->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('slug', 'like', $like);
                            });
                    });
                })
                ->orderByRaw("case status when 'open' then 0 when 'waiting' then 1 when 'answered' then 1 when 'closed' then 2 else 3 end")
                ->orderByDesc('updated_at')
                ->paginate($perPage, ['*'], 'support_page');
        }

        $topbarPromos = $section === 'promos'
            ? TopbarPromo::query()->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $adminOverview = [];
        $overviewLogs = collect();
        if ($section === 'overview') {
            $adminOverview = [
                'users' => User::query()->count(),
                'new_users' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'content' => Post::query()->count(),
                'pending_reports' => ContentReport::query()->where('resolved_status', 'pending')->count(),
                'open_tickets' => SupportTicket::query()->where('status', '<>', 'closed')->count(),
                'flagged_media' => ContentReport::query()
                    ->where('content_type', 'content')
                    ->where('resolved_status', 'pending')
                    ->count(),
                'active_promos' => TopbarPromo::query()->where('is_active', true)->count(),
            ];
            $overviewLogs = ModerationLog::query()->latest()->limit(6)->get();
        }

        return view('admin.index', [
            'content_items' => $content,
            'content_type' => $contentType,
            'content_visibility' => $contentVisibility,
            'content_moderation' => $contentModeration,
            'collaborations' => $collaborations,
            'selected_collaboration' => $selectedCollaboration,
            'collaboration_status' => $collaborationStatus,
            'users' => $users,
            'selected_user' => $selectedUser,
            'selected_user_reports' => $selectedUserReports,
            'selected_user_moderation' => $selectedUserModeration,
            'selected_user_audit' => $selectedUserAudit,
            'admin_analytics' => $analytics,
            'system_status' => $systemStatus,
            'comments' => $comments,
            'reviews' => $reviews,
            'media_reports' => $mediaReports,
            'moderation_feed' => $moderationFeed,
            'moderation_logs' => $moderationLogs,
            'moderation_sort' => $moderationSort,
            'support_tickets' => $supportTickets,
            'topbar_promos' => $topbarPromos,
            'admin_overview' => $adminOverview,
            'overview_logs' => $overviewLogs,
            'admin_search' => $search,
            'admin_section' => $section,
        ]);
    }
}
