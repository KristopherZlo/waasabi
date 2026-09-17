<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedViewService
{
    private const PAGE_SIZE = 10;

    public function __construct(private VisibilityService $visibility) {}

    public function buildPageData(?User $viewer, ?string $filter, ?string $tag, ?string $exclude): array
    {
        $filter = $this->normalizeFilter($filter);
        $tag = $this->normalizeTags($tag);
        $exclude = $this->normalizeTags($exclude);
        $feed = FeedService::buildInitialFeed($viewer, self::PAGE_SIZE, $filter, $tag, $exclude);

        return [
            'use_db_feed' => true,
            'feed_items' => $feed['items'],
            'feed_tags' => FeedService::buildFeedTags(15, 500, $viewer),
            'qa_threads' => FeedService::buildQaThreads(12, $viewer),
            'feed_projects_total' => $feed['projects_total'],
            'feed_questions_total' => $feed['questions_total'],
            'feed_projects_offset' => $feed['projects_offset'],
            'feed_questions_offset' => $feed['questions_offset'],
            'feed_page_size' => self::PAGE_SIZE,
            'filter' => $filter,
            'tag' => $tag,
            'exclude' => $exclude,
        ];
    }

    public function buildChunkData(?User $viewer, string $stream, int $offset, int $limit, ?string $filter, ?string $tag, ?string $exclude): array
    {
        return FeedService::buildFeedChunk(
            $stream,
            max(0, $offset),
            max(1, min(20, $limit)),
            $viewer,
            $this->normalizeFilter($filter),
            $this->normalizeTags($tag),
            $this->normalizeTags($exclude),
        );
    }

    public function buildSubscriptions(?User $viewer): array
    {
        if (! $viewer) {
            return [];
        }

        return DB::table('user_follows')
            ->join('users', 'user_follows.following_id', '=', 'users.id')
            ->leftJoin('posts', function ($join): void {
                $join->on('posts.user_id', '=', 'users.id')
                    ->where('posts.visibility', '=', 'public')
                    ->where('posts.moderation_status', '=', 'approved')
                    ->where('posts.is_hidden', '=', false);
            })
            ->where('user_follows.follower_id', $viewer->id)
            ->where('users.is_banned', false)
            ->groupBy('users.id', 'users.name', 'users.slug')
            ->orderBy('users.name')
            ->limit(20)
            ->get(['users.id', 'users.name', 'users.slug', DB::raw('count(posts.id) as count')])
            ->map(fn (object $row) => [
                'name' => $row->name,
                'slug' => $row->slug ?: 'user-'.$row->id,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    public function buildSidebarData(?User $viewer): array
    {
        return [
            'top_projects' => $this->topProjects(),
            'reading_now' => $this->readingNow(),
            'subscriptions' => $this->buildSubscriptions($viewer),
        ];
    }

    private function topProjects(): array
    {
        $query = Post::query()
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.type', 'post')
            ->groupBy('posts.id', 'posts.slug', 'posts.title', 'posts.created_at')
            ->select('posts.slug', 'posts.title', DB::raw('count(post_upvotes.id) as score'))
            ->orderByDesc('score')
            ->orderByDesc('posts.created_at')
            ->limit(4);
        $this->visibility->applyToQuery($query, 'posts', null);

        return $query->get()->map(fn (object $row) => [
            'slug' => $row->slug,
            'title' => $row->title,
            'score' => (int) $row->score,
        ])->all();
    }

    private function readingNow(): array
    {
        $source = DB::table('reading_activity')
            ->where('updated_at', '>=', now()->subMinutes(10))
            ->groupBy('post_id')
            ->select('post_id', DB::raw('count(distinct ip_hash) as readers'))
            ->orderByDesc('readers')
            ->limit(3)
            ->get();
        if ($source->isEmpty()) {
            return [];
        }

        $query = Post::whereIn('slug', $source->pluck('post_id')->all());
        $this->visibility->applyToQuery($query, 'posts', null);
        $posts = $query->get(['slug', 'title'])->keyBy('slug');

        return $source->map(function (object $row) use ($posts): ?array {
            $post = $posts->get($row->post_id);

            return $post ? ['slug' => $post->slug, 'title' => $post->title, 'readers' => (int) $row->readers] : null;
        })->filter()->values()->all();
    }

    private function normalizeFilter(?string $filter): string
    {
        return in_array($filter, ['best', 'fresh', 'reading', 'following', 'quiet'], true) ? $filter : 'all';
    }

    private function normalizeTags(?string $value): string
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $tag) => Str::slug($tag))
            ->filter()->unique()->take(10)->implode(',');
    }
}
