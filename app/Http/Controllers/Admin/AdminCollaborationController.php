<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Services\AutoModerationService;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminCollaborationController extends Controller
{
    public function bulk(Request $request, ModerationService $moderation, AutoModerationService $reports): RedirectResponse
    {
        $data = $request->validate([
            'request_ids' => ['required', 'array', 'min:1', 'max:100'],
            'request_ids.*' => ['integer', 'distinct', 'exists:collaboration_requests,id'],
            'action' => ['required', 'in:close,reopen,delete'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $actor = $request->user();
        abort_unless($actor, 403);
        $collaborations = CollaborationRequest::query()
            ->with(['user', 'post', 'applications.user', 'comments'])
            ->whereKey($data['request_ids'])
            ->get();
        foreach ($collaborations as $collaboration) {
            abort_if($moderation->shouldBlock($actor, $collaboration->user), 403);
        }

        foreach ($collaborations as $collaboration) {
            $url = route('collaboration.show', $collaboration);
            $moderation->logAction(
                $request,
                $actor,
                $data['action'],
                'collaboration',
                (string) $collaboration->id,
                $url,
                $data['reason'],
                ['title' => $collaboration->title, 'author_id' => $collaboration->user_id],
            );

            if ($data['action'] === 'delete') {
                $reports->resolveReportsForModel($collaboration, 'confirmed', 'delete');
                $collaboration->comments->each(fn ($comment) => $reports->resolveReportsForModel($comment, 'confirmed', 'delete'));
                $recipients = $collaboration->applications->pluck('user')->push($collaboration->user)->filter()->unique('id');
                $title = $collaboration->title;
                $collaboration->delete();
                $recipients->each(fn ($user) => $user->sendNotification(
                    __('ui.notifications.type_collaboration'),
                    __('ui.notifications.collaboration_removed', ['title' => $title]),
                    route('feed', ['stream' => 'collaboration']),
                ));

                continue;
            }

            $status = $data['action'] === 'close' ? 'closed' : 'open';
            if ($status === 'closed') {
                $collaboration->applications()->where('status', 'pending')->update([
                    'status' => 'closed',
                    'decided_at' => now(),
                ]);
            }
            $collaboration->update([
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
            ]);
        }

        return redirect()->route('admin', ['tab' => 'collaborations'])
            ->with('toast', __('ui.admin.bulk_updated', ['count' => $collaborations->count()]));
    }

    public function destroyApplication(
        Request $request,
        CollaborationApplication $application,
        ModerationService $moderation,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $application->load(['user', 'collaborationRequest']);
        abort_if($moderation->shouldBlock($request->user(), $application->user), 403);
        $collaboration = $application->collaborationRequest;
        $moderation->logAction(
            $request,
            $request->user(),
            'delete',
            'collaboration_application',
            (string) $application->id,
            route('collaboration.show', $collaboration),
            $data['reason'],
            ['collaboration_id' => $collaboration->id, 'author_id' => $application->user_id],
        );
        $application->delete();

        return back()->with('toast', __('ui.admin.application_removed'));
    }

    public function destroyComment(
        Request $request,
        CollaborationComment $comment,
        ModerationService $moderation,
        AutoModerationService $reports,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $comment->load(['user', 'collaborationRequest']);
        abort_if($moderation->shouldBlock($request->user(), $comment->user), 403);
        $collaboration = $comment->collaborationRequest;
        $moderation->logAction(
            $request,
            $request->user(),
            'delete',
            'collaboration_comment',
            (string) $comment->id,
            route('collaboration.show', $collaboration).'#comment-'.$comment->id,
            $data['reason'],
            ['collaboration_id' => $collaboration->id, 'author_id' => $comment->user_id],
        );
        $reports->resolveReportsForModel($comment, 'confirmed', 'delete');
        $comment->delete();

        return back()->with('toast', __('ui.admin.comment_removed'));
    }

    public function dismissRequestReport(
        Request $request,
        CollaborationRequest $collaboration,
        ModerationService $moderation,
        AutoModerationService $reports,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor, 403);
        $collaboration->loadMissing('user');
        abort_if($moderation->shouldBlock($actor, $collaboration->user), 403);
        $moderation->logAction(
            $request,
            $actor,
            'dismiss_report',
            'collaboration',
            (string) $collaboration->id,
            route('collaboration.show', $collaboration),
            null,
            ['title' => $collaboration->title, 'author_id' => $collaboration->user_id],
        );
        $reports->resolveReportsForModel($collaboration, 'rejected', 'dismiss_report');

        return redirect()->route('admin', ['tab' => 'moderation'])->with('toast', __('ui.admin.report_dismissed'));
    }

    public function dismissCommentReport(
        Request $request,
        CollaborationComment $comment,
        ModerationService $moderation,
        AutoModerationService $reports,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor, 403);
        $comment->loadMissing(['user', 'collaborationRequest']);
        abort_if($moderation->shouldBlock($actor, $comment->user), 403);
        $moderation->logAction(
            $request,
            $actor,
            'dismiss_report',
            'collaboration_comment',
            (string) $comment->id,
            route('collaboration.show', $comment->collaborationRequest).'#comment-'.$comment->id,
            null,
            ['collaboration_id' => $comment->collaboration_request_id, 'author_id' => $comment->user_id],
        );
        $reports->resolveReportsForModel($comment, 'rejected', 'dismiss_report');

        return redirect()->route('admin', ['tab' => 'moderation'])->with('toast', __('ui.admin.report_dismissed'));
    }
}
