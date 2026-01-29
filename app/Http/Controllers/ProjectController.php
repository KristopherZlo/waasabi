<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\DemoContentService;
use App\Services\FeedService;
use App\Services\MarkdownService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        private DemoContentService $demoContent,
        private UserPayloadService $payloadService,
        private VisibilityService $visibility,
        private MarkdownService $markdown,
        private TextModerationService $textModeration,
        private ModerationService $moderationService
    ) {
    }

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $project = collect($this->demoContent->projects())->firstWhere('slug', $slug);
        if (safeHasTable('posts')) {
            $dbPost = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'post')->first();
            if ($dbPost) {
                if (safeHasColumn('users', 'is_banned') && $dbPost->user?->is_banned) {
                    abort(404);
                }
                if (!$this->visibility->canViewHidden($viewer, $dbPost->user_id)) {
                    $isHidden = (bool) ($dbPost->is_hidden ?? false);
                    $status = (string) ($dbPost->moderation_status ?? 'approved');
                    if ($isHidden || $status !== 'approved') {
                        abort(404);
                    }
                }
                $stats = FeedService::preparePostStats([$dbPost], $viewer);
                $project = FeedService::mapPostToProjectWithStats($dbPost, $stats);
            }
        }

        abort_unless($project, 404);
        $projectMarkdown = (string) ($project['body_markdown'] ?? '');
        if (trim($projectMarkdown) !== '') {
            $project['body_html'] = $this->markdown->render($projectMarkdown);
        }

        $commentPageSize = 15;
        $commentRows = [];
        $commentTotal = 0;
        $reviewRows = [];
        if (safeHasTable('post_comments')) {
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
                                'name' => $author?->name ?? 'Anonymous',
                                'role' => $author?->role ?? 'user',
                                'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                            ],
                            'time' => $reply->created_at?->diffForHumans() ?? 'just now',
                            'text' => $reply->body,
                            'useful' => $reply->useful ?? 0,
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
                            'author' => $author?->name ?? 'Anonymous',
                            'author_slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'time' => $comment->created_at?->diffForHumans() ?? 'just now',
                            'section' => $comment->section ?? 'General',
                            'text' => $comment->body,
                            'useful' => $comment->useful ?? 0,
                            'role' => $author?->role ?? 'user',
                            'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                            'replies' => $replyMap[$comment->id] ?? [],
                            'is_hidden' => (bool) ($comment->is_hidden ?? false),
                            'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                        ];
                    })
                    ->toArray();
            }
        }

        if ($commentTotal === 0) {
            $demoComments = $project['comments'] ?? [];
            $commentTotal = count($demoComments);
            $commentRows = array_slice($demoComments, 0, $commentPageSize);
        }

        if (safeHasTable('post_reviews')) {
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
                            'name' => $author?->name ?? 'Anonymous',
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                            'note' => $author?->role === 'maker' ? 'Maker review' : null,
                        ],
                        'time' => $review->created_at?->diffForHumans() ?? 'just now',
                        'improve' => $review->improve,
                        'why' => $review->why,
                        'how' => $review->how,
                        'useful' => 0,
                        'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($review->is_hidden ?? false),
                        'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
                    ];
                })
                ->toArray();
        }

        $project['comments'] = array_values($commentRows);
        $project['comments_total'] = $commentTotal;
        $project['comments_offset'] = count($commentRows);
        $project['reviews'] = array_values(array_merge($project['reviews'] ?? [], $reviewRows));
        $currentUser = $this->payloadService->currentUserPayload();

        return view('project', ['project' => $project, 'current_user' => $currentUser]);
    }

    public function storeComment(StoreCommentRequest $request, string $slug)
    {
        if (!safeHasTable('post_comments')) {
            return response()->json(['message' => 'Comments table missing'], 503);
        }
        if (!$this->postSlugExists($slug)) {
            return response()->json(['message' => 'Post not found'], 404);
        }
        $data = $request->validated();

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (safeHasTable('posts')) {
            $post = Post::where('slug', $slug)->where('type', 'post')->first();
            if ($post && !$this->visibility->canViewHidden($user, $post->user_id)) {
                $isHidden = (bool) ($post->is_hidden ?? false);
                $status = (string) ($post->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    return response()->json(['message' => 'Post not found'], 404);
                }
            }
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $parent = PostComment::where('id', $parentId)->where('post_slug', $slug)->first();
            if (!$parent) {
                return response()->json(['message' => 'Invalid parent comment'], 422);
            }
        }

        $body = (string) ($data['body'] ?? '');
        $section = $data['section'] ?? null;
        $textModerationResult = [
            'flagged' => false,
            'summary' => '',
            'details' => [],
        ];
        if (!$user->hasRole('moderator')) {
            $textModerationResult = $this->textModeration->analyze($body, [
                'type' => 'comment',
            ]);
        }
        $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

        $commentPayload = [
            'post_slug' => $slug,
            'user_id' => $user->id,
            'body' => $body,
            'section' => $section,
            'useful' => 0,
            'parent_id' => $parentId ?: null,
        ];

        if ($textModerationFlagged) {
            if (safeHasColumn('post_comments', 'moderation_status')) {
                $commentPayload['moderation_status'] = 'pending';
            }
            if (safeHasColumn('post_comments', 'is_hidden')) {
                $commentPayload['is_hidden'] = true;
            }
            if (safeHasColumn('post_comments', 'hidden_at')) {
                $commentPayload['hidden_at'] = now();
            }
            if (safeHasColumn('post_comments', 'hidden_by')) {
                $commentPayload['hidden_by'] = null;
            }
        }

        $comment = PostComment::create($commentPayload);

        if ($textModerationFlagged && safeHasTable('content_reports')) {
            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            $detailText = $summary !== '' ? $summary : 'Text moderation flagged comment.';
            ContentReport::create([
                'user_id' => $user->id,
                'content_type' => 'comment',
                'content_id' => (string) $comment->id,
                'content_url' => $this->moderationService->resolvePostUrl($slug) . '#comment-' . $comment->id,
                'reason' => 'admin_flag',
                'details' => $detailText,
            ]);
        }

        return response()->json([
            'id' => $comment->id,
            'author' => $user->name,
            'author_slug' => $user->slug ?? Str::slug($user->name ?? ''),
            'role' => $user->roleKey(),
            'role_label' => __('ui.roles.' . ($user->role ?? 'user')),
            'time' => __('ui.project.comment_just_now'),
            'text' => $comment->body,
            'section' => $comment->section,
            'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
            'parent_id' => $comment->parent_id,
            'is_hidden' => (bool) ($comment->is_hidden ?? false),
            'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
        ]);
    }

    public function commentsChunk(Request $request, string $slug)
    {
        $viewer = $request->user();
        $limit = max(1, min(30, (int) $request->query('limit', 15)));
        $offset = max(0, (int) $request->query('offset', 0));
        $commentRows = [];
        $commentTotal = 0;
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);

        $project = collect($this->demoContent->projects())->firstWhere('slug', $slug);
        $dbExists = safeHasTable('posts')
            && Post::query()->where('slug', $slug)->where('type', 'post')->exists();
        $postAuthorSlug = '';
        if ($project && isset($project['author'])) {
            $postAuthorSlug = $project['author']['slug'] ?? Str::slug($project['author']['name'] ?? '');
        }
        if ($dbExists) {
            $post = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'post')->first();
            if ($post && !$this->visibility->canViewHidden($viewer, $post->user_id)) {
                $isHidden = (bool) ($post->is_hidden ?? false);
                $status = (string) ($post->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
                }
            }
            $postAuthorSlug = $post?->user?->slug ?? Str::slug($post?->user?->name ?? '') ?? $postAuthorSlug;
        }

        if (!$project && !$dbExists) {
            return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
        }

        if (safeHasTable('post_comments')) {
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
                    ->skip($offset)
                    ->take($limit)
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
                                'name' => $author?->name ?? 'Anonymous',
                                'role' => $author?->role ?? 'user',
                                'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                                'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                            ],
                            'time' => $reply->created_at?->diffForHumans() ?? 'just now',
                            'text' => $reply->body,
                            'useful' => $reply->useful ?? 0,
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
                            'author' => $author?->name ?? 'Anonymous',
                            'author_slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'time' => $comment->created_at?->diffForHumans() ?? 'just now',
                            'section' => $comment->section ?? 'General',
                            'text' => $comment->body,
                            'useful' => $comment->useful ?? 0,
                            'role' => $author?->role ?? 'user',
                            'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                            'replies' => $replyMap[$comment->id] ?? [],
                            'is_hidden' => (bool) ($comment->is_hidden ?? false),
                            'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                        ];
                    })
                    ->toArray();
            }
        }

        if ($commentTotal === 0) {
            $demoComments = $project['comments'] ?? [];
            $commentTotal = count($demoComments);
            $commentRows = array_slice($demoComments, $offset, $limit);
        }

        $items = [];
        foreach (array_values($commentRows) as $index => $comment) {
            $items[] = view('partials.project-comment', [
                'comment' => $comment,
                'commentIndex' => $offset + $index,
                'roleKeys' => $roleKeys,
                'postAuthorSlug' => $postAuthorSlug,
            ])->render();
        }

        $nextOffset = $offset + count($commentRows);

        return response()->json([
            'items' => $items,
            'total' => $commentTotal,
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $commentTotal,
        ]);
    }

    public function storeReview(StoreReviewRequest $request, string $slug)
    {
        if (!$this->postSlugExists($slug)) {
            return response()->json(['message' => 'Post not found'], 404);
        }
        $data = $request->validated();

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$user->hasRole('maker')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if (!safeHasTable('post_reviews')) {
            return response()->json(['message' => 'Reviews table missing'], 503);
        }

        if (safeHasTable('posts')) {
            $post = Post::where('slug', $slug)->where('type', 'post')->first();
            if ($post && !$this->visibility->canViewHidden($user, $post->user_id)) {
                $isHidden = (bool) ($post->is_hidden ?? false);
                $status = (string) ($post->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    return response()->json(['message' => 'Post not found'], 404);
                }
            }
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
        if (!$user->hasRole('moderator')) {
            $textModerationResult = $this->textModeration->analyze($reviewText, [
                'type' => 'review',
            ]);
        }
        $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);

        $reviewPayload = [
            'post_slug' => $slug,
            'user_id' => $user->id,
            'improve' => $improve,
            'why' => $why,
            'how' => $how,
        ];

        if ($textModerationFlagged) {
            if (safeHasColumn('post_reviews', 'moderation_status')) {
                $reviewPayload['moderation_status'] = 'pending';
            }
            if (safeHasColumn('post_reviews', 'is_hidden')) {
                $reviewPayload['is_hidden'] = true;
            }
            if (safeHasColumn('post_reviews', 'hidden_at')) {
                $reviewPayload['hidden_at'] = now();
            }
            if (safeHasColumn('post_reviews', 'hidden_by')) {
                $reviewPayload['hidden_by'] = null;
            }
        }

        $review = PostReview::create($reviewPayload);

        if ($textModerationFlagged && safeHasTable('content_reports')) {
            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            $detailText = $summary !== '' ? $summary : 'Text moderation flagged review.';
            ContentReport::create([
                'user_id' => $user->id,
                'content_type' => 'review',
                'content_id' => (string) $review->id,
                'content_url' => $this->moderationService->resolvePostUrl($slug) . '#review-' . $review->id,
                'reason' => 'admin_flag',
                'details' => $detailText,
            ]);
        }

        return response()->json([
            'id' => $review->id,
            'author' => $user->name,
            'role' => $user->role ?? 'user',
            'role_label' => __('ui.roles.' . ($user->role ?? 'user')),
            'time' => __('ui.project.comment_just_now'),
            'improve' => $review->improve,
            'why' => $review->why,
            'how' => $review->how,
            'created_at' => $review->created_at ? $review->created_at->getTimestamp() * 1000 : null,
            'is_hidden' => (bool) ($review->is_hidden ?? false),
            'moderation_status' => (string) ($review->moderation_status ?? 'approved'),
        ]);
    }

    private function postSlugExists(string $slug): bool
    {
        if (safeHasTable('posts')) {
            return Post::where('slug', $slug)->exists();
        }
        return in_array($slug, collect($this->demoContent->projects())->pluck('slug')->merge(collect($this->demoContent->questions())->pluck('slug'))->all(), true);
    }
}
