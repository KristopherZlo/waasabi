<?php

namespace App\Http\Controllers;

use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\ContentReport;
use App\Models\ContentReportScore;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostReview;
use App\Models\ProfileWallPost;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\BadgeCatalogService;
use App\Services\BadgePayloadService;
use App\Services\CollaborationService;
use App\Services\GitHubReadmeService;
use App\Services\MarkdownService;
use App\Services\ModerationService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** The public interface has explicit payloads; Eloquent users are never sent wholesale. */
class CommunityPageController extends Controller
{
    private function person(User $user): array
    {
        return $user->only('id', 'name', 'slug', 'avatar', 'skills', 'bio', 'headline', 'open_to_help', 'portfolio_url', 'profile_links', 'banner_url') + [
            'verified' => (bool) $user->is_profile_verified,
        ];
    }

    private function publicWorks()
    {
        return Post::query()->with('user')->where('visibility', 'public')->where('is_hidden', false)
            ->where('moderation_status', 'approved')->whereHas('user', fn ($q) => $q->where('is_banned', false));
    }

    private function card(Post $post): array
    {
        return $post->only('id', 'slug', 'title', 'subtitle', 'type', 'is_project', 'category', 'tags', 'status', 'visibility', 'feedback_mode', 'nsfw', 'is_hidden', 'moderation_status') + [
            'author' => $this->person($post->user),
            'url' => $post->type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug),
            'cover' => $post->cover_url,
            'preview' => Str::limit(html_entity_decode(strip_tags(app(MarkdownService::class)->render($post->body_markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 240),
            'date' => $post->created_at?->toISOString(),
            'comments' => $post->comments()->where('is_hidden', false)->where('moderation_status', 'approved')->whereHas('user', fn ($q) => $q->where('is_banned', false))->count(),
            'score' => $post->upvoters()->count(),
            'liked' => auth()->check() && $post->upvoters()->where('users.id', auth()->id())->exists(),
            'saved' => auth()->check() && $post->savers()->where('users.id', auth()->id())->exists(),
        ];
    }

    private function opening(CollaborationRequest $opening): array
    {
        return $opening->only('id', 'title', 'role', 'skills', 'availability', 'format', 'summary', 'status', 'post_id') + [
            'author' => $this->person($opening->user), 'open' => $opening->isOpen(),
            'date' => $opening->created_at?->toISOString(),
            'project' => $opening->post ? $opening->post->only('id', 'slug', 'title') : null,
            'applications' => (int) ($opening->applications_count ?? 0),
        ];
    }

    public function feed(Request $request): Response
    {
        $request->validate([
            'q' => 'nullable|string|max:100', 'filter' => 'nullable|string', 'stream' => 'nullable|string', 'sort' => 'nullable|in:hot,new',
            'role' => 'nullable|string|max:60', 'availability' => 'nullable|string|max:60', 'format' => 'nullable|string|max:60',
            'tags' => 'nullable|string|max:200', 'exclude' => 'nullable|string|max:200',
        ]);
        $filter = $request->string('filter')->toString();
        $stream = $request->string('stream')->toString();
        $sort = $request->string('sort')->toString() === 'new' ? 'new' : 'hot';
        $term = trim($request->string('q')->toString());
        $includedTags = collect(explode(',', $request->string('tags')->toString()))->map(fn ($tag) => trim($tag))->filter()->take(8);
        $excludedTags = collect(explode(',', $request->string('exclude')->toString()))->map(fn ($tag) => trim($tag))->filter()->take(8);
        $works = $this->publicWorks()->when($stream === 'collaboration', fn ($q) => $q->whereRaw('1 = 0'))
            ->when($stream === 'projects', fn ($q) => $q->where('is_project', true)->where('type', 'post'))
            ->when($stream === 'questions', fn ($q) => $q->where('type', 'question'))
            ->when($includedTags->isNotEmpty(), function ($query) use ($includedTags): void {
                $includedTags->each(fn ($tag) => $query->whereJsonContains('tags', $tag));
            })
            ->when($excludedTags->isNotEmpty(), function ($query) use ($excludedTags): void {
                $excludedTags->each(fn ($tag) => $query->whereJsonDoesntContain('tags', $tag));
            })
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%$term%")->orWhere('body_markdown', 'like', "%$term%")))
            ->when($filter === 'following', function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('followers', fn ($q) => $q->where('users.id', $request->user()?->id ?? 0))
                        ->orWhereIn('user_id', DB::table('user_follows')->where('follower_id', $request->user()?->id ?? 0)->select('following_id'));
                });
            })->when($filter === 'quiet', fn ($q) => $q->whereDoesntHave('comments', fn ($q) => $q->where('is_hidden', false)->where('moderation_status', 'approved')->whereHas('user', fn ($q) => $q->where('is_banned', false))))
            ->when($sort === 'hot', fn ($q) => $q->withCount('upvoters')
                ->withCount(['comments as hot_comments_count' => fn ($q) => $q->where('is_hidden', false)->where('moderation_status', 'approved')->whereHas('user', fn ($q) => $q->where('is_banned', false))])
                ->orderByRaw('(upvoters_count * 3 + hot_comments_count * 2) DESC'))
            ->orderByRaw('COALESCE(activity_at, posts.created_at) DESC')->orderByDesc('id')->paginate(12)->withQueryString();
        $works->through(fn ($post) => $this->card($post));
        $openings = CollaborationRequest::with('user', 'post')->visibleTo($request->user())->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($stream === 'collaboration', fn ($query) => $query
                ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
                ->when($request->filled('availability'), fn ($q) => $q->where('availability', $request->string('availability')))
                ->when($request->filled('format'), fn ($q) => $q->where('format', $request->string('format')))
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%$term%")
                    ->orWhere('summary', 'like', "%$term%")
                    ->orWhere('skills', 'like', "%$term%"))))
            ->latest()->take($stream === 'collaboration' ? 12 : 4)->get();
        $suggestedTags = $this->publicWorks()->latest()->limit(160)->pluck('tags')->flatten()
            ->filter()->countBy()->sortDesc()->keys()->take(12)->values();
        $wallPosts = collect();
        if ($filter === 'following' && $request->user()) {
            $wallPosts = ProfileWallPost::with(['user', 'profile'])->where('is_hidden', false)->where('moderation_status', 'approved')
                ->whereHas('user', fn ($q) => $q->where('is_banned', false))
                ->whereHas('profile', fn ($q) => $q->where('is_banned', false)->whereHas('followers', fn ($followers) => $followers->where('users.id', $request->user()->id)))
                ->latest()->take(12)->get()->map(fn ($post) => [
                    'id' => $post->id, 'body' => $post->body, 'date' => $post->created_at?->toISOString(),
                    'author' => $this->person($post->user), 'profile' => $this->person($post->profile),
                ]);
        }

        return Inertia::render('Feed', [
            'works' => Inertia::scroll($works), 'filter' => $filter, 'sort' => $sort, 'stream' => $stream, 'q' => $term,
            'tags' => $includedTags->implode(','), 'exclude' => $excludedTags->implode(','),
            'suggestedTags' => $suggestedTags,
            'wallPosts' => $wallPosts,
            'openings' => $openings->map(fn ($o) => $this->opening($o)),
            'people' => User::where('open_to_help', true)->where('is_banned', false)->orderBy('name')->take(4)->get()->map(fn ($u) => $this->person($u)),
        ]);
    }

    public function work(Request $request, string $slug): Response
    {
        $post = Post::with('user', 'attachments')->where('slug', $slug)->firstOrFail();
        abort_unless(Gate::allows('view', $post), 404);
        $staff = $request->user()?->can('moderate') ?? false;
        $comments = $post->comments()->with('user', 'replyTo.user')->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->when(! $staff, fn ($q) => $q->where('is_hidden', false)->where('moderation_status', 'approved'))
            ->orderBy('created_at')->paginate(30, ['*'], 'comments_page')->withQueryString();
        $commentVotes = $request->user()
            ? DB::table('post_comment_votes')->where('user_id', $request->user()->id)
                ->whereIn('post_comment_id', $comments->getCollection()->pluck('id'))->pluck('value', 'post_comment_id')
            : collect();
        $comments->through(fn ($c) => $c->only('id', 'body', 'parent_id', 'reply_to_id', 'is_hidden', 'moderation_status') + [
            'author' => $this->person($c->user), 'date' => $c->created_at->toISOString(),
            'reply_to' => $c->replyTo?->user ? $this->person($c->replyTo->user) : null,
            'score' => (int) $c->vote_score, 'vote' => (int) ($commentVotes[$c->id] ?? 0),
            'can_edit' => $request->user()?->id === $c->user_id,
            'can_delete' => $request->user()?->id === $c->user_id,
        ]);
        $updates = $post->updates()->with('user')->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->when(! $staff, fn ($q) => $q->where(fn ($q) => $q->where('is_hidden', false)->orWhere('user_id', $request->user()?->id ?? 0)))
            ->latest()->paginate(10, ['*'], 'updates_page')->withQueryString();
        $updates->through(fn ($u) => $u->only('id', 'title', 'is_hidden', 'user_id') + [
            'author' => $this->person($u->user), 'html' => app(MarkdownService::class)->render($u->body), 'date' => $u->created_at->toISOString(),
        ]);

        $partners = CollaborationApplication::query()
            ->with(['applicantPost.user', 'collaborationRequest.post.user'])
            ->where('status', 'accepted')->whereNotNull('applicant_post_id')
            ->where(fn ($query) => $query->where('applicant_post_id', $post->id)
                ->orWhereHas('collaborationRequest', fn ($q) => $q->where('post_id', $post->id)))
            ->get()
            ->map(fn (CollaborationApplication $application) => $application->applicant_post_id === $post->id
                ? $application->collaborationRequest?->post : $application->applicantPost)
            ->filter(fn (?Post $partner) => $partner && $partner->id !== $post->id && $partner->visibility === 'public'
                && ! $partner->is_hidden && $partner->moderation_status === 'approved' && ! $partner->user?->is_banned)
            ->unique('id')->values()->map(fn (Post $partner) => $this->card($partner));
        $postTags = collect($post->tags ?? [])->map(fn ($tag) => Str::lower((string) $tag))->filter();
        $related = $this->publicWorks()->whereKeyNot($post->id)->latest()->take(24)->get()
            ->sortByDesc(fn (Post $candidate) => $postTags->intersect(collect($candidate->tags ?? [])->map(fn ($tag) => Str::lower((string) $tag)))->count())
            ->take(3)->values()->map(fn (Post $candidate) => $this->card($candidate));

        return Inertia::render('Work', [
            'work' => $this->card($post) + $post->only('external_url', 'repository_url', 'license', 'is_hidden', 'moderation_status') + [
                'html' => app(MarkdownService::class)->render($post->body_markdown),
                'gallery' => array_values(array_unique(array_filter(array_merge([$post->cover_url], $post->album_urls ?? [])))),
                'following' => $request->user() && $post->followers()->where('users.id', $request->user()->id)->exists(),
                'attachments' => $post->attachments->map(fn ($a) => $a->only('id', 'original_name', 'kind', 'path')),
            ],
            'comments' => Inertia::scroll($comments), 'updates' => Inertia::scroll($updates),
            'canEdit' => $request->user()?->can('update', $post) ?? false,
            'isOwner' => $post->user_id === $request->user()?->id,
            'members' => $post->members()->with('user')->where('status', 'active')->whereHas('user', fn ($q) => $q->where('is_banned', false))->get()
                ->map(fn ($m) => $m->only('id', 'can_edit', 'role') + ['user' => $this->person($m->user)]),
            'openings' => $post->collaborationRequests()->with('user', 'post')->visibleTo($request->user())->get()->map(fn ($o) => $this->opening($o)),
            'partners' => $partners,
            'related' => $related,
        ]);
    }

    public function profile(Request $request, string $slug): Response
    {
        $request->validate(['q' => 'nullable|string|max:100', 'kind' => 'nullable|in:projects,works,questions,posts,all', 'view' => 'nullable|in:overview,work,collaborations,wall']);
        $user = User::where('slug', $slug)->firstOrFail();
        $owner = $request->user()?->id === $user->id;
        $term = trim($request->string('q')->toString());
        $kind = $request->string('kind')->toString() ?: 'all';
        $view = $request->string('view')->toString() ?: 'overview';
        $query = $owner ? Post::with('user')->where('user_id', $user->id) : $this->publicWorks()->where('user_id', $user->id);
        $works = $query->when($user->is_banned, fn ($q) => $q->whereRaw('1=0'))
            ->when($kind === 'projects', fn ($q) => $q->where('type', 'post')->where('is_project', true))
            ->when($kind === 'works', fn ($q) => $q->where('type', 'post')->where('is_project', false))
            ->when($kind === 'questions', fn ($q) => $q->where('type', 'question'))
            ->when($kind === 'posts', fn ($q) => $q->whereRaw('1=0'))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%$term%")
                ->orWhere('subtitle', 'like', "%$term%")
                ->orWhere('body_markdown', 'like', "%$term%")))
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$user->featured_post_id ?? 0])->latest()->paginate(12)->withQueryString();
        $works->through(fn ($p) => $this->card($p));
        $showcaseQuery = $user->showcaseProjects()->with('user');
        if (! $owner) {
            $showcaseQuery->where('visibility', 'public')->where('is_hidden', false)->where('moderation_status', 'approved');
        }
        $showcase = $user->is_banned ? collect() : $showcaseQuery->get()->map(fn ($post) => $this->card($post));
        $wallPosts = ProfileWallPost::with('user')->where('profile_user_id', $user->id)->where('is_hidden', false)->where('moderation_status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('is_banned', false))->latest()->take(50)->get()->map(fn ($post) => [
                'id' => $post->id, 'body' => $post->body, 'date' => $post->created_at?->toISOString(), 'author' => $this->person($post->user),
                'can_edit' => $request->user()?->id === $post->user_id,
                'can_delete' => $request->user() && ($request->user()->id === $user->id || $request->user()->id === $post->user_id || $request->user()->isAdmin()),
            ]);
        $stats = [
            'followers' => DB::table('user_follows')->where('following_id', $user->id)->count(),
            'upvotes' => DB::table('post_upvotes')->join('posts', 'posts.id', '=', 'post_upvotes.post_id')->where('posts.user_id', $user->id)
                ->where('posts.visibility', 'public')->where('posts.is_hidden', false)->where('posts.moderation_status', 'approved')->count(),
            'collaborations' => CollaborationApplication::where('status', 'accepted')->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereHas('collaborationRequest', fn ($requestQuery) => $requestQuery->where('user_id', $user->id)))->count(),
            'published' => $user->is_banned ? 0 : $this->publicWorks()->where('user_id', $user->id)->count(),
            'completed' => $user->is_banned ? 0 : $this->publicWorks()->where('user_id', $user->id)->where('status', 'done')->count(),
        ];
        $githubReadme = $user->is_banned ? null : app(GitHubReadmeService::class)->get($user->github_readme_repository);
        $readme = $githubReadme['markdown'] ?? $user->profile_readme;
        $metaDescription = Str::limit(trim((string) ($user->headline ?: $user->bio ?: "Projects, work and achievements by {$user->name}.")), 160);
        $rawMetaImage = trim((string) ($user->banner_url ?: $user->avatar));
        $metaImage = $rawMetaImage === '' ? null : (Str::startsWith($rawMetaImage, ['http://', 'https://']) ? $rawMetaImage : url('/'.ltrim($rawMetaImage, '/')));
        $profileMeta = ['title' => $user->name, 'description' => $metaDescription, 'url' => route('profile.show', $user->slug), 'image' => $metaImage];

        $badgeCatalog = app(BadgeCatalogService::class)->all();

        return Inertia::render('Profile', [
            'person' => $this->person($user) + ['featured_post_id' => $user->featured_post_id, 'is_banned' => $user->is_banned, 'wall_mode' => $user->wall_mode,
                'profile_highlights' => $user->profile_highlights,
                'allow_follow' => $user->connections_allow_follow,
                'following' => $request->user() && DB::table('user_follows')->where('follower_id', $request->user()->id)->where('following_id', $user->id)->exists()],
            'badges' => app(BadgePayloadService::class)->forUser($user, $badgeCatalog),
            'badgeCatalog' => $request->user()?->isAdmin() ? $badgeCatalog : [],
            'isOwner' => $owner, 'works' => Inertia::scroll($works), 'workFilter' => ['kind' => $kind, 'q' => $term], 'view' => $view,
            'stats' => $stats, 'showcase' => $showcase, 'profileReadmeHtml' => app(MarkdownService::class)->render((string) $readme), 'meta' => $profileMeta,
            'profileReadmeSource' => $githubReadme ? ['repository' => $githubReadme['repository'], 'url' => $githubReadme['url']] : null, 'wallPosts' => $wallPosts,
            'openings' => CollaborationRequest::with('user', 'post')->withCount('applications')->visibleTo($request->user())->where('user_id', $user->id)->where('status', 'open')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->latest()->take(20)->get()->map(fn ($o) => $this->opening($o)),
            'contributions' => $this->publicWorks()->where('user_id', '!=', $user->id)->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('accepted_at'))
                ->when($user->is_banned, fn ($q) => $q->whereRaw('1=0'))->latest()->take(20)->get()->map(fn ($p) => $this->card($p)),
        ])->withViewData('profileMeta', $profileMeta);
    }

    public function people(Request $request): Response
    {
        $request->validate(['q' => 'nullable|string|max:80']);
        $term = trim($request->string('q')->toString());
        $people = User::where('is_banned', false)->where('open_to_help', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$term%")->orWhere('skills', 'like', "%$term%")->orWhere('bio', 'like', "%$term%")))
            ->orderBy('name')->paginate(18)->withQueryString()->through(fn ($u) => $this->person($u));

        return Inertia::render('People', ['people' => Inertia::scroll($people), 'q' => $term]);
    }

    public function choose(): Response
    {
        return Inertia::render('Create');
    }

    public function editor(Request $request, ?string $slug = null): Response
    {
        $post = $slug ? Post::where('slug', $slug)->firstOrFail() : null;
        if ($post) {
            Gate::authorize('update', $post);
        }

        $categories = collect(config('projects.categories'));
        if ($post?->category && ! $categories->has($post->category)) {
            $categories->put($post->category, config('projects.legacy_categories.'.$post->category, $post->category));
        }

        return Inertia::render('Editor', [
            'post' => $post ? $post->only('id', 'slug', 'title', 'subtitle', 'body_markdown', 'type', 'is_project', 'feedback_mode', 'category', 'tags', 'status', 'visibility', 'external_url', 'repository_url', 'license', 'media_type', 'updated_at', 'nsfw') : null,
            'kind' => $post ? ($post->is_project ? 'project' : 'work') : ($request->query('kind') === 'project' ? 'project' : 'work'),
            'categories' => $categories->map(fn ($key) => __($key)),
            'mediaTypes' => collect(config('projects.media_types'))->map(fn ($key) => __($key)),
            'licenses' => collect(config('projects.licenses'))->map(fn ($key) => __($key)),
            'journal' => null,
        ]);
    }

    public function journalEditor(Request $request, string $slug, ?ProjectUpdate $projectUpdate = null): Response
    {
        $post = Post::where('slug', $slug)->where('type', 'post')->where('is_project', true)->firstOrFail();
        Gate::authorize('update', $post);
        if ($projectUpdate) {
            abort_unless($projectUpdate->post_id === $post->id, 404);
            abort_unless($post->user_id === $request->user()->id || $projectUpdate->user_id === $request->user()->id, 403);
        }

        return Inertia::render('Editor', ['post' => null, 'kind' => 'update', 'categories' => [], 'mediaTypes' => [], 'licenses' => [],
            'journal' => ['project' => $post->only('id', 'slug', 'title'), 'update' => $projectUpdate?->only('id', 'title', 'body', 'updated_at')]]);
    }

    public function collaborations(Request $request): Response
    {
        $service = app(CollaborationService::class);
        $requests = $service->requests($request->only('status', 'role', 'availability', 'format', 'scope', 'q'), $request->user());
        // The query service returns a collection; expose only public presentation fields.
        $requests->through(fn ($o) => $this->opening($o));

        return Inertia::render('Collaborations', ['openings' => Inertia::scroll($requests),
            'roles' => $service->roleOptions(), 'availability' => $service->availabilityOptions(), 'formats' => $service->formatOptions(),
            'filters' => $request->only('status', 'role', 'availability', 'format', 'scope', 'q')]);
    }

    public function helpEditor(Request $request, ?CollaborationRequest $collaborationRequest = null): Response
    {
        $service = app(CollaborationService::class);
        if ($collaborationRequest) {
            abort_unless($collaborationRequest->user_id === $request->user()->id, 403);
        }

        return Inertia::render('HelpEditor', ['roles' => $service->roleOptions(), 'availability' => $service->availabilityOptions(), 'formats' => $service->formatOptions(),
            'projects' => $service->manageableProjects($request->user())->map(fn ($p) => $p->only('id', 'title')), 'projectId' => (string) ($collaborationRequest?->post_id ?? $request->query('project', '')),
            'opening' => $collaborationRequest?->only('id', 'title', 'role', 'summary', 'availability', 'format', 'skills')]);
    }

    public function collaboration(Request $request, CollaborationRequest $collaborationRequest): Response
    {
        abort_unless(CollaborationRequest::visibleTo($request->user())->whereKey($collaborationRequest->id)->exists(), 404);
        $collaborationRequest->load('user', 'post');
        $owner = $collaborationRequest->user_id === $request->user()?->id;
        $applications = $collaborationRequest->applications()->with('user', 'applicantPost', 'messages.user')
            ->when(! $owner, fn ($q) => $q->where('user_id', $request->user()?->id ?? 0))->latest()->get();

        return Inertia::render('Collaboration', ['opening' => $this->opening($collaborationRequest), 'isOwner' => $owner,
            'applications' => $applications->map(fn ($a) => $a->only('id', 'status', 'message') + ['user' => $this->person($a->user),
                'project' => $a->applicantPost?->only('id', 'slug', 'title'),
                'messages' => $a->messages->map(fn ($message) => $message->only('id', 'body') + [
                    'author' => $this->person($message->user), 'date' => $message->created_at->toISOString(),
                ]),
            ]),
            'comments' => $collaborationRequest->comments()->with('user')->whereHas('user', fn ($q) => $q->where('is_banned', false))->oldest()->get()
                ->map(fn ($c) => $c->only('id', 'body') + ['author' => $this->person($c->user), 'date' => $c->created_at->toISOString(),
                    'can_edit' => $request->user()?->id === $c->user_id, 'can_delete' => $request->user()?->id === $c->user_id || ($request->user()?->isAdmin() ?? false)]),
            'candidateProjects' => $request->user() && ! $owner
                ? app(CollaborationService::class)->manageableProjects($request->user())->reject(fn ($project) => $project->id === $collaborationRequest->post_id)
                    ->map(fn ($project) => $project->only('id', 'title'))->values()
                : [],
        ]);
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();
        $twoFactor = app(TwoFactorService::class);
        $pendingSecret = $user->two_factor_secret && ! $user->two_factor_confirmed_at ? (string) $user->two_factor_secret : null;

        return Inertia::render('Settings', ['person' => $this->person($user) + $user->only(
            'featured_post_id', 'email', 'email_verified_at', 'privacy_allow_mentions',
            'notify_comments', 'notify_reviews', 'notify_follows', 'connections_allow_follow',
            'connections_show_follow_counts', 'security_login_alerts', 'headline', 'profile_readme', 'profile_highlights', 'profile_links', 'github_readme_repository', 'wall_mode'
        ),
            'projects' => $user->posts()->where('type', 'post')->latest()->get(['id', 'title', 'is_project']),
            'showcaseProjectIds' => $user->showcaseProjects()->pluck('posts.id'),
            'twoFactor' => [
                'enabled' => (bool) $user->two_factor_confirmed_at,
                'pending' => (bool) $pendingSecret,
                'secret' => $pendingSecret,
                'qr' => $pendingSecret ? $twoFactor->qr($user, $pendingSecret) : null,
                'uri' => $pendingSecret ? $twoFactor->uri($user, $pendingSecret) : null,
                'recoveryCodes' => $request->session()->pull('two_factor_recovery_codes', []),
            ],
        ]);
    }

    public function saved(Request $request): Response
    {
        $works = $this->publicWorks()->whereHas('savers', fn ($q) => $q->where('users.id', $request->user()->id))->latest()->paginate(12)->through(fn ($p) => $this->card($p));

        return Inertia::render('Saved', ['works' => Inertia::scroll($works)]);
    }

    public function notifications(Request $request): Response
    {
        return Inertia::render('Notifications', ['notifications' => Inertia::scroll($request->user()->notifications()->latest()->paginate(25))]);
    }

    public function moderation(Request $request): Response
    {
        $all = $request->query('filter') === 'all';
        $items = ContentReport::query()
            ->selectRaw('content_type, content_id, count(*) as reports_count, count(distinct coalesce(user_id, id)) as reporters_count, coalesce(sum(weight), 0) as weight_total, max(created_at) as last_report_at, max(content_url) as content_url')
            ->when(! $all, fn ($query) => $query->whereIn('resolved_status', ['pending', 'auto_hidden']))
            ->whereNotNull('content_id')
            ->where('content_id', '<>', '')
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('weight_total')
            ->orderByDesc('last_report_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($row) => $this->moderationItem($row));

        return Inertia::render('Moderation', ['items' => Inertia::scroll($items), 'all' => $all]);
    }

    private function moderationItem(ContentReport $row): array
    {
        $type = (string) $row->content_type;
        $contentId = (string) $row->content_id;
        $numericId = ctype_digit($contentId) ? (int) $contentId : null;
        $model = match ($type) {
            'post', 'question' => Post::with('user')
                ->where('type', $type === 'question' ? 'question' : 'post')
                ->where($numericId ? 'id' : 'slug', $numericId ?: $contentId)
                ->first(),
            'comment' => $numericId ? PostComment::with('user')->find($numericId) : null,
            'review' => $numericId ? PostReview::with('user')->find($numericId) : null,
            'profile' => $numericId ? User::find($numericId) : User::where('slug', $contentId)->first(),
            'collaboration' => $numericId ? CollaborationRequest::with('user')->find($numericId) : null,
            'collaboration_comment' => $numericId ? CollaborationComment::with(['user', 'collaborationRequest'])->find($numericId) : null,
            default => null,
        };
        $reports = ContentReport::query()
            ->where('content_type', $type)
            ->where('content_id', $contentId)
            ->latest()
            ->get();
        $openReports = $reports->whereIn('resolved_status', ['pending', 'auto_hidden']);
        $status = $openReports->contains('resolved_status', 'pending')
            ? 'pending'
            : ($openReports->contains('resolved_status', 'auto_hidden') ? 'auto_hidden' : (string) ($reports->first()?->resolved_status ?? 'resolved'));

        $author = match (true) {
            $model instanceof User => $model,
            $model !== null => $model->user,
            default => null,
        };
        $title = match (true) {
            $model instanceof Post => $model->title,
            $model instanceof PostComment => 'Comment on '.$model->post_slug,
            $model instanceof PostReview => 'Review on '.$model->post_slug,
            $model instanceof User => $model->name,
            $model instanceof CollaborationRequest => $model->title,
            $model instanceof CollaborationComment => 'Reply on '.($model->collaborationRequest?->title ?? 'collaboration'),
            $type === 'content' => 'Flagged media',
            default => 'Removed content',
        };
        $excerpt = match (true) {
            $model instanceof Post => $model->subtitle ?: Str::limit(strip_tags(app(MarkdownService::class)->render($model->body_markdown)), 320),
            $model instanceof PostComment, $model instanceof CollaborationComment => $model->body,
            $model instanceof PostReview => implode("\n\n", array_filter([$model->improve, $model->why, $model->how])),
            $model instanceof User => $model->bio,
            $model instanceof CollaborationRequest => $model->summary,
            default => (string) ($reports->first()?->details ?? ''),
        };
        $url = match (true) {
            $model instanceof Post => $model->type === 'question' ? route('questions.show', $model->slug) : route('project', $model->slug),
            $model instanceof PostComment => app(ModerationService::class)->resolvePostUrl($model->post_slug).'#comment-'.$model->id,
            $model instanceof PostReview => app(ModerationService::class)->resolvePostUrl($model->post_slug).'#review-'.$model->id,
            $model instanceof User => route('profile.show', $model->slug),
            $model instanceof CollaborationRequest => route('collaboration.show', $model),
            $model instanceof CollaborationComment => route('collaboration.show', $model->collaboration_request_id).'#comment-'.$model->id,
            default => $row->content_url,
        };
        $canonicalId = $model?->id ? (string) $model->id : $contentId;
        $score = ContentReportScore::query()->where('content_type', $type)->where('content_id', $canonicalId)->first();
        $threshold = (float) ($score?->weight_threshold ?: config('moderation.reports.auto_hide.base_threshold', 16));
        $actionType = $type === 'question' ? 'post' : $type;
        $moderatable = in_array($actionType, ['post', 'comment', 'review'], true);

        return [
            'key' => $type.':'.$contentId,
            'id' => $numericId,
            'type' => $type,
            'title' => $title,
            'excerpt' => Str::limit(trim((string) $excerpt), 700),
            'url' => $url,
            'author' => $author ? $this->person($author) : null,
            'status' => $status,
            'content_status' => $model?->moderation_status ?? null,
            'hidden' => (bool) ($model?->is_hidden ?? false),
            'reports_count' => (int) $row->reports_count,
            'reporters_count' => (int) $row->reporters_count,
            'weight_total' => round((float) $row->weight_total, 1),
            'weight_threshold' => round($threshold, 1),
            'last_report_at' => $row->last_report_at,
            'reasons' => $reports->groupBy('reason')->map(fn ($group, $reason) => [
                'reason' => $reason,
                'count' => $group->count(),
                'details' => $group->first(fn ($report) => trim((string) $report->details) !== '')?->details,
            ])->values(),
            'hide_url' => $moderatable && $numericId ? "/admin/moderation/{$actionType}s/{$numericId}/hide" : null,
            'restore_url' => $moderatable && $numericId ? "/admin/moderation/{$actionType}s/{$numericId}/restore" : null,
            'dismiss_url' => $numericId && in_array($actionType, ['post', 'comment', 'review', 'profile', 'collaboration', 'collaboration_comment'], true)
                ? "/admin/moderation/reports/{$actionType}/{$numericId}/dismiss"
                : null,
        ];
    }

    public function startProject(Request $request, Post $post)
    {
        abort_unless($post->user_id === $request->user()->id && $post->type === 'post', 403);
        $post->update(['is_project' => true]);

        return redirect()->route('project', $post->slug);
    }
}
