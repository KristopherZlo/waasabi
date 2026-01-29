<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Support\SchemaGuard;
use Illuminate\Support\Str;

class CollaborationService
{
    private const PAGE_LIMIT = 120;

    public function __construct(
        private DemoContentService $demoContent,
        private VisibilityService $visibility
    ) {
    }

    public function roleOptions(): array
    {
        return [
            'designer' => 'Designer',
            'ui-ux' => 'UI/UX designer',
            'frontend' => 'Frontend developer',
            'backend' => 'Backend developer',
            'fullstack' => 'Full-stack developer',
            'hardware' => 'Hardware engineer',
            'firmware' => 'Firmware developer',
            'product' => 'Product manager',
            'writer' => 'Technical writer',
            'research' => 'Researcher',
            'data' => 'Data analyst',
        ];
    }

    public function availabilityOptions(): array
    {
        return [
            'one-time' => 'One-time',
            'part-time' => 'Part-time',
            'full-time' => 'Full-time',
            'flexible' => 'Flexible',
        ];
    }

    public function formatOptions(): array
    {
        return [
            'remote' => 'Remote',
            'hybrid' => 'Hybrid',
            'on-site' => 'On-site',
            'async' => 'Async',
        ];
    }

    public function buildPageData(?User $viewer): array
    {
        $items = $this->fetchCollaborationItems($viewer);

        return [
            'collaboration_items' => $items,
            'collaboration_roles' => $this->roleOptions(),
            'collaboration_availability' => $this->availabilityOptions(),
            'collaboration_formats' => $this->formatOptions(),
        ];
    }

    public function buildTagsForRequest(string $roleKey, string $availabilityKey, string $formatKey, array $skills): array
    {
        $roleLabel = $this->roleOptions()[$roleKey] ?? $roleKey;
        $availabilityLabel = $this->availabilityOptions()[$availabilityKey] ?? $availabilityKey;
        $formatLabel = $this->formatOptions()[$formatKey] ?? $formatKey;

        $tags = [
            'Collaboration',
            $roleLabel,
            $availabilityLabel,
            $formatLabel,
        ];

        foreach ($skills as $skill) {
            $tags[] = $skill;
        }

        return array_values(array_unique(array_filter($tags)));
    }

    public function formatBody(
        string $roleLabel,
        string $availabilityLabel,
        string $formatLabel,
        array $skills,
        string $summary,
        ?string $contact
    ): string {
        $skillsLine = !empty($skills) ? implode(', ', $skills) : 'Open to suggestions';
        $contactLine = $contact ? trim($contact) : 'Send a message via my profile.';

        return "## Looking for\n"
            . "Role: {$roleLabel}\n"
            . "Availability: {$availabilityLabel}\n"
            . "Format: {$formatLabel}\n"
            . "Skills: {$skillsLine}\n\n"
            . "## Project\n"
            . $summary
            . "\n\n"
            . "## How to join\n"
            . $contactLine;
    }

    private function fetchCollaborationItems(?User $viewer): array
    {
        $useDbFeed = SchemaGuard::hasTable('posts') && Post::query()->exists();
        if ($useDbFeed) {
            return $this->fetchDbItems($viewer);
        }

        $projects = $this->demoContent->projects();
        $items = [];
        foreach ($projects as $project) {
            if (!$this->hasCollaborationTag($project['tags'] ?? [])) {
                continue;
            }
            $items[] = [
                'type' => 'project',
                'data' => $project,
                'published_minutes' => (int) ($project['published_minutes'] ?? 0),
            ];
        }

        return $items;
    }

    private function fetchDbItems(?User $viewer): array
    {
        $query = Post::with(['user', 'editedBy'])
            ->where('type', 'post')
            ->orderByDesc('created_at')
            ->limit(self::PAGE_LIMIT);

        $this->visibility->applyToQuery($query, 'posts', $viewer);

        $posts = $query->get();
        if ($posts->isEmpty()) {
            return [];
        }

        $filtered = $posts->filter(fn (Post $post) => $this->hasCollaborationTag($post->tags ?? []));
        if ($filtered->isEmpty()) {
            return [];
        }

        $stats = FeedService::preparePostStats($filtered, $viewer);

        return $filtered
            ->map(function (Post $post) use ($stats) {
                $data = FeedService::mapPostToProjectWithStats($post, $stats);
                return [
                    'type' => 'project',
                    'data' => $data,
                    'published_minutes' => (int) ($data['published_minutes'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function hasCollaborationTag(array $tags): bool
    {
        foreach ($tags as $tag) {
            $slug = Str::slug((string) $tag);
            if ($slug === 'collaboration') {
                return true;
            }
        }
        return false;
    }
}
