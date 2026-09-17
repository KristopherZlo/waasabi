<?php

namespace App\Services;

use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CollaborationService
{
    private const SEARCH_SYNONYMS = [
        'design' => ['role' => ['designer', 'visual-artist', 'illustrator']],
        'designer' => ['role' => ['designer', 'visual-artist']],
        'draw' => ['role' => ['illustrator', 'visual-artist']],
        'illustration' => ['role' => ['illustrator', 'visual-artist']],
        'art' => ['role' => ['visual-artist', 'illustrator', '3d-artist', 'graffiti-artist']],
        'code' => ['role' => ['developer']],
        'developer' => ['role' => ['developer']],
        'programming' => ['role' => ['developer']],
        'writing' => ['role' => ['writer', 'editor', 'coauthor', 'poet']],
        'text' => ['role' => ['writer', 'editor', 'coauthor']],
        'music' => ['role' => ['musician', 'vocalist', 'composer', 'sound-designer']],
        'audio' => ['role' => ['musician', 'composer', 'sound-designer']],
        'photo' => ['role' => ['photographer', 'filmmaker']],
        'video' => ['role' => ['filmmaker', 'animator']],
        'remote' => ['format' => ['remote', 'async']],
        'online' => ['format' => ['remote', 'async']],
        'local' => ['format' => ['on-site', 'hybrid']],
        'quick' => ['availability' => ['one-time']],
        'evening' => ['availability' => ['one-time']],
        'weekly' => ['availability' => ['part-time', 'regular']],
        'flexible' => ['availability' => ['flexible']],
    ];

    public function roleOptions(): array
    {
        return (array) __('ui.collaboration.roles');
    }

    public function availabilityOptions(): array
    {
        return (array) __('ui.collaboration.availability_options');
    }

    public function formatOptions(): array
    {
        return (array) __('ui.collaboration.format_options');
    }

    public function requests(array $filters, ?User $viewer): LengthAwarePaginator
    {
        $query = CollaborationRequest::query()
            ->with([
                'post:id,user_id,slug,title,cover_url,status',
                'user:id,slug,name,avatar,role',
            ])
            ->withCount('applications')
            ->visibleTo($viewer);

        $status = (string) ($filters['status'] ?? 'open');
        if (! in_array($status, ['open', 'filled', 'closed', 'all'], true)) {
            $status = 'open';
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($status === 'open') {
            $query->where(fn ($dateQuery) => $dateQuery->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        foreach (['role', 'availability', 'format'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $scope = (string) ($filters['scope'] ?? '');
        if ($viewer && $scope === 'mine') {
            $query->where('user_id', $viewer->id);
        } elseif ($viewer && $scope === 'applied') {
            $query->whereHas('applications', fn ($applications) => $applications->where('user_id', $viewer->id));
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $expanded = $this->expandSearch($search);
            $query->where(function ($searchQuery) use ($search, $expanded): void {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('skills', 'like', "%{$search}%")
                    ->orWhereHas('post', fn ($postQuery) => $postQuery->where('title', 'like', "%{$search}%"));
                foreach ($expanded as $field => $values) {
                    if ($values !== []) {
                        $searchQuery->orWhereIn($field, $values);
                    }
                }
            });
        }

        return $query->latest()->paginate(20)->withQueryString();
    }

    public function manageableProjects(User $user)
    {
        return Post::query()
            ->select(['id', 'slug', 'title'])
            ->where('type', 'post')
            ->where('user_id', $user->id)
            ->where('visibility', 'public')
            ->where('is_hidden', false)
            ->where('moderation_status', 'approved')
            ->latest()
            ->get();
    }

    public function parseSkills(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($skill) => trim(strip_tags((string) $skill)))
            ->filter()
            ->map(fn ($skill) => mb_substr($skill, 0, 40))
            ->unique(fn ($skill) => mb_strtolower($skill))
            ->take(10)
            ->values()
            ->all();
    }

    /** @return array{role: array<int, string>, availability: array<int, string>, format: array<int, string>} */
    private function expandSearch(string $search): array
    {
        $normalized = Str::lower($search);
        $tokens = preg_split('/[^\pL\pN-]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $expanded = ['role' => [], 'availability' => [], 'format' => []];

        foreach (self::SEARCH_SYNONYMS as $word => $matches) {
            if (! in_array($word, $tokens, true)) {
                continue;
            }
            foreach ($matches as $field => $values) {
                $expanded[$field] = array_values(array_unique(array_merge($expanded[$field], $values)));
            }
        }

        return $expanded;
    }
}
