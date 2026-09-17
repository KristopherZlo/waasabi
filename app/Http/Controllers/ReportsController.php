<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\User;
use App\Services\AutoModerationService;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $viewer = $request->user();
        $target = $this->resolveTarget($data['content_type'], $data['content_id']);
        if (! $target || ! $this->canViewTarget($viewer, $target)) {
            return response()->json(['message' => __('ui.errors.reported_content_not_found')], 404);
        }
        $targetOwnerId = $target instanceof User ? $target->id : $target->user_id;
        if ((int) $targetOwnerId === (int) $viewer->id) {
            return response()->json(['message' => __('ui.errors.cannot_report_own_content')], 403);
        }

        $result = app(AutoModerationService::class)->handleReport($request, $data);

        if (($result['missing'] ?? false) === true) {
            return response()->json(['message' => __('ui.errors.reported_content_not_found')], 404);
        }

        return response()->json(array_merge(['ok' => true], array_filter([
            'report_weight' => $result['report_weight'] ?? null,
            'weight_total' => $result['weight_total'] ?? null,
            'weight_threshold' => $result['weight_threshold'] ?? null,
            'auto_hidden' => $result['auto_hidden'] ?? null,
            'duplicate' => $result['duplicate'] ?? null,
        ], static fn ($value) => $value !== null)));
    }

    private function resolveTarget(string $contentType, string $contentId): CollaborationComment|CollaborationRequest|Post|PostComment|PostReview|User|null
    {
        if (in_array($contentType, ['post', 'question'], true)) {
            return Post::query()
                ->with('user')
                ->where('type', $contentType === 'question' ? 'question' : 'post')
                ->where(ctype_digit($contentId) ? 'id' : 'slug', ctype_digit($contentId) ? (int) $contentId : $contentId)
                ->first();
        }
        if ($contentType === 'profile') {
            return User::query()
                ->where(ctype_digit($contentId) ? 'id' : 'slug', ctype_digit($contentId) ? (int) $contentId : $contentId)
                ->first();
        }
        if ($contentType === 'collaboration') {
            return ctype_digit($contentId)
                ? CollaborationRequest::with(['post.user', 'user'])->find((int) $contentId)
                : null;
        }
        if (! ctype_digit($contentId)) {
            return null;
        }

        return match ($contentType) {
            'comment' => PostComment::with(['post.user', 'user'])->find((int) $contentId),
            'review' => PostReview::with(['post.user', 'user'])->find((int) $contentId),
            'collaboration_comment' => CollaborationComment::with(['collaborationRequest.post.user', 'user'])->find((int) $contentId),
            default => null,
        };
    }

    private function canViewTarget(User $viewer, CollaborationComment|CollaborationRequest|Post|PostComment|PostReview|User $target): bool
    {
        if ($target instanceof Post) {
            return $viewer->can('view', $target);
        }
        if ($target instanceof User) {
            return true;
        }
        if ($target instanceof CollaborationRequest || $target instanceof CollaborationComment) {
            $collaboration = $target instanceof CollaborationRequest ? $target : $target->collaborationRequest;
            $post = $collaboration?->post;

            return $post && $viewer->can('view', $post) && ! ($target->user?->is_banned ?? false);
        }
        if (! $target->post || ! $viewer->can('view', $target->post)) {
            return false;
        }

        return $viewer->hasRole('moderator') || (
            ! $target->is_hidden
            && $target->moderation_status === 'approved'
            && ! ($target->user?->is_banned ?? false)
        );
    }
}
