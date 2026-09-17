<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        $query = DB::table('posts')
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.type', (string) ($config['type'] ?? 'post'));

        if ($windowHours > 0) {
            $query->where('posts.created_at', '>=', now()->subHours($windowHours));
        }
        if (! empty($config['exclude_nsfw'])) {
            $query->where('posts.nsfw', false);
        }
        if (! empty($config['require_visible'])) {
            $query->where('posts.is_hidden', false);
        }
        if (! empty($config['require_approved'])) {
            $query->where('posts.moderation_status', 'approved');
        }
        $query->whereNotIn('posts.user_id', function ($sub) {
            $sub->select('id')->from('users')->where('is_banned', true);
        });

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
    }

    public function countUserTopPosts(User $user, int $threshold): int
    {
        $config = $this->config();
        $query = DB::table('posts')
            ->leftJoin('post_upvotes', 'posts.id', '=', 'post_upvotes.post_id')
            ->where('posts.user_id', $user->id)
            ->where('posts.type', (string) ($config['type'] ?? 'post'));

        if (! empty($config['exclude_nsfw'])) {
            $query->where('posts.nsfw', false);
        }
        if (! empty($config['require_visible'])) {
            $query->where('posts.is_hidden', false);
        }
        if (! empty($config['require_approved'])) {
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
        if ($user->is_banned) {
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
}
