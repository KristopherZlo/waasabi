<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileFollowController extends Controller
{
    public function toggle(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        if (! $viewer) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.errors.unauthorized')], 401);
            }

            return redirect()->route('login');
        }
        $user = User::where('slug', $slug)->firstOrFail();
        if ($user->id === $viewer->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.errors.cannot_follow_self')], 400);
            }

            return redirect()->back();
        }
        abort_if($user->is_banned, 404);
        if (! $user->connections_allow_follow) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('ui.errors.follow_disabled')], 403);
            }

            return redirect()->back();
        }

        $isFollowing = DB::transaction(function () use ($viewer, $user): bool {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $query = DB::table('user_follows')
                ->where('follower_id', $viewer->id)
                ->where('following_id', $user->id);

            if ($query->exists()) {
                $query->delete();

                return false;
            }

            DB::table('user_follows')->insert([
                'follower_id' => $viewer->id,
                'following_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });

        if ($isFollowing) {
            $user->sendPreferredNotification(
                'notify_follows',
                __('ui.notifications.type_follow'),
                __('ui.notifications.follow_started', ['user' => $viewer->name]),
                route('profile.show', $viewer->slug),
            );
        }

        if ($request->expectsJson()) {
            $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
            $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();

            return response()->json([
                'is_following' => $isFollowing,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
            ]);
        }

        return redirect()->back();
    }
}
