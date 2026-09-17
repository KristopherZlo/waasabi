<?php

namespace App\Http\Controllers;

use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use App\Services\BadgeCatalogService;
use App\Services\BadgePayloadService;
use App\Services\FeedService;
use App\Services\UserPayloadService;
use App\Services\UserSlugService;
use App\Services\VisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(
        private BadgeCatalogService $badgeCatalog,
        private BadgePayloadService $badgePayload,
        private UserPayloadService $payloadService,
        private UserSlugService $slugService,
        private VisibilityService $visibility
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && empty($user->slug)) {
            $user->slug = $this->slugService->generate($user->name ?? 'user');
            $user->save();
        }
        if ($user && ! empty($user->slug)) {
            return redirect()->route('profile.show', $user->slug);
        }

        return redirect()->route('login');
    }

    public function show(Request $request, string $slug)
    {
        $viewer = $request->user();
        $user = User::where('slug', $slug)->firstOrFail();

        if (empty($user->slug)) {
            $user->slug = $this->slugService->generate($user->name ?? 'user');
            $user->save();
        }

        $isBanned = (bool) $user->is_banned;

        $profileUser = [
            'id' => $user->id,
            'name' => $user->name,
            'slug' => $user->slug,
            'bio' => $user->bio ?? '',
            'skills' => $user->skills,
            'open_to_help' => $user->open_to_help,
            'portfolio_url' => $user->portfolio_url,
            'featured_post_id' => $user->featured_post_id,
            'role' => $user->roleKey(),
            'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            'banner_url' => $user->banner_url ?: null,
            'is_banned' => $isBanned,
            'allow_follow' => (bool) $user->connections_allow_follow,
            'show_follow_counts' => (bool) $user->connections_show_follow_counts,
        ];

        $projectsList = [];
        $questionsList = [];
        if (! $isBanned) {
            $userPostsQuery = Post::with(['user', 'editedBy'])->where('user_id', $user->id);
            if (! $viewer || $viewer->id !== $user->id) {
                $this->visibility->applyToQuery($userPostsQuery, 'posts', $viewer);
            }
            $userPosts = $userPostsQuery->latest()->get();
            $stats = FeedService::preparePostStats($userPosts, $viewer);
            $projectsList = $userPosts
                ->where('type', 'post')
                ->map(static fn (Post $post) => FeedService::mapPostToProjectWithStats($post, $stats))
                ->values()
                ->all();
            $questionsList = $userPosts
                ->where('type', 'question')
                ->map(static fn (Post $post) => FeedService::mapPostToQuestionWithStats($post, $stats))
                ->values()
                ->all();
        }

        $commentsList = [];
        if (! $isBanned) {
            $commentQuery = PostComment::with('user')
                ->where('user_id', $user->id);
            if (! $this->visibility->canViewHidden($viewer, $user->id)) {
                $this->visibility->applyToQuery($commentQuery, 'post_comments', $viewer);
                $commentQuery->whereHas('post', function ($postQuery) use ($viewer): void {
                    $this->visibility->applyToQuery($postQuery, 'posts', $viewer);
                });
            }
            $commentRows = $commentQuery
                ->latest()
                ->take(20)
                ->get();

            $postMap = collect();
            if ($commentRows->isNotEmpty()) {
                $postMapQuery = Post::whereIn('slug', $commentRows->pluck('post_slug')->all());
                if (! $this->visibility->canViewHidden($viewer, $user->id)) {
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

        $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
        $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
        $isFollowing = false;
        if ($viewer) {
            $isFollowing = DB::table('user_follows')
                ->where('following_id', $user->id)
                ->where('follower_id', $viewer->id)
                ->exists();
        }

        $badges = $this->badgePayload->forUser($user, $this->badgeCatalog->all());

        return view('profile', [
            'help_requests' => CollaborationRequest::query()->where('user_id', $user->id)
                ->visibleTo($viewer)->latest()->get(),
            'contributions' => Post::query()->with('user')
                ->where('user_id', '!=', $user->id)
                ->where('visibility', 'public')->where('is_hidden', false)->where('moderation_status', 'approved')
                ->whereHas('user', fn ($q) => $q->where('is_banned', false))
                ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('accepted_at')->whereIn('status', ['active', 'removed']))
                ->when($isBanned, fn ($q) => $q->whereRaw('1 = 0'))->latest()->get(),
            'projects' => collect($projectsList)->sortByDesc(fn ($p) => $p['id'] === $user->featured_post_id)->values()->all(),
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
}
