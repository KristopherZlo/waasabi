<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FeedService
{
    private const TAG_SCAN_LIMIT = 200;
    private const LEGACY_COVERS = [
        '/images/cover-1.svg',
        '/images/cover-2.svg',
        '/images/cover-3.svg',
        '/images/cover-4.svg',
    ];

    public static function buildInitialFeed(?User $viewer, int $pageSize, string $filter = 'all'): array
    {
        $filter = self::normalizeFeedFilter($filter);
        $projects = self::fetchPosts('post', 0, $pageSize, $viewer, $filter);
        $questions = self::fetchPosts('question', 0, $pageSize, $viewer, $filter);
        $items = array_merge($projects, $questions);
        if ($filter === 'best') {
            usort($items, static function (array $a, array $b): int {
                $scoreA = (int) ($a['data']['score'] ?? 0);
                $scoreB = (int) ($b['data']['score'] ?? 0);
                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }
                return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
            });
        } else {
            usort($items, static function (array $a, array $b): int {
                return ($a['published_minutes'] ?? 0) <=> ($b['published_minutes'] ?? 0);
            });
        }

        return [
            'items' => $items,
            'projects_total' => self::getTypeCount('post', $filter),
            'questions_total' => self::getTypeCount('question', $filter),
            'projects_offset' => count($projects),
            'questions_offset' => count($questions),
        ];
    }

    public static function buildFeedChunk(string $stream, int $offset, int $limit, ?User $viewer, string $filter = 'all'): array
    {
        $stream = in_array($stream, ['projects', 'questions'], true) ? $stream : 'projects';
        $type = $stream === 'questions' ? 'question' : 'post';
        $filter = self::normalizeFeedFilter($filter);
        $items = self::fetchPosts($type, $offset, $limit, $viewer, $filter);
        $nextOffset = $offset + count($items);
        $total = self::getTypeCount($type, $filter);

        return [
            'items' => $items,
            'next_offset' => $nextOffset,
            'has_more' => $nextOffset < $total,
            'total' => $total,
            'stream' => $stream,
        ];
    }

    public static function buildFeedTags(int $limit = 15, int $scanLimit = self::TAG_SCAN_LIMIT, ?User $viewer = null): array
    {
        $cacheKey = "feed.tags.$limit.$scanLimit." . (self::isModerator($viewer) ? 'mod' : 'public');
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($limit, $scanLimit, $viewer) {
            $rows = Post::query()
                ->select('tags')
                ->when(!self::isModerator($viewer), function ($query) use ($viewer) {
                    self::applyVisibilityFilters($query, 'posts', $viewer);
                })
                ->whereNotNull('tags')
                ->orderByDesc('created_at')
                ->limit($scanLimit)
                ->get();

            $tagBuckets = [];
            foreach ($rows as $row) {
                foreach (($row->tags ?? []) as $tag) {
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
                array_slice($tagEntries, 0, $limit),
            ));
        });
    }

    public static function buildQaThreads(int $limit = 12, ?User $viewer = null): array
    {
        $cacheKey = "feed.qa_threads.$limit." . (self::isModerator($viewer) ? 'mod' : 'public');
        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($limit, $viewer) {
            $questions = Post::query()
                ->where('type', 'question')
                ->when(!self::isModerator($viewer), function ($query) use ($viewer) {
                    self::applyVisibilityFilters($query, 'posts', $viewer);
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'slug', 'title', 'created_at']);

            if ($questions->isEmpty()) {
                return [];
            }

            $postIds = $questions->pluck('id')->all();
            $slugs = $questions->pluck('slug')->all();

            $upvoteCounts = self::countByColumn('post_upvotes', 'post_id', $postIds);
            $commentCounts = self::countByColumn(
                'post_comments',
                'post_slug',
                $slugs,
                self::visibilityFiltersForTable('post_comments', $viewer),
            );

            return $questions->map(static function (Post $post) use ($upvoteCounts, $commentCounts) {
                $score = $upvoteCounts[$post->id] ?? 0;
                return [
                    'slug' => $post->slug,
                    'title' => $post->title,
                    'time' => $post->created_at?->diffForHumans() ?? '',
                    'minutes' => $post->created_at?->diffInMinutes() ?? 0,
                    'replies' => $commentCounts[$post->slug] ?? 0,
                    'delta' => '+' . (int) $score,
                ];
            })->values()->all();
        });
    }

    public static function preparePostStats(iterable $posts, ?User $viewer): array
    {
        $postIds = [];
        $slugs = [];

        foreach ($posts as $post) {
            if ($post instanceof Post) {
                $postIds[] = $post->id;
                $slugs[] = $post->slug;
                continue;
            }
            if (is_array($post)) {
                if (array_key_exists('id', $post)) {
                    $postIds[] = $post['id'];
                }
                if (array_key_exists('slug', $post)) {
                    $slugs[] = $post['slug'];
                }
            }
        }

        $postIds = array_values(array_unique(array_filter($postIds, static function ($id) {
            return $id !== null && $id !== '';
        })));
        $postIds = array_map(static function ($id) {
            return is_numeric($id) ? (int) $id : $id;
        }, $postIds);

        $slugs = array_values(array_unique(array_filter($slugs, static function ($slug) {
            return $slug !== null && $slug !== '';
        })));

        $reportCountsById = self::countByColumn(
            'content_reports',
            'content_id',
            array_map(static fn ($id) => (string) $id, $postIds),
        );
        $reportCountsBySlug = self::countByColumn('content_reports', 'content_id', $slugs);
        $hasWeightedReports = self::safeHasColumn('content_reports', 'weight');
        $reportWeightsById = $hasWeightedReports
            ? self::sumByColumn(
                'content_reports',
                'content_id',
                'weight',
                array_map(static fn ($id) => (string) $id, $postIds),
            )
            : $reportCountsById;
        $reportWeightsBySlug = $hasWeightedReports
            ? self::sumByColumn('content_reports', 'content_id', 'weight', $slugs)
            : $reportCountsBySlug;

        return [
            'upvotes' => self::countByColumn('post_upvotes', 'post_id', $postIds),
            'saves' => self::countByColumn('post_saves', 'post_id', $postIds),
            'comments' => self::countByColumn(
                'post_comments',
                'post_slug',
                $slugs,
                self::visibilityFiltersForTable('post_comments', $viewer),
            ),
            'user_upvoted_ids' => $viewer ? self::idsForUser('post_upvotes', 'post_id', $viewer->id, $postIds) : [],
            'user_saved_ids' => $viewer ? self::idsForUser('post_saves', 'post_id', $viewer->id, $postIds) : [],
            'report_counts_by_id' => $reportCountsById,
            'report_counts_by_slug' => $reportCountsBySlug,
            'report_weights_by_id' => $reportWeightsById,
            'report_weights_by_slug' => $reportWeightsBySlug,
        ];
    }

    private static function fetchPosts(string $type, int $offset, int $limit, ?User $viewer, string $filter = 'all'): array
    {
        $filter = self::normalizeFeedFilter($filter);
        $query = Post::with(['user', 'editedBy'])->where('type', $type);
        self::applyVisibilityFilters($query, 'posts', $viewer);

        if ($filter === 'fresh') {
            $query->where('created_at', '>=', now()->subMinutes(180));
        }

        if ($filter === 'reading') {
            $query->where('read_time_minutes', '>=', 8);
        }

        if ($filter === 'best' && self::safeHasTable('post_upvotes')) {
            $query->orderByDesc(
                DB::table('post_upvotes')
                    ->selectRaw('count(*)')
                    ->whereColumn('post_upvotes.post_id', 'posts.id'),
            );
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $posts = $query->skip($offset)->take($limit)->get();

        if ($posts->isEmpty()) {
            return [];
        }

        $stats = self::preparePostStats($posts, $viewer);
        $upvoteCounts = $stats['upvotes'] ?? [];
        $saveCounts = $stats['saves'] ?? [];
        $commentCounts = $stats['comments'] ?? [];
        $userUpvotedIds = $stats['user_upvoted_ids'] ?? [];
        $userSavedIds = $stats['user_saved_ids'] ?? [];
        $reportCountsById = $stats['report_counts_by_id'] ?? [];
        $reportCountsBySlug = $stats['report_counts_by_slug'] ?? [];
        $reportWeightsById = $stats['report_weights_by_id'] ?? $reportCountsById;
        $reportWeightsBySlug = $stats['report_weights_by_slug'] ?? $reportCountsBySlug;

        return $posts
            ->map(function (Post $post) use ($type, $upvoteCounts, $saveCounts, $commentCounts, $userUpvotedIds, $userSavedIds, $reportCountsById, $reportCountsBySlug, $reportWeightsById, $reportWeightsBySlug) {
                if ($type === 'question') {
                    $data = self::mapPostToQuestion(
                        $post,
                        $upvoteCounts,
                        $saveCounts,
                        $commentCounts,
                        $userUpvotedIds,
                        $userSavedIds,
                        $reportCountsById,
                        $reportCountsBySlug,
                        $reportWeightsById,
                        $reportWeightsBySlug,
                    );
                    return [
                        'type' => 'question',
                        'data' => $data,
                        'published_minutes' => (int) ($data['published_minutes'] ?? 0),
                    ];
                }

                $data = self::mapPostToProject(
                    $post,
                    $upvoteCounts,
                    $saveCounts,
                    $commentCounts,
                    $userUpvotedIds,
                    $userSavedIds,
                    $reportCountsById,
                    $reportCountsBySlug,
                    $reportWeightsById,
                    $reportWeightsBySlug,
                );
                return [
                    'type' => 'project',
                    'data' => $data,
                    'published_minutes' => (int) ($data['published_minutes'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public static function mapPostToProjectWithStats(Post $post, array $stats): array
    {
        return self::mapPostToProject(
            $post,
            $stats['upvotes'] ?? [],
            $stats['saves'] ?? [],
            $stats['comments'] ?? [],
            $stats['user_upvoted_ids'] ?? [],
            $stats['user_saved_ids'] ?? [],
            $stats['report_counts_by_id'] ?? [],
            $stats['report_counts_by_slug'] ?? [],
            $stats['report_weights_by_id'] ?? ($stats['report_counts_by_id'] ?? []),
            $stats['report_weights_by_slug'] ?? ($stats['report_counts_by_slug'] ?? []),
        );
    }

    private static function mapPostToProject(
        Post $post,
        array $upvoteCounts,
        array $saveCounts,
        array $commentCounts,
        array $userUpvotedIds,
        array $userSavedIds,
        array $reportCountsById,
        array $reportCountsBySlug,
        array $reportWeightsById,
        array $reportWeightsBySlug
    ): array {
        $author = $post->user;
        $editedAt = $post->updated_at;
        $createdAt = $post->created_at;
        $wasEdited = !empty($post->edited_by) && $editedAt && $createdAt && $editedAt->gt($createdAt);
        $editor = $post->editedBy;
        $editedBy = null;
        $editedAtLabel = null;
        if ($wasEdited) {
            $editorName = $editor?->name ?? $author?->name ?? __('ui.project.anonymous');
            $editedBy = [
                'id' => $editor?->id,
                'name' => $editorName,
                'slug' => $editor?->slug ?? Str::slug($editorName),
                'role' => $editor?->role ?? $author?->role ?? 'user',
                'avatar' => $editor?->avatar ?? '/images/avatar-default.svg',
            ];
            $editedAtLabel = $editedAt?->diffForHumans();
        }
        $bodyMarkdown = self::normalizeMarkdownSource($post->body_markdown, $post->body_html);
        $statusKey = self::normalizeStatusKey($post->status ?? 'in-progress');
        $statusLabel = match ($statusKey) {
            'done' => __('ui.project.status_done'),
            'paused' => __('ui.project.status_paused'),
            default => __('ui.project.status_in_progress'),
        };
        $readMinutes = max(1, (int) ($post->read_time_minutes ?? 1));
        $subtitle = $post->subtitle ?: Str::limit(strip_tags($bodyMarkdown), 420);
        $album = self::normalizeAlbum($post->album_urls);
        $cover = self::normalizeCover($post->cover_url);
        if (empty($post->cover_url) && !empty($album)) {
            $cover = $album[0];
        }

        $reportCount = (int) ($reportCountsById[(string) $post->id] ?? 0)
            + (int) ($reportCountsBySlug[$post->slug] ?? 0);
        $reportPoints = (float) ($reportWeightsById[(string) $post->id] ?? 0)
            + (float) ($reportWeightsBySlug[$post->slug] ?? 0);
        if ($reportPoints <= 0) {
            $reportPoints = (float) $reportCount;
        }

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'subtitle' => $subtitle,
            'context' => $subtitle,
            'published' => $post->created_at?->diffForHumans() ?? __('ui.project.today'),
            'published_minutes' => $post->created_at?->diffInMinutes() ?? 0,
            'score' => $upvoteCounts[$post->id] ?? 0,
            'returns' => 0,
            'saves' => $saveCounts[$post->id] ?? 0,
            'read_time' => $readMinutes . ' min',
            'read_time_minutes' => $readMinutes,
            'cover' => $cover,
            'album' => $album,
            'media' => 'media--grid',
            'status_key' => $statusKey,
            'status' => $statusLabel,
            'nsfw' => (bool) ($post->nsfw ?? false),
            'is_hidden' => (bool) ($post->is_hidden ?? false),
            'moderation_status' => (string) ($post->moderation_status ?? 'approved'),
            'tags' => $post->tags ?? [],
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name ?? __('ui.project.anonymous'),
                'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                'role' => $author?->role ?? 'user',
                'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
            ],
            'author_id' => $author?->id,
            'edited' => $wasEdited,
            'edited_at' => $editedAtLabel,
            'edited_by' => $editedBy,
            'comments' => [],
            'comments_count' => $commentCounts[$post->slug] ?? 0,
            'reviews' => [],
            'reviewer' => [
                'name' => __('ui.project.reviewer_default_name'),
                'role' => 'maker',
                'note' => __('ui.project.reviewer_default_note'),
                'avatar' => '/images/avatar-default.svg',
            ],
            'sections' => [],
            'body_html' => '',
            'body_markdown' => $bodyMarkdown,
            'is_db' => true,
            'is_upvoted' => in_array($post->id, $userUpvotedIds, true),
            'is_saved' => in_array($post->id, $userSavedIds, true),
            'report_count' => $reportCount,
            'report_points' => round($reportPoints, 1),
        ];
    }

    public static function mapPostToQuestionWithStats(Post $post, array $stats): array
    {
        return self::mapPostToQuestion(
            $post,
            $stats['upvotes'] ?? [],
            $stats['saves'] ?? [],
            $stats['comments'] ?? [],
            $stats['user_upvoted_ids'] ?? [],
            $stats['user_saved_ids'] ?? [],
            $stats['report_counts_by_id'] ?? [],
            $stats['report_counts_by_slug'] ?? [],
            $stats['report_weights_by_id'] ?? ($stats['report_counts_by_id'] ?? []),
            $stats['report_weights_by_slug'] ?? ($stats['report_counts_by_slug'] ?? []),
        );
    }

    private static function mapPostToQuestion(
        Post $post,
        array $upvoteCounts,
        array $saveCounts,
        array $commentCounts,
        array $userUpvotedIds,
        array $userSavedIds,
        array $reportCountsById,
        array $reportCountsBySlug,
        array $reportWeightsById,
        array $reportWeightsBySlug
    ): array {
        $author = $post->user;
        $editedAt = $post->updated_at;
        $createdAt = $post->created_at;
        $wasEdited = !empty($post->edited_by) && $editedAt && $createdAt && $editedAt->gt($createdAt);
        $editor = $post->editedBy;
        $editedBy = null;
        $editedAtLabel = null;
        if ($wasEdited) {
            $editorName = $editor?->name ?? $author?->name ?? __('ui.project.anonymous');
            $editedBy = [
                'id' => $editor?->id,
                'name' => $editorName,
                'slug' => $editor?->slug ?? Str::slug($editorName),
                'role' => $editor?->role ?? $author?->role ?? 'user',
                'avatar' => $editor?->avatar ?? '/images/avatar-default.svg',
            ];
            $editedAtLabel = $editedAt?->diffForHumans();
        }
        $bodyMarkdown = self::normalizeMarkdownSource($post->body_markdown, $post->body_html);
        $created = $post->created_at ?? now();
        $score = $upvoteCounts[$post->id] ?? 0;
        $replies = $commentCounts[$post->slug] ?? 0;
        $reportCount = (int) ($reportCountsById[(string) $post->id] ?? 0)
            + (int) ($reportCountsBySlug[$post->slug] ?? 0);
        $reportPoints = (float) ($reportWeightsById[(string) $post->id] ?? 0)
            + (float) ($reportWeightsBySlug[$post->slug] ?? 0);
        if ($reportPoints <= 0) {
            $reportPoints = (float) $reportCount;
        }

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'time' => $created->format('H:i'),
            'published_minutes' => $created->diffInMinutes(),
            'delta' => '+' . (int) $score,
            'tags' => $post->tags ?? [],
            'nsfw' => (bool) ($post->nsfw ?? false),
            'is_hidden' => (bool) ($post->is_hidden ?? false),
            'moderation_status' => (string) ($post->moderation_status ?? 'approved'),
            'author' => [
                'id' => $author?->id,
                'name' => $author?->name ?? __('ui.project.anonymous'),
                'slug' => $author?->slug ?? Str::slug($author?->name ?? ''),
                'role' => $author?->role ?? 'user',
                'avatar' => $author?->avatar ?? '/images/avatar-default.svg',
            ],
            'author_id' => $author?->id,
            'edited' => $wasEdited,
            'edited_at' => $editedAtLabel,
            'edited_by' => $editedBy,
            'body' => $bodyMarkdown ?: ($post->subtitle ?? ''),
            'body_html' => '',
            'answers' => [],
            'score' => $score,
            'replies' => $replies,
            'saves' => $saveCounts[$post->id] ?? 0,
            'is_upvoted' => in_array($post->id, $userUpvotedIds, true),
            'is_saved' => in_array($post->id, $userSavedIds, true),
            'report_count' => $reportCount,
            'report_points' => round($reportPoints, 1),
        ];
    }

    public static function normalizeStatusKey(?string $status): string
    {
        $key = strtolower((string) $status);
        if (in_array($key, ['in_progress', 'in-progress', 'progress'], true)) {
            return 'in-progress';
        }
        if (in_array($key, ['done', 'shipped', 'completed'], true)) {
            return 'done';
        }
        if (in_array($key, ['paused', 'pause'], true)) {
            return 'paused';
        }
        return 'in-progress';
    }

    public static function normalizeMarkdownSource(?string $markdown, ?string $html): string
    {
        $markdown = (string) ($markdown ?? '');
        if (trim($markdown) !== '') {
            return $markdown;
        }
        $html = (string) ($html ?? '');
        if ($html === '') {
            return '';
        }
        $normalized = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $normalized = preg_replace('/<\/\s*p\s*>/i', "\n", $normalized ?? $html);
        return trim(strip_tags($normalized ?? $html));
    }

    private static function normalizeCover(?string $cover): string
    {
        $cover = $cover ?: '/images/logo-black.svg';
        if (Str::startsWith($cover, ['http://', 'https://'])) {
            return $cover;
        }
        $coverKey = '/' . ltrim((string) $cover, '/');
        if (in_array($coverKey, self::LEGACY_COVERS, true)) {
            return '/images/cover-gradient.svg';
        }
        return $coverKey;
    }

    private static function normalizeAlbum($album): array
    {
        if (is_string($album)) {
            $decoded = json_decode($album, true);
            $album = is_array($decoded) ? $decoded : preg_split('/\r\n|\n|\r/', $album);
        }

        if (!is_array($album)) {
            return [];
        }

        $items = [];
        foreach ($album as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            if (Str::startsWith($value, ['http://', 'https://', '/'])) {
                $items[] = $value;
                continue;
            }
            if (Str::startsWith($value, ['storage/', 'uploads/'])) {
                $items[] = '/' . ltrim($value, '/');
            }
        }

        $items = array_values(array_unique($items));
        return array_slice($items, 0, 12);
    }

    private static function countByColumn(string $table, string $column, array $values, array $filters = []): array
    {
        if (empty($values) || !self::safeHasTable($table)) {
            return [];
        }

        $query = DB::table($table)->whereIn($column, $values);
        foreach ($filters as $filterColumn => $filterValue) {
            $query->where($filterColumn, $filterValue);
        }
        return $query
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->pluck('total', $column)
            ->toArray();
    }

    private static function sumByColumn(string $table, string $column, string $sumColumn, array $values, array $filters = []): array
    {
        if (empty($values) || !self::safeHasTable($table) || !self::safeHasColumn($table, $sumColumn)) {
            return [];
        }

        $query = DB::table($table)->whereIn($column, $values);
        foreach ($filters as $filterColumn => $filterValue) {
            $query->where($filterColumn, $filterValue);
        }

        return $query
            ->select($column, DB::raw('coalesce(sum(' . $sumColumn . '), 0) as total'))
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(static fn ($value) => (float) $value)
            ->toArray();
    }

    private static function idsForUser(string $table, string $column, int $userId, array $values): array
    {
        if (empty($values) || !self::safeHasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->where('user_id', $userId)
            ->whereIn($column, $values)
            ->pluck($column)
            ->map(static function ($value) {
                return is_numeric($value) ? (int) $value : $value;
            })
            ->toArray();
    }

    private static function getTypeCount(string $type, string $filter = 'all'): int
    {
        $filter = self::normalizeFeedFilter($filter);
        $key = "feed.count.$type.$filter.public";
        return (int) Cache::remember($key, now()->addMinutes(2), function () use ($type, $filter) {
            $query = Post::where('type', $type);
            self::applyVisibilityFilters($query, 'posts', null);
            if ($filter === 'fresh') {
                $query->where('created_at', '>=', now()->subMinutes(180));
            }
            if ($filter === 'reading') {
                $query->where('read_time_minutes', '>=', 8);
            }
            return $query->count();
        });
    }

    private static function normalizeFeedFilter(?string $filter): string
    {
        $value = strtolower((string) $filter);
        return in_array($value, ['best', 'fresh', 'reading'], true) ? $value : 'all';
    }

    private static function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function isModerator(?User $viewer): bool
    {
        return $viewer ? $viewer->hasRole('moderator') : false;
    }

    private static function visibilityFiltersForTable(string $table, ?User $viewer): array
    {
        if (self::isModerator($viewer)) {
            return [];
        }

        $filters = [];
        if (self::safeHasColumn($table, 'is_hidden')) {
            $filters['is_hidden'] = false;
        }
        if (self::safeHasColumn($table, 'moderation_status')) {
            $filters['moderation_status'] = 'approved';
        }
        return $filters;
    }

    private static function applyVisibilityFilters($query, string $table, ?User $viewer): void
    {
        if ($table === 'posts' && self::safeHasColumn('users', 'is_banned')) {
            $query->whereNotIn($table . '.user_id', function ($sub) {
                $sub->select('id')
                    ->from('users')
                    ->where('is_banned', true);
            });
        }
        $filters = self::visibilityFiltersForTable($table, $viewer);
        if (empty($filters)) {
            return;
        }
        foreach ($filters as $column => $value) {
            $query->where($column, $value);
        }
    }
}
