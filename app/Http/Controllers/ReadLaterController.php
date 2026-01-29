<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReadLaterController extends Controller
{
    public function index(Request $request, VisibilityService $visibility, UserPayloadService $payload): \Illuminate\View\View
    {
        $user = $request->user();
        $savedItems = [];

        if ($user && safeHasTable('post_saves') && safeHasTable('posts')) {
            $savedIds = DB::table('post_saves')
                ->where('user_id', $user->id)
                ->pluck('post_id')
                ->toArray();

            $savedPostsQuery = Post::with(['user', 'editedBy'])
                ->whereIn('id', $savedIds);
            $visibility->applyToQuery($savedPostsQuery, 'posts', $user);

            $savedPosts = $savedPostsQuery->latest()->get();
            $stats = FeedService::preparePostStats($savedPosts, $user);

            $savedItems = $savedPosts
                ->map(static function (Post $post) use ($stats) {
                    if ($post->type === 'question') {
                        return [
                            'type' => 'question',
                            'data' => FeedService::mapPostToQuestionWithStats($post, $stats),
                        ];
                    }
                    return [
                        'type' => 'project',
                        'data' => FeedService::mapPostToProjectWithStats($post, $stats),
                    ];
                })
                ->values()
                ->all();
        }

        return view('read-later', [
            'items' => $savedItems,
            'current_user' => $payload->currentUserPayload(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['items' => []], 401);
        }
        if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
            return response()->json(['items' => []], 503);
        }

        $items = DB::table('post_saves')
            ->join('posts', 'posts.id', '=', 'post_saves.post_id')
            ->where('post_saves.user_id', $user->id)
            ->orderByDesc('post_saves.created_at')
            ->limit(200)
            ->pluck('posts.slug')
            ->filter()
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }

    public function sync(Request $request, VisibilityService $visibility): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['items' => []], 401);
        }
        if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
            return response()->json(['items' => []], 503);
        }

        $rawItems = $request->input('items', []);
        if (!is_array($rawItems)) {
            return response()->json(['message' => 'Invalid payload'], 422);
        }

        $slugs = collect($rawItems)
            ->map(static fn ($slug) => Str::of((string) $slug)->trim()->lower()->toString())
            ->filter(static fn ($slug) => $slug !== '')
            ->unique()
            ->take(400)
            ->values();

        if ($slugs->isNotEmpty()) {
            $postsQuery = Post::query()
                ->whereIn('slug', $slugs->all())
                ->select('posts.id', 'posts.slug');
            $visibility->applyToQuery($postsQuery, 'posts', $user);
            $posts = $postsQuery->get();

            if ($posts->isNotEmpty()) {
                $postIds = $posts->pluck('id')->all();
                $existingIds = DB::table('post_saves')
                    ->where('user_id', $user->id)
                    ->whereIn('post_id', $postIds)
                    ->pluck('post_id')
                    ->all();

                $missingIds = array_values(array_diff($postIds, $existingIds));
                if ($missingIds) {
                    $now = now();
                    $rows = array_map(static fn (int $postId) => [
                        'user_id' => $user->id,
                        'post_id' => $postId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $missingIds);

                    DB::table('post_saves')->insert($rows);
                }
            }
        }

        $savedQuery = Post::query()
            ->join('post_saves', 'post_saves.post_id', '=', 'posts.id')
            ->where('post_saves.user_id', $user->id)
            ->orderByDesc('post_saves.created_at')
            ->select('posts.slug');
        $visibility->applyToQuery($savedQuery, 'posts', $user);

        $items = $savedQuery
            ->limit(400)
            ->pluck('posts.slug')
            ->filter()
            ->values()
            ->all();

        return response()->json(['items' => $items]);
    }

    public function render(Request $request, VisibilityService $visibility): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['items' => [], 'slugs' => []], 401);
        }
        if (!safeHasTable('post_saves') || !safeHasTable('posts')) {
            return response()->json(['items' => [], 'slugs' => []], 503);
        }

        $savedPostsQuery = Post::with(['user', 'editedBy'])
            ->join('post_saves', 'post_saves.post_id', '=', 'posts.id')
            ->where('post_saves.user_id', $user->id)
            ->orderByDesc('post_saves.created_at')
            ->select('posts.*')
            ->distinct();
        $visibility->applyToQuery($savedPostsQuery, 'posts', $user);

        $savedPosts = $savedPostsQuery->get();
        $stats = FeedService::preparePostStats($savedPosts, $user);

        $items = $savedPosts
            ->map(static function (Post $post) use ($stats) {
                if ($post->type === 'question') {
                    $question = FeedService::mapPostToQuestionWithStats($post, $stats);
                    return view('partials.question-card', ['question' => $question])->render();
                }
                $project = FeedService::mapPostToProjectWithStats($post, $stats);
                return view('partials.project-card', ['project' => $project])->render();
            })
            ->values()
            ->all();

        $slugs = $savedPosts
            ->pluck('slug')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'items' => $items,
            'slugs' => $slugs,
        ]);
    }
}
