<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\BadgeCatalogService;
use App\Services\BadgePayloadService;
use App\Services\DemoContentService;
use App\Services\UserPayloadService;
use App\Services\UserSlugService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function __construct(
        private DemoContentService $demoContent,
        private BadgeCatalogService $badgeCatalog,
        private BadgePayloadService $badgePayload,
        private UserPayloadService $payloadService,
        private UserSlugService $slugService,
        private VisibilityService $visibility
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && safeHasColumn('users', 'slug') && empty($user->slug)) {
            $user->slug = $this->slugService->generate($user->name ?? 'user');
            $user->save();
        }
        if ($user && !empty($user->slug)) {
            return redirect()->route('profile.show', $user->slug);
        }

        $projects = $this->demoContent->projects();
        $profile = $this->demoContent->profile();

        return view('profile', $this->demoProfilePayload($projects, $profile));
    }

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $user = null;
        if (safeHasTable('users')) {
            $user = User::where('slug', $slug)->first();
            if (!$user) {
                $user = User::all()->first(function (User $candidate) use ($slug) {
                    return Str::slug($candidate->name ?? '') === $slug;
                });
            }
        }

        $projects = $this->demoContent->projects();
        $profile = $this->demoContent->profile();

        if (!$user) {
            $fallbackSlug = Str::slug($profile['name'] ?? '');
            if ($fallbackSlug !== $slug) {
                abort(404);
            }
            return view('profile', $this->demoProfilePayload($projects, $profile));
        }

        if (safeHasColumn('users', 'slug') && empty($user->slug)) {
            $user->slug = $this->slugService->generate($user->name ?? 'user');
            $user->save();
        }

        $isBanned = safeHasColumn('users', 'is_banned') ? (bool) $user->is_banned : false;

        $profileUser = [
            'id' => $user->id,
            'name' => $user->name,
            'slug' => $user->slug,
            'bio' => $user->bio ?? '',
            'role' => $user->roleKey(),
            'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            'banner_url' => safeHasColumn('users', 'banner_url') ? ($user->banner_url ?: null) : null,
            'is_banned' => $isBanned,
            'allow_follow' => safeHasColumn('users', 'connections_allow_follow')
                ? (bool) $user->connections_allow_follow
                : true,
            'show_follow_counts' => safeHasColumn('users', 'connections_show_follow_counts')
                ? (bool) $user->connections_show_follow_counts
                : true,
        ];

        $projectsList = [];
        $questionsList = [];
        if (!$isBanned && safeHasTable('posts')) {
            $userPostsQuery = Post::with(['user', 'editedBy'])->where('user_id', $user->id);
            if (!$this->visibility->canViewHidden($viewer, $user->id)) {
                $this->visibility->applyToQuery($userPostsQuery, 'posts', $viewer);
            }
            $userPosts = $userPostsQuery->latest()->get();
            $stats = \App\Services\FeedService::preparePostStats($userPosts, $viewer);
            $projectsList = $userPosts
                ->where('type', 'post')
                ->map(static fn (Post $post) => \App\Services\FeedService::mapPostToProjectWithStats($post, $stats))
                ->values()
                ->all();
            $questionsList = $userPosts
                ->where('type', 'question')
                ->map(static fn (Post $post) => \App\Services\FeedService::mapPostToQuestionWithStats($post, $stats))
                ->values()
                ->all();
        }

        $commentsList = [];
        if (safeHasTable('post_comments')) {
            $commentQuery = PostComment::with('user')
                ->where('user_id', $user->id);
            if (!$this->visibility->canViewHidden($viewer, $user->id)) {
                $this->visibility->applyToQuery($commentQuery, 'post_comments', $viewer);
            }
            $commentRows = $commentQuery
                ->latest()
                ->take(20)
                ->get();

            $postMap = collect();
            if (safeHasTable('posts') && $commentRows->isNotEmpty()) {
                $postMapQuery = Post::whereIn('slug', $commentRows->pluck('post_slug')->all());
                if (!$this->visibility->canViewHidden($viewer, $user->id)) {
                    $this->visibility->applyToQuery($postMapQuery, 'posts', $viewer);
                }
                $postMap = $postMapQuery
                    ->get(['slug', 'title', 'type'])
                    ->keyBy('slug');
            }

            $commentsList = $commentRows
                ->map(function (PostComment $comment) use ($postMap) {
                    $post = $postMap->get($comment->post_slug);
                    return [
                        'body' => $comment->body,
                        'time' => $comment->created_at?->diffForHumans() ?? '',
                        'post_slug' => $comment->post_slug,
                        'post_title' => $post?->title ?? $comment->post_slug,
                        'post_type' => $post?->type ?? 'post',
                    ];
                })
                ->values()
                ->all();
        }

        $followersCount = 0;
        $followingCount = 0;
        $isFollowing = false;
        if (safeHasTable('user_follows')) {
            $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
            $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
            if ($viewer) {
                $isFollowing = DB::table('user_follows')
                    ->where('following_id', $user->id)
                    ->where('follower_id', $viewer->id)
                    ->exists();
            }
        }

        $badges = $this->badgePayload->forUser($user, $this->badgeCatalog->all());

        return view('profile', [
            'projects' => $projectsList,
            'questions' => $questionsList,
            'comments' => $commentsList,
            'profile_user' => $profileUser,
            'is_owner' => Auth::id() === $user->id,
            'followers_count' => $followersCount,
            'following_count' => $followingCount,
            'is_following' => $isFollowing,
            'badges' => $badges,
            'badge_catalog' => $this->badgeCatalog->all(),
            'current_user' => $this->payloadService->currentUserPayload(),
        ]);
    }

    private function demoProfilePayload(array $projects, array $profile): array
    {
        return [
            'projects' => $projects,
            'questions' => [],
            'comments' => [],
            'profile_user' => $profile,
            'is_owner' => false,
            'followers_count' => 0,
            'following_count' => 0,
            'is_following' => false,
            'badges' => [],
            'badge_catalog' => $this->badgeCatalog->all(),
            'current_user' => $this->payloadService->currentUserPayload(),
        ];
    }
}
