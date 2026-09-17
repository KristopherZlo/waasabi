<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\MakerPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ContentActionController extends Controller
{
    public function readingProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => ['required', 'string', 'max:190'],
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'anchor' => ['nullable', 'string', 'max:190'],
        ]);

        $post = Post::with('user')->where('slug', $data['post_id'])->first();
        if (! $post || Gate::denies('view', $post)) {
            return response()->json(['ok' => true]);
        }

        $timestamp = now();
        $ip = (string) ($request->ip() ?? '');
        if ($ip !== '') {
            DB::table('reading_activity')->upsert([[
                'ip_hash' => hash('sha256', config('app.key').'|'.$ip),
                'post_id' => $data['post_id'],
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]], ['ip_hash', 'post_id'], ['updated_at']);
        }

        if ($request->user()) {
            DB::table('reading_progress')->upsert([[
                'user_id' => $request->user()->id,
                'post_id' => $data['post_id'],
                'percent' => $data['percent'],
                'anchor' => $data['anchor'] ?? null,
                'updated_at' => $timestamp,
                'created_at' => $timestamp,
            ]], ['user_id', 'post_id'], ['percent', 'anchor', 'updated_at']);
        }

        return response()->json(['ok' => true]);
    }

    public function toggleSave(Request $request, string $slug): JsonResponse
    {
        [$enabled, $count] = $this->toggle($request, $slug, 'post_saves');

        return response()->json(['saved' => $enabled, 'count' => $count]);
    }

    public function toggleUpvote(Request $request, string $slug, MakerPromotionService $promotion): JsonResponse
    {
        [$enabled, $count, $post] = $this->toggle($request, $slug, 'post_upvotes');
        if ($enabled) {
            $promotion->maybePromote($post->user);
        }

        return response()->json(['upvoted' => $enabled, 'count' => $count]);
    }

    /** @return array{0: bool, 1: int, 2: Post} */
    private function toggle(Request $request, string $slug, string $table): array
    {
        $post = Post::with('user')->where('slug', $slug)->firstOrFail();
        abort_unless($request->user()->can('view', $post), 404);

        [$enabled, $count] = DB::transaction(function () use ($request, $post, $table): array {
            $query = DB::table($table)
                ->where('user_id', $request->user()->id)
                ->where('post_id', $post->id);
            $exists = $query->lockForUpdate()->exists();

            if ($exists) {
                $query->delete();
            } else {
                DB::table($table)->insert([
                    'user_id' => $request->user()->id,
                    'post_id' => $post->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [! $exists, DB::table($table)->where('post_id', $post->id)->count()];
        });

        return [$enabled, $count, $post];
    }
}
