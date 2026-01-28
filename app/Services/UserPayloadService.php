<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPayloadService
{
    public function currentUserPayload(): array
    {
        $user = Auth::user();
        if (!$user) {
            return [
                'id' => null,
                'name' => 'Guest',
                'role' => 'user',
                'slug' => null,
                'avatar' => '/images/avatar-default.svg',
                'banner_url' => null,
                'bio' => '',
                'followers_count' => 0,
                'following_count' => 0,
            ];
        }
        $slug = $user->slug ?? Str::slug($user->name ?? '');
        $slug = $slug !== '' ? $slug : null;
        $followersCount = 0;
        $followingCount = 0;
        if (safeHasTable('user_follows')) {
            $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
            $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
        }
        return [
            'id' => $user->id,
            'name' => $user->name ?? 'Guest',
            'role' => $user->roleKey(),
            'slug' => $slug,
            'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            'banner_url' => safeHasColumn('users', 'banner_url') ? ($user->banner_url ?: null) : null,
            'bio' => $user->bio ?? '',
            'followers_count' => (int) $followersCount,
            'following_count' => (int) $followingCount,
            'is_banned' => safeHasColumn('users', 'is_banned') ? (bool) $user->is_banned : false,
        ];
    }
}
