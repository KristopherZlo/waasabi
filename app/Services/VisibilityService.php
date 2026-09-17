<?php

namespace App\Services;

use App\Models\User;

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
        if ($table === 'posts') {
            $query->where($table.'.visibility', 'public');
        }
        if ($this->isModerator($viewer)) {
            return;
        }
        if (in_array($table, ['posts', 'post_comments', 'post_reviews'], true)) {
            $query->whereNotIn($table.'.user_id', function ($sub) {
                $sub->select('id')
                    ->from('users')
                    ->where('is_banned', true);
            });
        }
        $query->where($table.'.is_hidden', false);
        $query->where($table.'.moderation_status', 'approved');
    }

    private function isModerator(?User $user): bool
    {
        return $user ? $user->hasRole('moderator') : false;
    }
}
