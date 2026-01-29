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
        if (!$viewer) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }
        if (!safeHasTable('user_follows') || !safeHasTable('users')) {
            abort(503);
        }

        $user = User::where('slug', $slug)->firstOrFail();
        if ($user->id === $viewer->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Cannot follow yourself'], 400);
            }
            return redirect()->back();
        }
        if (safeHasColumn('users', 'connections_allow_follow') && !$user->connections_allow_follow) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Follow disabled'], 403);
            }
            return redirect()->back();
        }

        $existing = DB::table('user_follows')
            ->where('follower_id', $viewer->id)
            ->where('following_id', $user->id)
            ->exists();

        if ($existing) {
            DB::table('user_follows')
                ->where('follower_id', $viewer->id)
                ->where('following_id', $user->id)
                ->delete();
        } else {
            DB::table('user_follows')->insert([
                'follower_id' => $viewer->id,
                'following_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            $followersCount = DB::table('user_follows')->where('following_id', $user->id)->count();
            $followingCount = DB::table('user_follows')->where('follower_id', $user->id)->count();
            return response()->json([
                'is_following' => !$existing,
                'followers_count' => $followersCount,
                'following_count' => $followingCount,
            ]);
        }

        return redirect()->back();
    }
}
