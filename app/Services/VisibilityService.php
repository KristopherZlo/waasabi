<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

class VisibilityService
{
    public function canViewHidden(?User $viewer, ?int $ownerId = null): bool
    {
        if ($this->isModerator($viewer)) {
            return true;
        }

        return $viewer && $ownerId && $viewer->id === $ownerId;
    }

    public function applyToQuery($query, string $table, ?User $viewer): void
    {
        if ($table === 'posts' && $this->safeHasTable('users') && $this->safeHasColumn('users', 'is_banned')) {
            $query->whereNotIn($table . '.user_id', function ($sub) {
                $sub->select('id')
                    ->from('users')
                    ->where('is_banned', true);
            });
        }
        if ($this->isModerator($viewer)) {
            return;
        }
        if ($this->safeHasColumn($table, 'is_hidden')) {
            $query->where($table . '.is_hidden', false);
        }
        if ($this->safeHasColumn($table, 'moderation_status')) {
            $query->where($table . '.moderation_status', 'approved');
        }
    }

    private function isModerator(?User $user): bool
    {
        return $user ? $user->hasRole('moderator') : false;
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
