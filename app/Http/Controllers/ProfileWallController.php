<?php

namespace App\Http\Controllers;

use App\Models\ProfileWallPost;
use App\Models\User;
use App\Services\TextModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileWallController extends Controller
{
    public function store(Request $request, User $user, TextModerationService $moderation): RedirectResponse
    {
        abort_if($user->is_banned, 404);
        abort_unless($request->user()->id === $user->id || $user->wall_mode === 'everyone', 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000'], 'return_view' => ['nullable', 'in:work,wall']]);
        $body = trim(strip_tags($data['body']));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('validation.required', ['attribute' => 'body'])]);
        }
        $result = $moderation->analyze($body, ['type' => 'profile_wall', 'profile' => $user->name]);
        if (($result['flagged'] ?? false) === true) {
            throw ValidationException::withMessages(['body' => (string) ($result['summary'] ?: __('ui.moderation.text_flagged_detail'))]);
        }

        $post = ProfileWallPost::create(['profile_user_id' => $user->id, 'user_id' => $request->user()->id, 'body' => $body]);
        if ($user->id !== $request->user()->id) {
            $user->sendPreferredNotification('notify_comments', __('ui.notifications.type_comment'), $request->user()->name.' wrote on your wall.', route('profile.show', $user->slug).'#wall-post-'.$post->id);
        }

        $view = $data['return_view'] ?? 'wall';
        $response = redirect()->route('profile.show', ['slug' => $user->slug, 'view' => $view]);

        return $view === 'wall' ? $response->withFragment('wall-post-'.$post->id) : $response;
    }

    public function destroy(Request $request, ProfileWallPost $profileWallPost): RedirectResponse
    {
        abort_unless(in_array($request->user()->id, [$profileWallPost->profile_user_id, $profileWallPost->user_id], true) || $request->user()->hasRole('moderator'), 403);
        $profileWallPost->delete();

        return back();
    }

    public function update(Request $request, ProfileWallPost $profileWallPost, TextModerationService $moderation): RedirectResponse
    {
        abort_unless($request->user()->id === $profileWallPost->user_id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:2000']]);
        $body = trim(strip_tags($data['body']));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('validation.required', ['attribute' => 'body'])]);
        }
        $result = $moderation->analyze($body, ['type' => 'profile_wall']);
        if (($result['flagged'] ?? false) === true) {
            throw ValidationException::withMessages(['body' => (string) ($result['summary'] ?: __('ui.moderation.text_flagged_detail'))]);
        }
        $profileWallPost->update(['body' => $body]);

        return back();
    }
}
