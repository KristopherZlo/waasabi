<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedService;
use App\Services\UserPayloadService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;

class ShowcaseController extends Controller
{
    public function __construct(
        private UserPayloadService $payloadService,
        private VisibilityService $visibility,
    ) {}

    public function index(Request $request)
    {
        $buildCollection = function (string $title, callable $scope) use ($request): array {
            $query = Post::with(['user', 'editedBy'])->where('type', 'post');
            $this->visibility->applyToQuery($query, 'posts', $request->user());
            $scope($query);
            $posts = $query->limit(8)->get();
            $stats = FeedService::preparePostStats($posts, $request->user());

            return [
                'title' => $title,
                'projects' => $posts->map(fn (Post $post) => FeedService::mapPostToProjectWithStats($post, $stats))->all(),
            ];
        };

        return view('showcase', [
            'showcase' => [
                $buildCollection(__('ui.showcase.shipped'), fn ($query) => $query->where('status', 'done')->latest('published_at')),
                $buildCollection(__('ui.showcase.active'), fn ($query) => $query->whereIn('status', ['in_progress', 'in-progress'])->latest('updated_at')),
                $buildCollection(__('ui.showcase.discussed'), fn ($query) => $query->withCount('comments')->orderByDesc('comments_count')),
            ],
            'current_user' => $this->payloadService->currentUserPayload(),
        ]);
    }
}
