<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSlugService
{
    public function generate(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'user';
        }

        if (!$this->safeHasTable('users') || !$this->safeHasColumn('users', 'slug')) {
            return $base;
        }

        $slug = $base;
        $counter = 2;
        while (User::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter += 1;
        }

        return $slug;
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
