<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class SupportStaffService
{
    public function staffUsers(): Collection
    {
        $roles = ['support', 'moderator', 'admin'];

        return User::query()->whereIn('role', $roles)->where('is_banned', false)->get();
    }

    public function notify(string $type, string $text, ?string $link = null, ?int $excludeUserId = null): void
    {
        foreach ($this->staffUsers() as $staff) {
            if ($excludeUserId && $staff->id === $excludeUserId) {
                continue;
            }
            $staff->sendNotification($type, $text, $link);
        }
    }
}
