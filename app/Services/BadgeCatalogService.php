<?php

namespace App\Services;

class BadgeCatalogService
{
    private ?array $cache = null;

    public function all(): array
    {
        if (is_array($this->cache)) {
            return $this->cache;
        }

        $path = resource_path('data/badges.json');
        if (!is_file($path)) {
            $this->cache = [];
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw)) {
            $this->cache = [];
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $items[] = [
                'key' => $key,
                'name' => trim((string) ($entry['name'] ?? '')),
                'description' => trim((string) ($entry['description'] ?? '')),
                'icon' => trim((string) ($entry['icon'] ?? '')),
            ];
        }

        $this->cache = $items;
        return $items;
    }

    public function find(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        foreach ($this->all() as $entry) {
            if (($entry['key'] ?? '') === $key) {
                return $entry;
            }
        }

        return null;
    }
}
