<?php

namespace App\Services;

use Illuminate\Support\Str;

class MarkdownService
{
    public function render(?string $markdown): string
    {
        $markdown = (string) ($markdown ?? '');
        if (!method_exists(Str::class, 'markdown')) {
            return nl2br(e($markdown));
        }
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
