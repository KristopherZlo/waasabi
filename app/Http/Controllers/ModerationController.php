<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModerationReasonRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\AutoModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModerationController extends Controller
{
    public function queuePost(ModerationReasonRequest $request, Post $post): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $post->loadMissing('user');
        if (shouldBlockModeration($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($post, $moderator, 'pending');
        logModerationAction(
            $request,
            $moderator,
            'queue',
            'post',
            (string) $post->id,
            resolvePostUrl($post->slug),
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

    public function hidePost(ModerationReasonRequest $request, Post $post): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $post->loadMissing('user');
        if (shouldBlockModeration($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($post, $moderator, 'hidden');
        logModerationAction(
            $request,
            $moderator,
            'hide',
            'post',
            (string) $post->id,
            resolvePostUrl($post->slug),
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

    public function restorePost(Request $request, Post $post): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        setModerationState($post, $moderator, 'approved');
        logModerationAction(
            $request,
            $moderator,
            'restore',
            'post',
            (string) $post->id,
            resolvePostUrl($post->slug),
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

    public function nsfwPost(Request $request, Post $post): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $post->loadMissing('user');
        if (shouldBlockModeration($moderator, $post->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($post, $moderator, 'approved');
        if (safeHasColumn('posts', 'nsfw')) {
            $post->nsfw = true;
            $post->save();
        }

        logModerationAction(
            $request,
            $moderator,
            'nsfw',
            'post',
            (string) $post->id,
            resolvePostUrl($post->slug),
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

    public function queueComment(ModerationReasonRequest $request, PostComment $comment): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $comment->loadMissing('user');
        if (shouldBlockModeration($moderator, $comment->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($comment, $moderator, 'pending');
        $contentUrl = resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        logModerationAction(
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

    public function hideComment(ModerationReasonRequest $request, PostComment $comment): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $comment->loadMissing('user');
        if (shouldBlockModeration($moderator, $comment->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($comment, $moderator, 'hidden');
        $contentUrl = resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        logModerationAction(
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

    public function restoreComment(Request $request, PostComment $comment): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        setModerationState($comment, $moderator, 'approved');
        $contentUrl = resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;
        logModerationAction(
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

    public function queueReview(ModerationReasonRequest $request, PostReview $review): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $review->loadMissing('user');
        if (shouldBlockModeration($moderator, $review->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($review, $moderator, 'pending');
        $contentUrl = resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        logModerationAction(
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

    public function hideReview(ModerationReasonRequest $request, PostReview $review): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        $review->loadMissing('user');
        if (shouldBlockModeration($moderator, $review->user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        setModerationState($review, $moderator, 'hidden');
        $contentUrl = resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        logModerationAction(
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

    public function restoreReview(Request $request, PostReview $review): JsonResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        setModerationState($review, $moderator, 'approved');
        $contentUrl = resolvePostUrl($review->post_slug) . '#review-' . $review->id;
        logModerationAction(
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
