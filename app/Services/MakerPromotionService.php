<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakerPromotionService
{
    public function config(): array
    {
        $defaults = [
            'required_posts' => 5,
            'min_upvotes' => 15,
            'percentile' => 75,
            'window_hours' => 24,
            'min_sample' => 10,
            'exclude_nsfw' => true,
            'require_visible' => true,
            'require_approved' => true,
            'type' => 'post',
            'cache_minutes' => 10,
        ];

        $config = (array) config('roles.maker_promotion', []);
        return array_merge($defaults, $config);
    }

    public function threshold(): int
    {
        $config = $this->config();
        $minUpvotes = max(0, (int) ($config['min_upvotes'] ?? 0));
        $percentile = (float) ($config['percentile'] ?? 75);
        $windowHours = max(0, (int) ($config['window_hours'] ?? 24));
        $minSample = max(0, (int) ($config['min_sample'] ?? 0));
        $cacheMinutes = max(1, (int) ($config['cache_minutes'] ?? 10));

        $cacheKey = implode('.', [
            'roles',
            'maker',
            'threshold',
            'v2',
            $windowHours,
            (int) $percentile,
            $minUpvotes,
            $minSample,
            !empty($config['exclude_nsfw']) ? 'nsfw0' : 'nsfw1',
        ]);

        return (int) Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use (
            $config,
            $minUpvotes,
            $percentile,
            $windowHours,
            $minSample
        ): int {
            if (!$this->safeHasTable('posts') || !$this->safeHasTable('post_upvotes')) {
                return $minUpvotes;
            }

            $query = DB::table('posts')
                ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
                ->where('posts.type', (string) ($config['type'] ?? 'post'));

            if ($windowHours > 0) {
                $query->where('posts.created_at', '>=', now()->subHours($windowHours));
            }
            if (!empty($config['exclude_nsfw']) && $this->safeHasColumn('posts', 'nsfw')) {
                $query->where('posts.nsfw', false);
            }
            if (!empty($config['require_visible']) && $this->safeHasColumn('posts', 'is_hidden')) {
                $query->where('posts.is_hidden', false);
            }
            if (!empty($config['require_approved']) && $this->safeHasColumn('posts', 'moderation_status')) {
                $query->where('posts.moderation_status', 'approved');
            }
            if ($this->safeHasTable('users') && $this->safeHasColumn('users', 'is_banned')) {
                $query->whereNotIn('posts.user_id', function ($sub) {
                    $sub->select('id')
                        ->from('users')
                        ->where('is_banned', true);
                });
            }

            $rows = $query
                ->groupBy('posts.id')
                ->select('posts.id', DB::raw('count(post_upvotes.id) as score'))
                ->get();

            $scores = $rows
                ->pluck('score')
                ->map(static fn ($value) => (int) $value)
                ->all();

            if (count($scores) < $minSample || empty($scores)) {
                return $minUpvotes;
            }

            sort($scores, SORT_NUMERIC);
            $percentile = max(0.0, min(100.0, $percentile));
            $rank = (int) ceil(($percentile / 100) * count($scores));
            $rank = max(1, min($rank, count($scores)));
            $value = (int) ($scores[$rank - 1] ?? 0);

            return max($minUpvotes, $value);
        });
    }

    public function countUserTopPosts(User $user, int $threshold): int
    {
        if (!$this->safeHasTable('posts') || !$this->safeHasTable('post_upvotes')) {
            return 0;
        }

        $config = $this->config();
        $query = DB::table('posts')
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', (string) ($config['type'] ?? 'post'));

        if (!empty($config['exclude_nsfw']) && $this->safeHasColumn('posts', 'nsfw')) {
            $query->where('posts.nsfw', false);
        }
        if (!empty($config['require_visible']) && $this->safeHasColumn('posts', 'is_hidden')) {
            $query->where('posts.is_hidden', false);
        }
        if (!empty($config['require_approved']) && $this->safeHasColumn('posts', 'moderation_status')) {
            $query->where('posts.moderation_status', 'approved');
        }

        $rows = $query
            ->groupBy('posts.id')
            ->havingRaw('count(post_upvotes.id) >= ?', [$threshold])
            ->select('posts.id')
            ->get();

        return $rows->count();
    }

    public function maybePromote(User $user): bool
    {
        if ($user->roleKey() !== 'user') {
            return false;
        }
        if ($this->safeHasColumn('users', 'is_banned') && ($user->is_banned ?? false)) {
            return false;
        }

        $config = $this->config();
        $required = max(1, (int) ($config['required_posts'] ?? 5));
        $threshold = $this->threshold();
        $qualified = $this->countUserTopPosts($user, $threshold);

        if ($qualified < $required) {
            return false;
        }

        $user->update(['role' => 'maker']);
        return true;
    }

    private function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

}