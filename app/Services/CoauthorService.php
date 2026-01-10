<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CoauthorService
{
    public function listSuggestions(int $limit = 200, ?User $actor = null): array
    {
        if (!$this->safeHasTable('users') || !$this->safeHasColumn('users', 'slug')) {
            return [];
        }

        $query = User::query()
            ->select(['id', 'slug', 'name'])
            ->whereNotNull('slug')
            ->where('slug', '<>', '');

        if ($actor) {
            $query->where('id', '<>', $actor->id);
        }
        if ($this->safeHasColumn('users', 'is_banned')) {
            $query->where('is_banned', false);
        }
        if ($this->safeHasColumn('users', 'privacy_allow_mentions')) {
            $query->where('privacy_allow_mentions', true);
        }

        return $query
            ->orderBy('name')
            ->limit(max(10, $limit))
            ->get()
            ->map(static fn (User $user) => [
                'id' => (int) $user->id,
                'slug' => (string) ($user->slug ?? ''),
                'name' => (string) ($user->name ?? ''),
            ])
            ->filter(static fn (array $item) => $item['slug'] !== '')
            ->values()
            ->all();
    }

    public function resolveUsers(string $raw, ?User $actor = null, int $limit = 8): array
    {
        $tokens = $this->parseTokens($raw);
        if ($tokens === [] || !$this->safeHasTable('users') || !$this->safeHasColumn('users', 'slug')) {
            return [
                'tokens' => $tokens,
                'users' => collect(),
                'ids' => [],
                'slugs' => [],
                'unresolved' => $tokens,
            ];
        }

        $candidateSlugs = [];
        foreach ($tokens as $token) {
            $lower = Str::lower($token);
            if ($lower !== '') {
                $candidateSlugs[] = $lower;
            }
            $slug = Str::slug($token);
            if ($slug !== '') {
                $candidateSlugs[] = $slug;
            }
        }
        $candidateSlugs = array_values(array_unique(array_filter($candidateSlugs)));
        if ($candidateSlugs === []) {
            return [
                'tokens' => $tokens,
                'users' => collect(),
                'ids' => [],
                'slugs' => [],
                'unresolved' => $tokens,
            ];
        }

        $query = User::query()
            ->select(['id', 'slug', 'name'])
            ->whereIn('slug', $candidateSlugs);

        if ($actor) {
            $query->where('id', '<>', $actor->id);
        }
        if ($this->safeHasColumn('users', 'is_banned')) {
            $query->where('is_banned', false);
        }
        if ($this->safeHasColumn('users', 'privacy_allow_mentions')) {
            $query->where('privacy_allow_mentions', true);
        }

        $users = $query->get();
        if ($users->isEmpty()) {
            return [
                'tokens' => $tokens,
                'users' => collect(),
                'ids' => [],
                'slugs' => [],
                'unresolved' => $tokens,
            ];
        }

        $bySlug = $users->keyBy(static fn (User $user) => Str::lower((string) $user->slug));
        $ordered = [];
        $resolvedTokens = [];

        foreach ($tokens as $token) {
            $lower = Str::lower($token);
            $slug = Str::slug($token);
            $user = $bySlug->get($lower) ?? ($slug !== '' ? $bySlug->get($slug) : null);
            if (!$user) {
                continue;
            }
            $key = (int) $user->id;
            if (isset($ordered[$key])) {
                $resolvedTokens[] = $token;
                continue;
            }
            $ordered[$key] = $user;
            $resolvedTokens[] = $token;
            if (count($ordered) >= max(1, $limit)) {
                break;
            }
        }

        $orderedUsers = collect(array_values($ordered));
        $orderedSlugs = $orderedUsers
            ->map(static fn (User $user) => (string) ($user->slug ?? ''))
            ->filter()
            ->values()
            ->all();
        $orderedIds = $orderedUsers
            ->map(static fn (User $user) => (int) $user->id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();

        $unresolved = [];
        foreach ($tokens as $token) {
            if (!in_array($token, $resolvedTokens, true)) {
                $unresolved[] = $token;
            }
        }

        return [
            'tokens' => $tokens,
            'users' => $orderedUsers,
            'ids' => $orderedIds,
            'slugs' => $orderedSlugs,
            'unresolved' => $unresolved,
        ];
    }

    private function parseTokens(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/@?([\p{L}\p{N}][\p{L}\p{N}\-_.]{1,60})/u', $raw, $matches);
        $tokens = $matches[1] ?? [];

        return collect($tokens)
            ->map(static fn ($token) => trim((string) $token))
            ->map(static fn ($token) => ltrim($token, '@'))
            ->filter()
            ->unique()
            ->values()
            ->all();
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
