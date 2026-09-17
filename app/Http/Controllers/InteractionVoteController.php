<?php

namespace App\Http\Controllers;

use App\Models\PostComment;
use App\Models\PostReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InteractionVoteController extends Controller
{
    public function comment(Request $request, PostComment $postComment): JsonResponse
    {
        return $this->vote($request, 'post_comment_votes', 'post_comment_id', $postComment);
    }

    public function review(Request $request, PostReview $postReview): JsonResponse
    {
        return $this->vote($request, 'post_review_votes', 'post_review_id', $postReview);
    }

    private function vote(Request $request, string $table, string $foreignKey, PostComment|PostReview $item): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'integer', Rule::in([-1, 1])],
        ]);
        $item->loadMissing(['post.user', 'user']);
        abort_unless($item->post && $request->user()->can('view', $item->post), 404);
        if (! $request->user()->hasRole('moderator')) {
            abort_unless(
                ! $item->is_hidden
                && $item->moderation_status === 'approved'
                && ! ($item->user?->is_banned ?? false),
                404,
            );
        }

        $userId = (int) $request->user()->id;
        $requested = (int) $validated['value'];

        [$score, $vote] = DB::transaction(function () use ($table, $foreignKey, $item, $userId, $requested): array {
            $existing = DB::table($table)
                ->where($foreignKey, $item->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing && (int) $existing->value === $requested) {
                DB::table($table)->where('id', $existing->id)->delete();
                $vote = 0;
            } else {
                DB::table($table)->updateOrInsert(
                    [$foreignKey => $item->id, 'user_id' => $userId],
                    ['value' => $requested, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()],
                );
                $vote = $requested;
            }

            $score = (int) DB::table($table)->where($foreignKey, $item->id)->sum('value');
            $item->update(['vote_score' => $score]);

            return [$score, $vote];
        });

        return response()->json(['score' => $score, 'vote' => $vote]);
    }
}
