<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\AutoModerationService;
use App\Services\TextModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InteractionContentController extends Controller
{
    public function __construct(
        private TextModerationService $textModeration,
        private AutoModerationService $reports,
    ) {}

    public function updateComment(Request $request, PostComment $postComment): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $postComment->user_id);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'section' => ['nullable', 'string', 'max:80'],
        ]);

        $flagged = ! $request->user()->hasRole('moderator')
            && (bool) ($this->textModeration->analyze($data['body'], ['type' => 'comment'])['flagged'] ?? false);
        $postComment->update(array_merge($data, $this->moderationState($postComment->hidden_by, $flagged)));

        return $this->updatedResponse($request, $postComment->post_slug, 'comment', $postComment->id);
    }

    public function destroyComment(Request $request, PostComment $postComment): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $postComment->user_id);
        $slug = $postComment->post_slug;
        $this->reports->resolveReportsForModel($postComment, 'withdrawn', 'source_removed');
        $postComment->delete();

        return $this->deletedResponse($request, $slug);
    }

    public function updateReview(Request $request, PostReview $postReview): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $postReview->user_id);
        $data = $request->validate([
            'improve' => ['required', 'string', 'max:2000'],
            'why' => ['required', 'string', 'max:2000'],
            'how' => ['required', 'string', 'max:2000'],
        ]);
        $body = implode("\n\n", [$data['improve'], $data['why'], $data['how']]);
        $flagged = ! $request->user()->hasRole('moderator')
            && (bool) ($this->textModeration->analyze($body, ['type' => 'review'])['flagged'] ?? false);
        $postReview->update(array_merge($data, $this->moderationState($postReview->hidden_by, $flagged)));

        return $this->updatedResponse($request, $postReview->post_slug, 'review', $postReview->id);
    }

    public function destroyReview(Request $request, PostReview $postReview): JsonResponse|RedirectResponse
    {
        $this->authorizeOwner($request, $postReview->user_id);
        $slug = $postReview->post_slug;
        $this->reports->resolveReportsForModel($postReview, 'withdrawn', 'source_removed');
        $postReview->delete();

        return $this->deletedResponse($request, $slug);
    }

    private function authorizeOwner(Request $request, int $ownerId): void
    {
        abort_unless((int) $request->user()->id === $ownerId, 403);
    }

    private function moderationState(?int $hiddenBy, bool $flagged): array
    {
        if ($hiddenBy !== null) {
            return [];
        }

        return [
            'moderation_status' => $flagged ? 'pending' : 'approved',
            'is_hidden' => $flagged,
            'hidden_at' => $flagged ? now() : null,
            'hidden_by' => null,
        ];
    }

    private function updatedResponse(Request $request, string $slug, string $type, int $id): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to($this->contentUrl($slug).'#'.$type.'-'.$id)
            ->with('toast', __('ui.project.interaction_updated'));
    }

    private function deletedResponse(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to($this->contentUrl($slug))->with('toast', __('ui.project.interaction_deleted'));
    }

    private function contentUrl(string $slug): string
    {
        return Post::where('slug', $slug)->value('type') === 'question'
            ? route('questions.show', $slug)
            : route('project', $slug);
    }
}
