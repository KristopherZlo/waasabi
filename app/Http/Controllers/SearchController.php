<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Services\FeedService;
use App\Services\VisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function __invoke(Request $request, VisibilityService $visibility): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $term = trim($data['q']);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $postQuery = Post::with('user:id,name,slug')
            ->where(function ($query) use ($like): void {
                $query->where('title', 'like', $like)
                    ->orWhere('subtitle', 'like', $like)
                    ->orWhere('body_markdown', 'like', $like)
                    ->orWhere('tags', 'like', $like);
            })
            ->latest()
            ->limit(8);
        $visibility->applyToQuery($postQuery, 'posts', null);

        $items = $postQuery->get()->map(function (Post $post): array {
            $type = $post->type === 'question' ? 'question' : 'post';

            return [
                'type' => $type,
                'title' => $post->title,
                'subtitle' => $post->subtitle,
                'url' => $type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug),
                'slug' => $post->slug,
                'author' => $post->user?->name,
            ];
        });

        if ($items->count() < 10) {
            $users = User::query()
                ->where('is_banned', false)
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('bio', 'like', $like))
                ->orderBy('name')->limit(10 - $items->count())->get();
            $items = $items->concat($users->map(fn (User $user) => [
                'type' => 'user',
                'title' => $user->name,
                'subtitle' => $user->bio ?: __('ui.roles.'.$user->roleKey()),
                'url' => route('profile.show', $user->slug),
                'slug' => $user->slug,
                'author' => null,
            ]));
        }

        if ($items->count() < 10) {
            $normalized = Str::lower($term);
            $tags = collect(FeedService::buildFeedTags(200, 1000))
                ->filter(fn (array $tag) => str_contains(Str::lower($tag['label']), $normalized))
                ->take(10 - $items->count())
                ->map(fn (array $tag) => [
                    'type' => 'tag',
                    'title' => '#'.$tag['label'],
                    'subtitle' => __('ui.search_tag_posts', ['count' => $tag['count']]),
                    'url' => route('feed', ['tags' => $tag['slug']]),
                    'slug' => $tag['slug'],
                    'author' => null,
                ]);
            $items = $items->concat($tags);
        }

        return response()->json(['items' => $items->take(10)->values()]);
    }
}
