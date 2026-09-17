<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Services\UserPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommunityController extends Controller
{
    public function follow(Request $request, Post $post)
    {
        Gate::authorize('view', $post);
        abort_unless($post->type === 'post', 404);
        $data = $request->validate(['following' => ['required', 'boolean']]);
        if ($data['following']) {
            $post->followers()->syncWithoutDetaching([$request->user()->id]);
        } else {
            $post->followers()->detach($request->user()->id);
        }

        return back()->with('toast', __('waasabi.follow_saved'));
    }

    public function people(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:80']]);
        $term = trim($data['q'] ?? '');
        $people = User::query()->where('is_banned', false)->where('open_to_help', true)
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('skills', 'like', '%'.$term.'%')
                    ->orWhere('bio', 'like', '%'.$term.'%');
            }))->orderBy('name')->paginate(18)->withQueryString();

        return view('people', [
            'people' => $people,
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
        ]);
    }
}
