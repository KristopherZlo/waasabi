<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\AutoModerationService;
use App\Services\UploadAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function destroy(Request $request, Post $post, UploadAssetService $assets, AutoModerationService $reports): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.errors.unauthorized')], 401);
            }

            return redirect()->route('login');
        }
        if ($user->cannot('delete', $post)) {
            abort(403);
        }

        $reports->resolveReportsForModel($post, 'withdrawn', 'source_removed');
        $reports->withdrawReportsForPostInteractions($post);
        $assets->deletePostMedia($post);
        $post->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('feed');
    }
}
