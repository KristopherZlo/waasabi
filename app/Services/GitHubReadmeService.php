<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GitHubReadmeService
{
    /** @return array{markdown: string, repository: string, url: string}|null */
    public function get(?string $repository): ?array
    {
        $path = $this->repositoryPath($repository);
        if (! $path) {
            return null;
        }

        $cached = Cache::remember('github-readme:'.sha1(strtolower($path)), now()->addHour(), function () use ($path): array {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/vnd.github.raw+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])->timeout(4)->get("https://api.github.com/repos/{$path}/readme");

                return ['markdown' => $response->successful() ? Str::limit($response->body(), 100000, '') : null];
            } catch (\Throwable) {
                return ['markdown' => null];
            }
        });

        $markdown = $cached['markdown'] ?? null;
        if (! is_string($markdown) || trim($markdown) === '') {
            return null;
        }

        return ['markdown' => $markdown, 'repository' => $path, 'url' => "https://github.com/{$path}"];
    }

    public function repositoryPath(?string $repository): ?string
    {
        $path = preg_replace('~\Ahttps://(?:www\.)?github\.com/~i', '', trim((string) $repository));
        $path = preg_replace('~\.git/?\z~i', '', (string) $path);
        $path = rtrim((string) $path, '/');

        return preg_match('~\A[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})/[A-Za-z0-9._-]{1,100}\z~', $path) ? $path : null;
    }
}
