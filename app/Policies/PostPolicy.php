<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($user?->is_banned) {
            return false;
        }

        if ($user?->hasRole('moderator')) {
            return true;
        }

        if ($post->user?->is_banned) {
            return false;
        }

        if ($user && $post->user_id === $user->id) {
            return true;
        }

        if ($user && $post->members()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return true;
        }

        return in_array($post->visibility, ['public', 'unlisted'], true)
            && ! $post->is_hidden
            && $post->moderation_status === 'approved'
            && ! ($post->user?->is_banned ?? false);
    }

    public function update(User $user, Post $post): bool
    {
        return ! $user->is_banned && ($post->user_id === $user->id
            || $post->members()->where('user_id', $user->id)->where('status', 'active')->where('can_edit', true)->exists());
    }

    public function delete(User $user, Post $post): bool
    {
        return ! $user->is_banned && $post->user_id === $user->id;
    }
}
