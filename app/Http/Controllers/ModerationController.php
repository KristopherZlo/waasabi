<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModerationReasonRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\AutoModerationService;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function queuePost(ModerationReasonRequest $request, Post $post, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $post->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($post, $moderator, 'pending');
        $moderation->logAction(
            $request,
            $moderator,
            'queue',
            'post',
            (string) $post->id,
            $moderation->resolvePostUrl($post->slug),
            $reason,
            [
                'slug' => $post->slug,
                'title' => $post->title,
                'author_id' => $post->user_id,
                'author_name' => $post->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($post, 'confirmed', 'queue', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $post->moderation_status]);
    }

    public function hidePost(ModerationReasonRequest $request, Post $post, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $post->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($post, $moderator, 'hidden');
        $moderation->logAction(
            $request,
            $moderator,
            'hide',
            'post',
            (string) $post->id,
            $moderation->resolvePostUrl($post->slug),
            $reason,
            [
                'slug' => $post->slug,
                'title' => $post->title,
                'author_id' => $post->user_id,
                'author_name' => $post->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($post, 'confirmed', 'hide', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $post->moderation_status]);
    }

    public function restorePost(Request $request, Post $post, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $moderation->setState($post, $moderator, 'approved');
        $moderation->logAction(
            $request,
            $moderator,
            'restore',
            'post',
            (string) $post->id,
            $moderation->resolvePostUrl($post->slug),
            null,
            [
                'slug' => $post->slug,
                'title' => $post->title,
                'author_id' => $post->user_id,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($post, 'rejected', 'restore', null, $moderator);

        return response()->json(['ok' => true, 'status' => $post->moderation_status]);
    }

    public function nsfwPost(Request $request, Post $post, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $post->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($post, $moderator, 'approved');
        if (safeHasColumn('posts', 'nsfw')) {
            $post->nsfw = true;
            $post->save();
        }

        $moderation->logAction(
            $request,
            $moderator,
            'nsfw',
            'post',
            (string) $post->id,
            $moderation->resolvePostUrl($post->slug),
            null,
            [
                'slug' => $post->slug,
                'title' => $post->title,
                'author_id' => $post->user_id,
                'author_name' => $post->user?->name,
            ],
        );

        return response()->json(['ok' => true, 'status' => $post->moderation_status]);
    }

    public function queueComment(ModerationReasonRequest $request, PostComment $comment, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $comment->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $comment->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($comment, $moderator, 'pending');
        $contentUrl = $moderation->resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        $moderation->logAction(
            $request,
            $moderator,
            'queue',
            'comment',
            (string) $comment->id,
            $contentUrl,
            $reason,
            [
                'post_slug' => $comment->post_slug,
                'author_id' => $comment->user_id,
                'author_name' => $comment->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($comment, 'confirmed', 'queue', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $comment->moderation_status]);
    }

    public function hideComment(ModerationReasonRequest $request, PostComment $comment, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $comment->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $comment->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($comment, $moderator, 'hidden');
        $contentUrl = $moderation->resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        $moderation->logAction(
            $request,
            $moderator,
            'hide',
            'comment',
            (string) $comment->id,
            $contentUrl,
            $reason,
            [
                'post_slug' => $comment->post_slug,
                'author_id' => $comment->user_id,
                'author_name' => $comment->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($comment, 'confirmed', 'hide', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $comment->moderation_status]);
    }

    public function restoreComment(Request $request, PostComment $comment, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $moderation->setState($comment, $moderator, 'approved');
        $contentUrl = $moderation->resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        $moderation->logAction(
            $request,
            $moderator,
            'restore',
            'comment',
            (string) $comment->id,
            $contentUrl,
            null,
            [
                'post_slug' => $comment->post_slug,
                'author_id' => $comment->user_id,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($comment, 'rejected', 'restore', null, $moderator);

        return response()->json(['ok' => true, 'status' => $comment->moderation_status]);
    }

    public function queueReview(ModerationReasonRequest $request, PostReview $review, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $review->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $review->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($review, $moderator, 'pending');
        $contentUrl = $moderation->resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        $moderation->logAction(
            $request,
            $moderator,
            'queue',
            'review',
            (string) $review->id,
            $contentUrl,
            $reason,
            [
                'post_slug' => $review->post_slug,
                'author_id' => $review->user_id,
                'author_name' => $review->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($review, 'confirmed', 'queue', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $review->moderation_status]);
    }

    public function hideReview(ModerationReasonRequest $request, PostReview $review, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $review->loadMissing('user');
        if ($moderation->shouldBlock($moderator, $review->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $moderation->setState($review, $moderator, 'hidden');
        $contentUrl = $moderation->resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        $moderation->logAction(
            $request,
            $moderator,
            'hide',
            'review',
            (string) $review->id,
            $contentUrl,
            $reason,
            [
                'post_slug' => $review->post_slug,
                'author_id' => $review->user_id,
                'author_name' => $review->user?->name,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($review, 'confirmed', 'hide', $reason, $moderator);

        return response()->json(['ok' => true, 'status' => $review->moderation_status]);
    }

    public function restoreReview(Request $request, PostReview $review, ModerationService $moderation): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $moderation->setState($review, $moderator, 'approved');
        $contentUrl = $moderation->resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        $moderation->logAction(
            $request,
            $moderator,
            'restore',
            'review',
            (string) $review->id,
            $contentUrl,
            null,
            [
                'post_slug' => $review->post_slug,
                'author_id' => $review->user_id,
            ],
        );
        app(AutoModerationService::class)->resolveReportsForModel($review, 'rejected', 'restore', null, $moderator);

        return response()->json(['ok' => true, 'status' => $review->moderation_status]);
    }
}
