<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function destroy(Request $request, Post $post): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }
        if ($user->cannot('delete', $post)) {
            abort(403);
        }

        $post->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('feed');
    }
}
