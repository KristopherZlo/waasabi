<?php

namespace App\Services;

use App\Models\User;
use App\Support\SchemaGuard;
use Illuminate\Support\Str;

class DemoContentService
{
    private ?array $cache = null;

    public function __construct(private UserSlugService $slugService)
    {
    }

    public function projects(): array
    {
        return $this->getData()['projects'];
    }

    public function questions(): array
    {
        return $this->getData()['qa_questions'];
    }

    public function profile(): array
    {
        return $this->getData()['profile'];
    }

    public function showcase(): array
    {
        return $this->getData()['showcase'];
    }

    private function getData(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $base = require base_path('resources/data/demo.php');
        $projects = $base['projects'] ?? [];
        $questions = $base['qa_questions'] ?? [];
        $profile = $base['profile'] ?? [];

        $demoAuthors = $this->loadDemoAuthors();
        if (!empty($demoAuthors)) {
            $projects = $this->applyAuthors($projects, $demoAuthors);
            $questions = $this->applyAuthors($questions, $demoAuthors);
        }

        $this->cache = [
            'projects' => $projects,
            'qa_questions' => $questions,
            'profile' => $profile,
            'showcase' => $this->buildShowcase($projects),
        ];

        return $this->cache;
    }

    private function loadDemoAuthors(): array
    {
        if (!SchemaGuard::hasTable('users')) {
            return [];
        }

        return User::query()
            ->orderBy('id')
            ->get()
            ->map(function (User $user) {
                $name = $user->name ?? __('ui.project.anonymous');
                $slug = $user->slug ?? Str::slug($user->name ?? '');
                if ($slug === '') {
                    $slug = $this->slugService->generate($user->name ?? 'user');
                }
                return [
                    'id' => $user->id,
                    'name' => $name,
                    'slug' => $slug,
                    'role' => $user->role ?? 'user',
                    'avatar' => $user->avatar ?? '/images/avatar-default.svg',
                ];
            })
            ->values()
            ->all();
    }

    private function applyAuthors(array $items, array $authors): array
    {
        $index = 0;
        $total = count($authors);
        if ($total === 0) {
            return $items;
        }

        return array_map(static function (array $item) use (&$index, $authors, $total) {
            $author = $authors[$index % $total] ?? null;
            $index += 1;
            if ($author) {
                $item['author'] = $author;
            }
            return $item;
        }, $items);
    }

    private function buildShowcase(array $projects): array
    {
        $groups = [
            ['title' => 'Shipped to the end', 'indexes' => [0, 1]],
            ['title' => 'Living process', 'indexes' => [2]],
            ['title' => 'Strong reviews', 'indexes' => [3]],
        ];

        $showcase = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['indexes'] as $index) {
                if (isset($projects[$index])) {
                    $items[] = $projects[$index];
                }
            }
            $showcase[] = [
                'title' => $group['title'],
                'projects' => $items,
            ];
        }

        return $showcase;
    }
}
