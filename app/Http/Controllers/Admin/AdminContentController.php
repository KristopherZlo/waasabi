<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDeleteRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Services\AutoModerationService;
use App\Services\ModerationService;
use App\Services\UploadAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    public function bulk(
        Request $request,
        ModerationService $moderation,
        AutoModerationService $reports,
        UploadAssetService $assets,
    ): RedirectResponse {
        $data = $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['integer', 'distinct', 'exists:posts,id'],
            'action' => ['required', 'in:queue,hide,restore,delete'],
            'reason' => ['nullable', 'required_unless:action,restore', 'string', 'max:500'],
        ]);
        $actor = $request->user();
        abort_unless($actor, 403);
        if ($data['action'] === 'delete') {
            abort_unless($actor->isAdmin(), 403);
        }

        $posts = Post::query()->with('user')->whereKey($data['post_ids'])->get();
        foreach ($posts as $post) {
            abort_if($moderation->shouldBlock($actor, $post->user), 403);
        }

        $reason = trim((string) ($data['reason'] ?? '')) ?: null;
        foreach ($posts as $post) {
            $contentType = $post->type === 'question' ? 'question' : 'post';
            $url = $moderation->resolvePostUrl($post->slug);
            $moderation->logAction($request, $actor, $data['action'], $contentType, (string) $post->id, $url, $reason, [
                'slug' => $post->slug,
                'title' => $post->title,
                'author_id' => $post->user_id,
                'author_name' => $post->user?->name,
            ]);

            if ($data['action'] === 'delete') {
                $reports->resolveReportsForModel($post, 'confirmed', 'delete');
                $reports->withdrawReportsForPostInteractions($post);
                $assets->deletePostMedia($post);
                $post->delete();

                continue;
            }

            $status = ['queue' => 'pending', 'hide' => 'hidden', 'restore' => 'approved'][$data['action']];
            $moderation->setState($post, $actor, $status);
            $reports->resolveReportsForModel(
                $post,
                $data['action'] === 'restore' ? 'rejected' : 'confirmed',
                $data['action'],
            );
        }

        return redirect()->route('admin', ['tab' => 'content'])
            ->with('toast', __('ui.admin.bulk_updated', ['count' => $posts->count()]));
    }

    public function deleteComment(AdminDeleteRequest $request, PostComment $comment, ModerationService $moderation, AutoModerationService $reports): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $comment->loadMissing('user');
        $contentUrl = $moderation->resolvePostUrl($comment->post_slug).'#comment-'.$comment->id;

        if ($moderator) {
            $moderation->logAction(
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

        $reports->resolveReportsForModel($comment, 'confirmed', 'delete');
        $comment->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }

    public function deleteReview(AdminDeleteRequest $request, PostReview $review, ModerationService $moderation, AutoModerationService $reports): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $review->loadMissing('user');
        $contentUrl = $moderation->resolvePostUrl($review->post_slug).'#review-'.$review->id;

        if ($moderator) {
            $moderation->logAction(
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

        $reports->resolveReportsForModel($review, 'confirmed', 'delete');
        $review->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }

    public function deletePost(AdminDeleteRequest $request, Post $post, ModerationService $moderation, UploadAssetService $assets, AutoModerationService $reports): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $reason = trim((string) $data['reason']);
        $moderator = $request->user();
        $post->loadMissing('user');
        $contentUrl = $moderation->resolvePostUrl($post->slug);
        $contentType = $post->type === 'question' ? 'question' : 'post';

        if ($moderator) {
            $moderation->logAction(
                $request,
                $moderator,
                'delete',
                $contentType,
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

        $reports->resolveReportsForModel($post, 'confirmed', 'delete');
        $reports->withdrawReportsForPostInteractions($post);
        $assets->deletePostMedia($post);
        $post->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin');
    }
}
