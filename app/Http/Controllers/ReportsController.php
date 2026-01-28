<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\AutoModerationService;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $viewer = $request->user();
        $contentId = $data['content_id'] ?? null;
        if ($viewer && $contentId !== null) {
            $viewerId = $viewer->id;
            $contentType = $data['content_type'];
            $isSelfReport = false;

            if ($contentType === 'content') {
                $isSelfReport = (string) $viewerId === (string) $contentId;
            } elseif (in_array($contentType, ['post', 'question'], true) && safeHasTable('posts')) {
                $query = Post::query();
                $query->where('type', $contentType === 'question' ? 'question' : 'post');
                if (ctype_digit((string) $contentId)) {
                    $query->where('id', (int) $contentId);
                } else {
                    $query->where('slug', $contentId);
                }
                $post = $query->first();
                $isSelfReport = $post && $post->user_id === $viewerId;
            } elseif ($contentType === 'comment' && safeHasTable('post_comments') && ctype_digit((string) $contentId)) {
                $comment = PostComment::where('id', (int) $contentId)->first();
                $isSelfReport = $comment && $comment->user_id === $viewerId;
            } elseif ($contentType === 'review' && safeHasTable('post_reviews') && ctype_digit((string) $contentId)) {
                $review = PostReview::where('id', (int) $contentId)->first();
                $isSelfReport = $review && $review->user_id === $viewerId;
            }

            if ($isSelfReport) {
                return response()->json(['message' => 'Cannot report your own content.'], 403);
            }
        }

        $result = app(AutoModerationService::class)->handleReport($request, $data);

        return response()->json(array_merge(['ok' => true], array_filter([
            'report_weight' => $result['report_weight'] ?? null,
            'weight_total' => $result['weight_total'] ?? null,
            'weight_threshold' => $result['weight_threshold'] ?? null,
            'auto_hidden' => $result['auto_hidden'] ?? null,
            'duplicate' => $result['duplicate'] ?? null,
        ], static fn ($value) => $value !== null)));
    }
}
