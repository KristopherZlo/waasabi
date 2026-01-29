<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Services\DemoContentService;
use App\Services\FeedService;
use App\Services\MarkdownService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    public function __construct(
        private DemoContentService $demoContent,
        private UserPayloadService $payloadService,
        private VisibilityService $visibility,
        private MarkdownService $markdown
    ) {
    }

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $question = collect($this->demoContent->questions())->firstWhere('slug', $slug);
        if (safeHasTable('posts')) {
            $dbPost = Post::with(['user', 'editedBy'])->where('slug', $slug)->where('type', 'question')->first();
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
                $question = FeedService::mapPostToQuestionWithStats($dbPost, $stats);
            }
        }
        abort_unless($question, 404);

        $questionMarkdown = (string) ($question['body_markdown'] ?? $question['body'] ?? '');
        if (trim($questionMarkdown) !== '') {
            $question['body_html'] = $this->markdown->render($questionMarkdown);
        }

        $commentPageSize = 15;
        $answers = $question['answers'] ?? [];
        $answerTotal = 0;

        if (safeHasTable('post_comments')) {
            $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
            $this->visibility->applyToQuery($answerCountQuery, 'post_comments', $viewer);
            $answerTotal = $answerCountQuery->count();
            if ($answerTotal > 0) {
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

        $currentUser = $this->payloadService->currentUserPayload();
        return view('questions.show', ['question' => $question, 'current_user' => $currentUser]);
    }

    public function commentsChunk(Request $request, string $slug)
    {
        $viewer = $request->user();
        $limit = max(1, min(30, (int) $request->query('limit', 15)));
        $offset = max(0, (int) $request->query('offset', 0));
        $answers = [];
        $total = 0;
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);

        $question = collect($this->demoContent->questions())->firstWhere('slug', $slug);
        $dbExists = safeHasTable('posts')
            && Post::query()->where('slug', $slug)->where('type', 'question')->exists();

        if (!$question && !$dbExists) {
            return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
        }

        if (safeHasTable('posts')) {
            $post = Post::where('slug', $slug)->where('type', 'question')->first();
            if ($post && !$this->visibility->canViewHidden($viewer, $post->user_id)) {
                $isHidden = (bool) ($post->is_hidden ?? false);
                $status = (string) ($post->moderation_status ?? 'approved');
                if ($isHidden || $status !== 'approved') {
                    return response()->json(['items' => [], 'total' => 0, 'next_offset' => 0, 'has_more' => false]);
                }
            }
        }

        if (safeHasTable('post_comments')) {
            $answerCountQuery = PostComment::where('post_slug', $slug)->whereNull('parent_id');
            $this->visibility->applyToQuery($answerCountQuery, 'post_comments', $viewer);
            $total = $answerCountQuery->count();
            if ($total > 0) {
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
    }
}
