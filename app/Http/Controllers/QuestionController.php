<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Services\FeedService;
use App\Services\MarkdownService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class QuestionController extends Controller
{
    public function __construct(
        private UserPayloadService $payloadService,
        private VisibilityService $visibility,
        private MarkdownService $markdown
    ) {}

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $dbPost = Post::with(['user', 'editedBy'])
            ->where('slug', $slug)
            ->where('type', 'question')
            ->firstOrFail();
        abort_unless(Gate::allows('view', $dbPost), 404);
        $stats = FeedService::preparePostStats([$dbPost], $viewer);
        $question = FeedService::mapPostToQuestionWithStats($dbPost, $stats);

        $questionMarkdown = (string) ($question['body_markdown'] ?? $question['body'] ?? '');
        if (trim($questionMarkdown) !== '') {
            $question['body_html'] = $this->markdown->render($questionMarkdown);
        }

        $commentPageSize = 15;
        $answers = [];
        $answerTotal = 0;

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
                            'id' => $author?->id,
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->vote_score ?? 0,
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
                            'id' => $author?->id,
                            'name' => $author?->name ?? __('ui.project.anonymous'),
                            'role' => $author?->role ?? 'user',
                            'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
                            'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                        ],
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'text' => $comment->body,
                        'id' => $comment->id,
                        'parent_id' => $comment->parent_id,
                        'useful' => $comment->vote_score ?? 0,
                        'created_at' => $comment->created_at ? $comment->created_at->getTimestamp() * 1000 : null,
                        'is_hidden' => (bool) ($comment->is_hidden ?? false),
                        'moderation_status' => (string) ($comment->moderation_status ?? 'approved'),
                    ];
                    $replies = $replyMap->get($comment->id, []);
                    if (! empty($replies)) {
                        $entry['replies'] = $replies;
                    }

                    return $entry;
                })
                ->values()
                ->all();
        }

        $question['answers'] = $answers;
        $question['answers_total'] = $answerTotal;
        $question['answers_offset'] = count($answers);

        $currentUser = $this->payloadService->currentUserPayload();

        return view('questions.show', ['question' => $question, 'current_user' => $currentUser]);
    }
}
