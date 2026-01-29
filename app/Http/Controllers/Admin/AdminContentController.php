<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDeleteRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AdminContentController extends Controller
{
    public function deleteComment(AdminDeleteRequest $request, PostComment $comment): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $comment->loadMissing('user');
        $contentUrl = resolvePostUrl($comment->post_slug) . '#comment-' . $comment->id;

        if ($moderator) {
            logModerationAction(
                $request,
                $moderator,
                'delete',
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
        }

        $comment->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }

    public function deleteReview(AdminDeleteRequest $request, PostReview $review): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $review->loadMissing('user');
        $contentUrl = resolvePostUrl($review->post_slug) . '#review-' . $review->id;

        if ($moderator) {
            logModerationAction(
                $request,
                $moderator,
                'delete',
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
        }

        $review->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }

    public function deletePost(AdminDeleteRequest $request, Post $post): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $post->loadMissing('user');
        $contentUrl = resolvePostUrl($post->slug);

        if ($moderator) {
            logModerationAction(
                $request,
                $moderator,
                'delete',
                'post',
                (string) $post->id,
                $contentUrl,
                $reason,
                [
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'author_id' => $post->user_id,
                    'author_name' => $post->user?->name,
                ],
            );
        }

        $post->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }
}
