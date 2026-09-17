<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserPayloadService
{
    public function currentUserPayload(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [
                'id' => null,
                'name' => __('ui.project.anonymous'),
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
        $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
        $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();

        return [
            'id' => $user->id,
            'name' => $user->name ?? __('ui.project.anonymous'),
            'role' => $user->roleKey(),
            'slug' => $slug,
            'avatar' => $user->avatar ?? '/images/avatar-default.svg',
            'banner_url' => $user->banner_url ?: null,
            'bio' => $user->bio ?? '',
            'followers_count' => (int) $followersCount,
            'following_count' => (int) $followingCount,
            'is_banned' => (bool) $user->is_banned,
        ];
    }
}
