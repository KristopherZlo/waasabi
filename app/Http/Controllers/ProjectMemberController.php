<?php

namespace App\Http\Controllers;

use App\Models\CollaborationApplication;
use App\Models\Post;
use App\Models\ProjectMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMemberController extends Controller
{
    public function accept(Request $request, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($projectMember->user_id === $request->user()->id, 403);
        abort_unless($projectMember->status === 'invited', 409);
        $projectMember->update(['status' => 'active', 'accepted_at' => now()]);
        $projectMember->post->user->sendNotification(
            __('ui.notifications.type_project_team'),
            __('ui.notifications.member_accepted', [
                'user' => $request->user()->name,
                'title' => $projectMember->post->title,
            ]),
            route('project', $projectMember->post->slug),
        );

        return back()->with('toast', __('ui.project.invitation_accepted'));
    }

    public function decline(Request $request, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($projectMember->user_id === $request->user()->id, 403);
        abort_unless($projectMember->status === 'invited', 409);
        $projectMember->update(['status' => 'declined', 'accepted_at' => null]);
        $projectMember->post->user->sendNotification(
            __('ui.notifications.type_project_team'),
            __('ui.notifications.member_declined', [
                'user' => $request->user()->name,
                'title' => $projectMember->post->title,
            ]),
            route('project', $projectMember->post->slug),
        );

        return back()->with('toast', __('ui.project.invitation_declined'));
    }

    public function permissions(Request $request, Post $post, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        abort_unless($projectMember->post_id === $post->id && $projectMember->status === 'active', 404);
        $data = $request->validate(['can_edit' => ['required', 'boolean']]);
        $projectMember->update(['can_edit' => $data['can_edit']]);

        return back()->with('toast', __('waasabi.permissions_saved'));
    }

    public function destroy(Request $request, Post $post, ProjectMember $projectMember): RedirectResponse
    {
        abort_unless($projectMember->post_id === $post->id, 404);
        $actor = $request->user();
        abort_unless($post->user_id === $actor->id || $projectMember->user_id === $actor->id, 403);
        abort_if($projectMember->user_id === $post->user_id, 422);
        $projectMember->update(['status' => 'removed', 'can_edit' => false]);
        CollaborationApplication::query()->where('user_id', $projectMember->user_id)
            ->where('status', 'accepted')->whereHas('collaborationRequest', fn ($q) => $q->where('post_id', $post->id))
            ->update(['status' => 'withdrawn', 'decided_at' => now()]);
        if ($projectMember->user_id === $actor->id) {
            $post->user->sendNotification(
                __('ui.notifications.type_project_team'),
                __('ui.notifications.member_left', ['user' => $actor->name, 'title' => $post->title]),
                route('project', $post->slug),
            );
        } else {
            $projectMember->user->sendNotification(
                __('ui.notifications.type_project_team'),
                __('ui.notifications.member_removed', ['title' => $post->title]),
                route('project', $post->slug),
            );
        }

        return back()->with(
            'toast',
            $projectMember->user_id === $actor->id
                ? __('ui.project.member_left')
                : __('ui.project.member_removed'),
        );
    }
}
