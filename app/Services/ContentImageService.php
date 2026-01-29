<?php

namespace App\Services;

use Illuminate\Support\Str;

class ContentImageService
{
    public function extractUploadedImagePathsFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $matches = [];
        preg_match_all('/<img[^>]+src=["\\\']([^"\\\']+)["\\\']/i', $html, $matches);
        $sources = $matches[1] ?? [];
        if (empty($sources)) {
            return [];
        }

        $paths = [];
        foreach ($sources as $source) {
            $source = trim((string) $source);
            if ($source === '') {
                continue;
            }
            $path = parse_url($source, PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : $source;
            $normalized = $path;
            $storagePos = strpos($normalized, '/storage/');
            if ($storagePos !== false) {
                $normalized = substr($normalized, $storagePos + 1);
            } else {
                $normalized = ltrim($normalized, '/');
            }
            if (!Str::startsWith($normalized, 'storage/')) {
                continue;
            }
            $paths[] = $normalized;
        }

        return array_values(array_unique($paths));
    }
}
