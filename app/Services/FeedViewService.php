<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Support\SchemaGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedViewService
{
    private const PAGE_SIZE = 10;

    public function __construct(private VisibilityService $visibility)
    {
    }

    public function buildPageData(?User $viewer, array $projects, array $questions, ?string $filter): array
    {
        $filter = $this->normalizeFilter($filter);
        $useDbFeed = $this->useDbFeed();

        $qaThreads = $useDbFeed ? FeedService::buildQaThreads(12, $viewer) : [];
        if (empty($qaThreads)) {
            $qaThreads = $this->buildDemoQaThreads($questions);
        }

        $feedTags = $useDbFeed ? FeedService::buildFeedTags(15, 200, $viewer) : [];
        $demoTagEntries = $this->buildDemoTagEntries($projects, $questions);
        if (empty($feedTags)) {
            $feedTags = array_slice($demoTagEntries, 0, 15);
        }

        $feedItems = [];
        $projectsTotal = 0;
        $questionsTotal = 0;
        $projectsOffset = 0;
        $questionsOffset = 0;

        if ($useDbFeed) {
            $feedData = FeedService::buildInitialFeed($viewer, self::PAGE_SIZE, $filter);
            $feedItems = $feedData['items'];
            $projectsTotal = $feedData['projects_total'];
            $questionsTotal = $feedData['questions_total'];
            $projectsOffset = $feedData['projects_offset'];
            $questionsOffset = $feedData['questions_offset'];
        } else {
            $streams = $this->buildDemoStreams($projects, $questions, $qaThreads);
            $filteredProjects = $this->applyDemoFilter($streams['projects'], $filter);
            $filteredQuestions = $this->applyDemoFilter($streams['questions'], $filter);

            $projectsTotal = count($filteredProjects);
            $questionsTotal = count($filteredQuestions);

            $projectsPage = array_slice($filteredProjects, 0, self::PAGE_SIZE);
            $questionsPage = array_slice($filteredQuestions, 0, self::PAGE_SIZE);

            $projectsOffset = count($projectsPage);
            $questionsOffset = count($questionsPage);

            $feedItems = array_merge($projectsPage, $questionsPage);
            $sorter = $filter === 'best' ? $this->scoreSorter() : $this->timeSorter();
            usort($feedItems, $sorter);
        }

        return [
            'use_db_feed' => $useDbFeed,
            'feed_items' => $feedItems,
            'feed_tags' => $feedTags,
            'qa_threads' => $qaThreads,
            'top_projects' => $this->buildTopProjects($projects),
            'reading_now' => $this->buildReadingNow($projects),
            'feed_projects_total' => $projectsTotal,
            'feed_questions_total' => $questionsTotal,
            'feed_projects_offset' => $projectsOffset,
            'feed_questions_offset' => $questionsOffset,
            'feed_page_size' => self::PAGE_SIZE,
            'filter' => $filter,
        ];
    }

    public function buildChunkData(
        ?User $viewer,
        array $projects,
        array $questions,
        string $stream,
        int $offset,
        int $limit,
        ?string $filter
    ): array {
        $stream = $stream === 'questions' ? 'questions' : 'projects';
        $filter = $this->normalizeFilter($filter);
        $limit = max(1, min(20, $limit));
        $offset = max(0, $offset);

        if ($this->useDbFeed()) {
            return FeedService::buildFeedChunk($stream, $offset, $limit, $viewer, $filter);
        }

        $qaThreads = $this->buildDemoQaThreads($questions);
        $streams = $this->buildDemoStreams($projects, $questions, $qaThreads);
        $source = $stream === 'questions' ? $streams['questions'] : $streams['projects'];
        if ($filter !== 'all') {
            $source = $this->applyDemoFilter($source, $filter);
        }

        $slice = array_slice($source, $offset, $limit);
        $nextOffset = $offset + count($slice);
        $total = count($source);

        return [
            'items' => $slice,
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $total,
            'total' => $total,
            'stream' => $stream,
        ];
    }

    public function buildSearchIndex(array $projects, array $questions): array
    {
        $useDbFeed = $this->useDbFeed();
        $demoTagEntries = $this->buildDemoTagEntries($projects, $questions);

        return Cache::remember('search.index.v1', now()->addMinutes(5), function () use ($useDbFeed, $projects, $questions, $demoTagEntries) {
            $items = [];

            if ($useDbFeed) {
                $postsQuery = Post::with('user:id,name,slug')
                    ->orderByDesc('created_at');
                $this->visibility->applyToQuery($postsQuery, 'posts', null);
                $posts = $postsQuery
                    ->limit(200)
                    ->get(['id', 'slug', 'title', 'subtitle', 'type', 'user_id', 'tags']);

                foreach ($posts as $post) {
                    $type = $post->type === 'question' ? 'question' : 'post';
                    $items[] = [
                        'type' => $type,
                        'title' => $post->title,
                        'subtitle' => $post->subtitle,
                        'url' => $type === 'question'
                            ? url('/questions/' . $post->slug)
                            : url('/projects/' . $post->slug),
                        'slug' => $post->slug,
                        'author' => $post->user?->name ?? null,
                        'keywords' => is_array($post->tags) ? implode(' ', $post->tags) : null,
                    ];
                }
            } else {
                foreach ($projects as $project) {
                    $items[] = [
                        'type' => 'post',
                        'title' => $project['title'] ?? '',
                        'subtitle' => $project['subtitle'] ?? $project['context'] ?? null,
                        'url' => url('/projects/' . $project['slug']),
                        'slug' => $project['slug'],
                        'author' => $project['author']['name'] ?? null,
                        'keywords' => !empty($project['tags']) ? implode(' ', $project['tags']) : null,
                    ];
                }
                foreach ($questions as $question) {
                    $items[] = [
                        'type' => 'question',
                        'title' => $question['title'] ?? '',
                        'subtitle' => $question['body'] ?? null,
                        'url' => url('/questions/' . $question['slug']),
                        'slug' => $question['slug'],
                        'author' => $question['author']['name'] ?? null,
                        'keywords' => !empty($question['tags']) ? implode(' ', $question['tags']) : null,
                    ];
                }
            }

            $tagEntries = $demoTagEntries;
            if (empty($tagEntries) && $useDbFeed) {
                $tagEntries = FeedService::buildFeedTags(200, 500);
            }
            $tagEntries = array_slice($tagEntries, 0, 200);
            foreach ($tagEntries as $entry) {
                $label = trim((string) ($entry['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $slug = $entry['slug'] ?? Str::slug($label);
                if ($slug === '') {
                    continue;
                }
                $count = (int) ($entry['count'] ?? 0);
                $items[] = [
                    'type' => 'tag',
                    'title' => '#' . $label,
                    'subtitle' => __('ui.search_tag_posts', ['count' => $count]),
                    'url' => url('/?tags=' . $slug),
                    'slug' => $slug,
                    'author' => null,
                    'keywords' => implode(' ', array_filter([$label, '#' . $label, $slug])),
                ];
            }

            if (SchemaGuard::hasTable('users') && User::query()->exists()) {
                $users = User::query()->orderBy('name')->limit(200)->get(['id', 'name', 'slug', 'role', 'bio']);
                foreach ($users as $user) {
                    $slug = $user->slug ?? Str::slug($user->name ?? '');
                    if ($slug === '') {
                        continue;
                    }
                    $items[] = [
                        'type' => 'user',
                        'title' => $user->name ?? '',
                        'subtitle' => $user->role ?? null,
                        'url' => url('/profile/' . $slug),
                        'slug' => $slug,
                        'author' => null,
                        'keywords' => $user->bio ?? null,
                    ];
                }
            } else {
                $demoUsers = [];
                foreach ([$projects, $questions] as $collection) {
                    foreach ($collection as $entry) {
                        $author = $entry['author']['name'] ?? null;
                        if (!$author) {
                            continue;
                        }
                        $slug = $entry['author']['slug'] ?? Str::slug($author);
                        if ($slug === '') {
                            continue;
                        }
                        $role = $entry['author']['role'] ?? null;
                        $demoUsers[$slug] = [
                            'type' => 'user',
                            'title' => $author,
                            'subtitle' => $role,
                            'url' => url('/profile/' . $slug),
                            'slug' => $slug,
                            'author' => null,
                            'keywords' => null,
                        ];
                    }
                }
                $items = array_merge($items, array_values($demoUsers));
            }

            return $items;
        });
    }

    public function buildSubscriptions(?User $viewer): array
    {
        if (!$viewer || !SchemaGuard::hasTable('user_follows') || !SchemaGuard::hasTable('users')) {
            return [];
        }

        $subscriptions = [];
        $followRows = DB::table('user_follows')
            ->join('users', 'user_follows.following_id', '=', 'users.id')
            ->where('user_follows.follower_id', $viewer->id)
            ->orderBy('users.name')
            ->limit(6)
            ->get(['users.id', 'users.name', 'users.slug']);

        foreach ($followRows as $row) {
            $slug = $row->slug ?? Str::slug((string) ($row->name ?? ''));
            if ($slug === '') {
                $slug = 'user-' . $row->id;
            }
            $count = SchemaGuard::hasTable('posts')
                ? DB::table('posts')->where('user_id', $row->id)->count()
                : 0;
            $subscriptions[] = [
                'name' => $row->name,
                'slug' => $slug,
                'count' => (int) $count,
            ];
        }

        return $subscriptions;
    }

    private function useDbFeed(): bool
    {
        return SchemaGuard::hasTable('posts') && Post::query()->exists();
    }

    private function normalizeFilter(?string $filter): string
    {
        $value = strtolower((string) $filter);
        return in_array($value, ['best', 'fresh', 'reading'], true) ? $value : 'all';
    }

    private function buildDemoTagEntries(array $projects, array $questions): array
    {
        $tagBuckets = [];
        foreach ($projects as $project) {
            foreach (($project['tags'] ?? []) as $tag) {
                $label = trim((string) $tag);
                if ($label === '') {
                    continue;
                }
                $key = Str::slug($label);
                if ($key === '') {
                    continue;
                }
                if (!isset($tagBuckets[$key])) {
                    $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
                }
                $tagBuckets[$key]['count'] += 1;
            }
        }
        foreach ($questions as $question) {
            foreach (($question['tags'] ?? []) as $tag) {
                $label = trim((string) $tag);
                if ($label === '') {
                    continue;
                }
                $key = Str::slug($label);
                if ($key === '') {
                    continue;
                }
                if (!isset($tagBuckets[$key])) {
                    $tagBuckets[$key] = ['label' => $label, 'slug' => $key, 'count' => 0];
                }
                $tagBuckets[$key]['count'] += 1;
            }
        }

        $tagEntries = array_values($tagBuckets);
        usort($tagEntries, static function (array $a, array $b): int {
            $countCompare = ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }
            return strcmp($a['label'] ?? '', $b['label'] ?? '');
        });

        return array_values(array_map(
            static fn (array $entry) => [
                'label' => $entry['label'] ?? '',
                'slug' => $entry['slug'] ?? Str::slug($entry['label'] ?? ''),
                'count' => (int) ($entry['count'] ?? 0),
            ],
            $tagEntries,
        ));
    }

    private function buildDemoQaThreads(array $questions): array
    {
        return array_map(static function (array $question) {
            $replies = count($question['answers'] ?? []);
            $score = (int) ($question['score'] ?? 0);
            $deltaValue = '+' . (int) $score;
            if (!str_starts_with($deltaValue, '+')) {
                $deltaValue = '+' . ltrim($deltaValue, '+');
            }
            $minutes = $question['published_minutes'] ?? 0;
            $timeLabel = $question['time'] ?? '';
            if (!$timeLabel && $minutes) {
                $timeLabel = now()->subMinutes($minutes)->diffForHumans();
            }
            return [
                'slug' => $question['slug'] ?? '',
                'title' => $question['title'] ?? '',
                'time' => $timeLabel,
                'minutes' => $minutes,
                'replies' => $replies,
                'delta' => $deltaValue,
            ];
        }, $questions);
    }

    private function buildDemoStreams(array $projects, array $questions, array $qaThreads): array
    {
        $qaThreadMap = collect($qaThreads)->keyBy('slug');
        $feedProjects = [];
        $feedQuestions = [];

        foreach ($projects as $project) {
            $feedProjects[] = [
                'type' => 'project',
                'data' => $project,
                'published_minutes' => (int) ($project['published_minutes'] ?? 0),
            ];
        }

        foreach ($questions as $question) {
            $slug = $question['slug'] ?? '';
            $thread = $slug ? $qaThreadMap->get($slug) : null;
            $replies = $thread['replies'] ?? count($question['answers'] ?? []);
            $question['replies'] = $replies;
            $question['score'] = (int) ($question['score'] ?? 0);
            $feedQuestions[] = [
                'type' => 'question',
                'data' => $question,
                'published_minutes' => (int) ($question['published_minutes'] ?? 0),
            ];
        }

        usort($feedProjects, $this->timeSorter());
        usort($feedQuestions, $this->timeSorter());

        return [
            'projects' => $feedProjects,
            'questions' => $feedQuestions,
        ];
    }

    private function applyDemoFilter(array $items, string $filter): array
    {
        if ($filter === 'fresh') {
            return array_values(array_filter($items, static function (array $item): bool {
                return (int) ($item['published_minutes'] ?? 0) <= 180;
            }));
        }
        if ($filter === 'reading') {
            return array_values(array_filter($items, static function (array $item): bool {
                return (int) ($item['data']['read_time_minutes'] ?? 0) >= 8;
            }));
        }
        if ($filter === 'best') {
            $sorted = $items;
            usort($sorted, $this->scoreSorter());
            return array_values($sorted);
        }
        return $items;
    }

    private function timeSorter(): callable
    {
        return static function (array $a, array $b): int {
            return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
        };
    }

    private function scoreSorter(): callable
    {
        return static function (array $a, array $b): int {
            $scoreA = (int) ($a['data']['score'] ?? 0);
            $scoreB = (int) ($b['data']['score'] ?? 0);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }
            return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
        };
    }

    private function buildTopProjects(array $projects): array
    {
        return Cache::remember('feed.top_projects.v1', now()->addMinutes(10), function () use ($projects) {
            if (SchemaGuard::hasTable('posts') && SchemaGuard::hasTable('post_upvotes')) {
                $rowsQuery = DB::table('posts')
                    ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
                    ->where('posts.type', 'post')
                    ->groupBy('posts.id', 'posts.slug', 'posts.title')
                    ->select('posts.slug', 'posts.title', DB::raw('count(post_upvotes.id) as score'))
                    ->orderByDesc('score')
                    ->orderBy('posts.title')
                    ->limit(4);
                $this->visibility->applyToQuery($rowsQuery, 'posts', null);
                $rows = $rowsQuery->get();

                $entries = [];
                foreach ($rows as $row) {
                    $entries[] = [
                        'slug' => $row->slug,
                        'title' => $row->title,
                        'score' => (int) ($row->score ?? 0),
                    ];
                }

                if (!empty($entries)) {
                    return $entries;
                }
            }

            return collect($projects)
                ->map(static fn (array $project) => [
                    'slug' => $project['slug'],
                    'title' => $project['title'],
                    'score' => (int) ($project['score'] ?? 0),
                ])
                ->sortByDesc('score')
                ->take(4)
                ->values()
                ->all();
        });
    }

    private function buildReadingNow(array $projects): array
    {
        return Cache::remember('feed.reading_now.v2', now()->addMinutes(2), function () use ($projects) {
            if (!SchemaGuard::hasTable('reading_activity') && !SchemaGuard::hasTable('reading_progress')) {
                return [];
            }

            $windowMinutes = 10;
            if (SchemaGuard::hasTable('reading_activity')) {
                $rows = DB::table('reading_activity')
                    ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
                    ->groupBy('post_id')
                    ->select('post_id', DB::raw('count(distinct ip_hash) as readers'), DB::raw('max(updated_at) as last_read'))
                    ->orderByDesc('readers')
                    ->orderByDesc('last_read')
                    ->limit(3)
                    ->get();
            } else {
                $rows = DB::table('reading_progress')
                    ->where('updated_at', '>=', now()->subMinutes($windowMinutes))
                    ->groupBy('post_id')
                    ->select('post_id', DB::raw('count(*) as readers'), DB::raw('max(updated_at) as last_read'))
                    ->orderByDesc('readers')
                    ->orderByDesc('last_read')
                    ->limit(3)
                    ->get();
            }

            $postIds = $rows
                ->pluck('post_id')
                ->filter(static fn ($id) => is_numeric($id))
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $postSlugs = $rows
                ->pluck('post_id')
                ->filter(static fn ($id) => is_string($id) && !is_numeric($id))
                ->map(static fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all();

            $postMapById = collect();
            $postMapBySlug = collect();
            if (SchemaGuard::hasTable('posts')) {
                if (!empty($postIds)) {
                    $postQuery = Post::whereIn('id', $postIds);
                    $this->visibility->applyToQuery($postQuery, 'posts', null);
                    $postMapById = $postQuery->get(['id', 'slug', 'title'])->keyBy('id');
                }
                if (!empty($postSlugs)) {
                    $postQuery = Post::whereIn('slug', $postSlugs);
                    $this->visibility->applyToQuery($postQuery, 'posts', null);
                    $postMapBySlug = $postQuery->get(['id', 'slug', 'title'])->keyBy('slug');
                }
            }

            $projectMap = collect($projects)->keyBy('slug');
            $entries = [];
            foreach ($rows as $row) {
                $postId = $row->post_id ?? null;
                if ($postId === null) {
                    continue;
                }
                $slug = '';
                $title = '';
                if (is_numeric($postId)) {
                    $post = $postMapById->get((int) $postId);
                    $slug = $post?->slug ?? '';
                    $title = $post?->title ?? '';
                } else {
                    $post = $postMapBySlug->get((string) $postId);
                    if ($post) {
                        $slug = $post->slug ?? '';
                        $title = $post->title ?? '';
                    } else {
                        $project = $projectMap->get((string) $postId);
                        $slug = $project['slug'] ?? '';
                        $title = $project['title'] ?? '';
                    }
                }
                if ($slug === '' || $title === '') {
                    continue;
                }
                $entries[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'readers' => (int) ($row->readers ?? 0),
                    'last_read' => $row->last_read,
                ];
            }

            return $entries;
        });
    }
}
