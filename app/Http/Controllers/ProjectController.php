<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\CollaborationApplication;
use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\CollaborationService;
use App\Services\FeedService;
use App\Services\MarkdownService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private UserPayloadService $payloadService,
        private VisibilityService $visibility,
        private MarkdownService $markdown,
        private TextModerationService $textModeration,
        private ModerationService $moderationService
    ) {}

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $dbPost = Post::with([
            'user',
            'editedBy',
            'attachments',
            'updates' => fn ($query) => $query->with('user')->whereHas('user', fn ($q) => $q->where('is_banned', false))
                ->when(! $viewer?->hasRole('moderator'), fn ($q) => $q->where(function ($q) use ($viewer): void {
                    $q->where('is_hidden', false);
                    if ($viewer) {
                        $q->orWhere('user_id', $viewer->id);
                    }
                }))->latest(),
            'members' => fn ($query) => $query->where('status', 'active')->with('user'),
            'collaborationRequests' => fn ($query) => $query
                ->where('status', 'open')
                ->where(fn ($dates) => $dates->whereNull('expires_at')->orWhere('expires_at', '>', now())),
        ])->where('slug', $slug)->where('type', 'post')->firstOrFail();
        abort_unless(Gate::allows('view', $dbPost), 404);
        $partnerProjects = CollaborationApplication::query()
            ->with(['applicantPost.user', 'collaborationRequest.post.user'])
            ->where('status', 'accepted')
            ->whereNotNull('applicant_post_id')
            ->where(function ($query) use ($dbPost): void {
                $query
                    ->where('applicant_post_id', $dbPost->id)
                    ->orWhereHas('collaborationRequest', fn ($requestQuery) => $requestQuery->where('post_id', $dbPost->id));
            })
            ->get()
            ->map(fn (CollaborationApplication $application) => $application->applicant_post_id === $dbPost->id
                ? $application->collaborationRequest?->post
                : $application->applicantPost)
            ->filter(fn (?Post $partner) => $partner
                && $partner->id !== $dbPost->id
                && $partner->visibility === 'public'
                && ! $partner->is_hidden
                && $partner->moderation_status === 'approved'
                && ! $partner->user?->is_banned)
            ->unique('id')
            ->values();
        $stats = FeedService::preparePostStats([$dbPost], $viewer);
        $project = FeedService::mapPostToProjectWithStats($dbPost, $stats);
        $projectMarkdown = (string) ($project['body_markdown'] ?? '');
        if (trim($projectMarkdown) !== '') {
            $project['body_html'] = $this->markdown->render($projectMarkdown);
        }

        $commentPageSize = 15;
        $commentRows = [];
        $commentTotal = 0;
        $reviewRows = [];
        $commentCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
        $this->visibility->applyToQuery($commentCountQuery, 'post_comments', $viewer);
        $commentTotal = $commentCountQuery->count();
        if ($commentTotal > 0) {
            $parentRowsQuery = PostComment::with('user')
                ->where('post_slug', $slug)
                ->whereNull('parent_id');
            $this->visibility->applyToQuery($parentRowsQuery, 'post_comments', $viewer);
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
                        $this->visibility->applyToQuery($query, 'post_comments', $viewer);
                    })
                    ->orderBy('created_at')
                    ->get();

            $replyMap = $replyRows
                ->map(function (PostComment $reply) {
                    $author = $reply->user;

                    return [
                        'id' => $reply->id,
                        'author' => [
                            'id' => $author?->id,
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $reply->created_at?->diffForHumans() ?? __('ui.project.comment_just_now'),
                        'text' => $reply->body,
                        'useful' => $reply->vote_score ?? 0,
                        'created_at' => $reply->created_at ? $reply->created_at->getTimestamp() * 1000 : null,
                        'parent_id' => $reply->parent_id,
                        'is_hidden' => (bool) ($reply->is_hidden ?? false),
                        'moderation_status' => (string) ($reply->moderation_status ?? 'approved'),
                    ];
                })
                ->groupBy('parent_id')
                ->map(fn ($group) => $group->values()->all());

            $commentRows = $parentRows
                ->map(function (PostComment $comment) use ($replyMap) {
                    $author = $comment->user;

                    return [
                        'id' => $comment->id,
                        'user_id' => $author?->id,
                        'author' => $author?->name ?? __('ui.project.anonymous'),
                        'author_slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                        'time' => $comment->created_at?->diffForHumans() ?? __('ui.project.comment_just_now'),
                        'section' => $comment->section ?? __('ui.project.comment_section_general'),
                        'text' => $comment->body,
                        'useful' => $comment->vote_score ?? 0,
                        'role' => $author?->role ?? 'user',
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'replies' => $replyMap[$comment->id] ?? [],
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                })
                ->toArray();
        }

        $reviewQuery = PostReview::with('user')
            ->where('post_slug', $slug);
        $this->visibility->applyToQuery($reviewQuery, 'post_reviews', $viewer);
        $reviewRows = $reviewQuery
            ->latest()
            ->get()
            ->map(function (PostReview $review) {
                $author = $review->user;

                return [
                    'id' => $review->id,
                    'author' => [
                        'id' => $author?->id,
                        'name' => $author?->name ?? __('ui.project.anonymous'),
                        'role' => $author?->role ?? 'user',
                        'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                        'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        'note' => $author?->role === 'maker' ? __('ui.project.reviewer_default_note') : null,
                    ],
                    'time' => $review->created_at?->diffForHumans() ?? __('ui.project.comment_just_now'),
                    'improve' => $review->improve,
                    'why' => $review->why,
                    'how' => $review->how,
                    'useful' => $review->vote_score ?? 0,
                    'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
                    'is_hidden' => (bool) ($review->is_hidden ?? false),
                    'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
                ];
            })
            ->toArray();

        $project['comments'] = array_values($commentRows);
        $project['comments_total'] = $commentTotal;
        $project['comments_offset'] = count($commentRows);
        $project['reviews'] = array_values($reviewRows);
        $projectTags = collect($dbPost->tags ?? [])->map(fn ($tag) => Str::slug((string) $tag))->filter();
        $relatedPosts = Post::with(['user', 'editedBy'])
            ->where('type', 'post')
            ->whereKeyNot($dbPost->id)
            ->where('visibility', 'public')
            ->where('is_hidden', false)
            ->where('moderation_status', 'approved')
            ->whereHas('user', fn ($query) => $query->where('is_banned', false))
            ->latest()
            ->limit(24)
            ->get()
            ->sortByDesc(function (Post $candidate) use ($projectTags): int {
                $candidateTags = collect($candidate->tags ?? [])->map(fn ($tag) => Str::slug((string) $tag));

                return $projectTags->intersect($candidateTags)->count();
            })
            ->take(3)
            ->values();
        $relatedStats = FeedService::preparePostStats($relatedPosts, $viewer);
        $relatedProjects = $relatedPosts
            ->map(fn (Post $post) => FeedService::mapPostToProjectWithStats($post, $relatedStats))
            ->all();
        $currentUser = $this->payloadService->currentUserPayload();

        return view('project', [
            'project' => $project,
            'project_model' => $dbPost,
            'project_members' => $dbPost?->members ?? collect(),
            'project_invitation' => $dbPost && $viewer
                ? $dbPost->members()->where('user_id', $viewer->id)->where('status', 'invited')->first()
                : null,
            'project_collaboration_requests' => $dbPost?->collaborationRequests ?? collect(),
            'project_partners' => $partnerProjects,
            'related_projects' => $relatedProjects,
            'collaboration_roles' => app(CollaborationService::class)->roleOptions(),
            'current_user' => $currentUser,
        ]);
    }

    public function commentsChunk(Request $request, string $slug)
    {
        $post = Post::with('user')->where('slug', $slug)->where('type', 'post')->firstOrFail();
        abort_unless(Gate::allows('view', $post), 404);
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = max(1, min(30, (int) $request->input('limit', 15)));
        $query = $post->comments()->with('user')->whereNull('parent_id');
        $this->visibility->applyToQuery($query, 'post_comments', $request->user());
        $total = (clone $query)->count();
        $comments = $query->latest()->orderByDesc('id')->skip($offset)->take($limit)->get();
        $repliesQuery = $post->comments()->with('user')->whereIn('parent_id', $comments->pluck('id'));
        $this->visibility->applyToQuery($repliesQuery, 'post_comments', $request->user());
        $replies = $repliesQuery->oldest()->get()->groupBy('parent_id');
        $map = static fn (PostComment $comment) => [
            'id' => $comment->id,
            'user_id' => $comment->user_id,
            'author' => $comment->user->name,
            'author_slug' => $comment->user->slug,
            'avatar' => $comment->user->avatar,
            'role' => $comment->user->roleKey(),
            'time' => $comment->created_at->diffForHumans(),
            'created_at' => $comment->created_at->getTimestamp() * 1000,
            'text' => $comment->body,
            'section' => $comment->section,
            'useful' => $comment->vote_score ?? 0,
            'is_hidden' => $comment->is_hidden,
            'moderation_status' => $comment->moderation_status,
        ];
        $items = $comments->map(function (PostComment $row) use ($map, $replies, $post) {
            $comment = $map($row);
            $comment['replies'] = ($replies[$row->id] ?? collect())->map(function ($reply) use ($map) {
                $data = $map($reply);
                $data['author'] = ['id' => $reply->user_id, 'name' => $reply->user->name,
                    'slug' => $reply->user->slug, 'role' => $reply->user->roleKey(), 'avatar' => $reply->user->avatar];

                return $data;
            })->all();

            return view('partials.project-comment', ['comment' => $comment,
                'roleKeys' => config('roles.order'), 'postAuthorSlug' => $post->user->slug,
                'current_user' => $this->payloadService->currentUserPayload(),
            ])->render();
        });

        return response()->json(['items' => $items, 'next_offset' => $offset + $items->count(), 'total' => $total]);
    }

    public function storeComment(StoreCommentRequest $request, string $slug)
    {
        $data = $request->validated();

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('ui.errors.unauthorized')], 401);
        }

        $post = Post::with('user')->where('slug', $slug)->first();
        if (! $post) {
            return response()->json(['message' => __('ui.errors.post_not_found')], 404);
        }
        if ($user->cannot('view', $post)) {
            return response()->json(['message' => __('ui.errors.post_not_found')], 404);
        }

        $parent = null;
        $parentId = $data['parent_id'] ?? null;
        $replyToId = null;
        if ($parentId) {
            $parentQuery = PostComment::with('user')->where('id', $parentId)->where('post_slug', $slug);
            $this->visibility->applyToQuery($parentQuery, 'post_comments', $user);
            $parent = $parentQuery->first();
            if (! $parent) {
                return response()->json(['message' => __('ui.errors.invalid_parent_comment')], 422);
            }
            $replyToId = $parent->id;
            $parentId = $parent->parent_id ?: $parent->id;
        }

        $body = (string) ($data['body'] ?? '');
        $section = $data['section'] ?? null;
        $textModerationResult = [
            'flagged' => false,
            'summary' => '',
            'details' => [],
        ];
        if (! $user->hasRole('moderator')) {
            $textModerationResult = $this->textModeration->analyze($body, [
                'type' => 'comment',
            ]);
        }
        $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

        $commentPayload = [
            'post_id' => $post->id,
            'post_slug' => $slug,
            'user_id' => $user->id,
            'body' => $body,
            'section' => $section,
            'useful' => 0,
            'parent_id' => $parentId ?: null,
            'reply_to_id' => $replyToId,
        ];

        if ($textModerationFlagged) {
            $commentPayload['moderation_status'] = 'pending';
            $commentPayload['is_hidden'] = true;
            $commentPayload['hidden_at'] = now();
            $commentPayload['hidden_by'] = null;
        }

        $comment = PostComment::create($commentPayload);

        if (! $textModerationFlagged) {
            $recipient = $parent?->user ?? $post?->user;
            if ($recipient && $recipient->id !== $user->id) {
                $recipient->sendPreferredNotification(
                    'notify_comments',
                    __('ui.notifications.type_comment'),
                    __('ui.notifications.comment_added', [
                        'user' => $user->name,
                        'title' => $post->title,
                    ]),
                    $this->moderationService->resolvePostUrl($slug).'#comment-'.$comment->id,
                );
            }
        }

        if ($textModerationFlagged) {
            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            $detailText = $summary !== '' ? $summary : __('ui.moderation.text_flagged_detail');
            ContentReport::create([
                'user_id' => $user->id,
                'content_type' => 'comment',
                'content_id' => (string) $comment->id,
                'content_url' => $this->moderationService->resolvePostUrl($slug).'#comment-'.$comment->id,
                'reason' => 'admin_flag',
                'details' => $detailText,
            ]);
        }

        return response()->json([
            'id' => $comment->id,
            'author' => $user->name,
            'author_slug' => $user->slug ?? Str::slug($user->name ?? ''),
            'role' => $user->roleKey(),
            'role_label' => __('ui.roles.'.($user->role ?? 'user')),
            'time' => __('ui.project.comment_just_now'),
            'text' => $comment->body,
            'section' => $comment->section,
            'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
            'parent_id' => $comment->parent_id,
            'reply_to_id' => $comment->reply_to_id,
            'is_hidden' => (bool) ($comment->is_hidden ?? false),
            'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
        ]);
    }

    public function storeReview(StoreReviewRequest $request, string $slug)
    {
        $data = $request->validated();

        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('ui.errors.unauthorized')], 401);
        }
        $post = Post::with('user')->where('slug', $slug)->where('type', 'post')->first();
        if (! $post) {
            return response()->json(['message' => __('ui.errors.post_not_found')], 404);
        }
        if ($user->cannot('view', $post)) {
            return response()->json(['message' => __('ui.errors.post_not_found')], 404);
        }

        $improve = (string) ($data['improve'] ?? '');
        $why = (string) ($data['why'] ?? '');
        $how = (string) ($data['how'] ?? '');
        $reviewText = trim(implode("\n\n", array_filter([$improve, $why, $how], static fn ($value) => trim((string) $value) !== '')));
        $textModerationResult = [
            'flagged' => false,
            'summary' => '',
            'details' => [],
        ];
        if (! $user->hasRole('moderator')) {
            $textModerationResult = $this->textModeration->analyze($reviewText, [
                'type' => 'review',
            ]);
        }
        $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

        $reviewPayload = [
            'post_id' => $post->id,
            'post_slug' => $slug,
            'user_id' => $user->id,
            'improve' => $improve,
            'why' => $why,
            'how' => $how,
        ];

        if ($textModerationFlagged) {
            $reviewPayload['moderation_status'] = 'pending';
            $reviewPayload['is_hidden'] = true;
            $reviewPayload['hidden_at'] = now();
            $reviewPayload['hidden_by'] = null;
        }

        $review = PostReview::create($reviewPayload);

        if (! $textModerationFlagged && $post?->user && $post->user_id !== $user->id) {
            $post->user->sendPreferredNotification(
                'notify_reviews',
                __('ui.notifications.type_review'),
                __('ui.notifications.review_added', [
                    'user' => $user->name,
                    'title' => $post->title,
                ]),
                route('project', $post->slug).'#review-'.$review->id,
            );
        }

        if ($textModerationFlagged) {
            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            $detailText = $summary !== '' ? $summary : __('ui.moderation.text_flagged_detail');
            ContentReport::create([
                'user_id' => $user->id,
                'content_type' => 'review',
                'content_id' => (string) $review->id,
                'content_url' => $this->moderationService->resolvePostUrl($slug).'#review-'.$review->id,
                'reason' => 'admin_flag',
                'details' => $detailText,
            ]);
        }

        return response()->json([
            'id' => $review->id,
            'author' => $user->name,
            'role' => $user->role ?? 'user',
            'role_label' => __('ui.roles.'.($user->role ?? 'user')),
            'time' => __('ui.project.comment_just_now'),
            'improve' => $review->improve,
            'why' => $review->why,
            'how' => $review->how,
            'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
            'is_hidden' => (bool) ($review->is_hidden ?? false),
            'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
        ]);
    }
}
