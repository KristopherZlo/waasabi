<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SupportStaffService
{
    public function staffUsers(): Collection
    {
        if (!$this->safeHasTable('users')) {
            return collect();
        }

        $roles = ['support', 'moderator', 'admin'];
        $query = User::query()->whereIn('role', $roles);
        if ($this->safeHasColumn('users', 'is_banned')) {
            $query->where('is_banned', false);
        }

        return $query->get();
    }

    public function notify(string $type, string $text, ?string $link = null, ?int $excludeUserId = null): void
    {
        if (!$this->safeHasTable('user_notifications')) {
            return;
        }

        foreach ($this->staffUsers() as $staff) {
            if ($excludeUserId && $staff->id === $excludeUserId) {
                continue;
            }
            $staff->sendNotification($type, $text, $link);
        }
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
