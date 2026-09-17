<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollaborationApplicationRequest;
use App\Http\Requests\StoreCollaborationRequest;
use App\Models\CollaborationApplication;
use App\Models\CollaborationComment;
use App\Models\CollaborationRequest;
use App\Models\Post;
use App\Models\ProjectMember;
use App\Services\CollaborationService;
use App\Services\TextModerationService;
use App\Services\UserPayloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollaborationController extends Controller
{
    public function __construct(
        private CollaborationService $collaboration,
        private UserPayloadService $payloadService,
        private TextModerationService $textModeration,
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $filters = array_filter(
            $request->only(['status', 'role', 'availability', 'format', 'q']),
            static fn ($value) => $value !== null && $value !== '',
        );

        return redirect()->route('feed', array_merge(
            ['stream' => 'collaboration'],
            $filters,
        ));
    }

    public function create(Request $request): View
    {
        return view('quick-help', [
            'collaboration_roles' => $this->collaboration->roleOptions(),
            'collaboration_availability' => $this->collaboration->availabilityOptions(),
            'collaboration_formats' => $this->collaboration->formatOptions(),
            'manageable_projects' => $this->collaboration->manageableProjects($request->user()),
            'current_user' => $this->payloadService->currentUserPayload(),
        ]);
    }

    public function show(Request $request, CollaborationRequest $collaborationRequest): View
    {
        $viewer = $request->user();
        $collaborationRequest->load([
            'post.user',
            'user',
            'comments' => fn ($query) => $query->with('user')->oldest(),
        ])->loadCount(['applications', 'comments']);
        $post = $collaborationRequest->post;
        $isManager = $viewer && $viewer->id === $collaborationRequest->user_id;
        abort_unless(CollaborationRequest::query()->visibleTo($request->user())->whereKey($collaborationRequest->id)->exists(), 404);

        if ($isManager) {
            $collaborationRequest->load([
                'applications.user:id,slug,name,avatar,role',
                'applications.applicantPost:id,user_id,slug,title,visibility,is_hidden,moderation_status',
            ]);
        } elseif ($viewer) {
            $collaborationRequest->load([
                'applications' => fn ($query) => $query
                    ->where('user_id', $viewer->id)
                    ->with('applicantPost:id,user_id,slug,title,visibility,is_hidden,moderation_status'),
            ]);
        }

        return view('collaboration-show', [
            'collaboration_request' => $collaborationRequest,
            'collaboration_roles' => $this->collaboration->roleOptions(),
            'collaboration_availability' => $this->collaboration->availabilityOptions(),
            'collaboration_formats' => $this->collaboration->formatOptions(),
            'manageable_projects' => $viewer ? $this->collaboration->manageableProjects($viewer) : collect(),
            'is_manager' => $isManager,
            'current_user' => $this->payloadService->currentUserPayload(),
        ]);
    }

    public function storeComment(Request $request, CollaborationRequest $collaborationRequest): RedirectResponse
    {
        $user = $request->user();
        $collaborationRequest->load(['post.user', 'user']);
        $post = $collaborationRequest->post;
        abort_unless(CollaborationRequest::query()->visibleTo($request->user())->whereKey($collaborationRequest->id)->exists(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);
        $body = trim(strip_tags($data['body']));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('validation.required', ['attribute' => 'body'])]);
        }
        $this->ensureTextIsAllowed($body, $collaborationRequest->title, 'body');

        $comment = $collaborationRequest->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        if ($collaborationRequest->user && $collaborationRequest->user_id !== $user->id) {
            $collaborationRequest->user->sendPreferredNotification(
                'notify_comments',
                __('ui.notifications.type_comment'),
                __('ui.notifications.comment_added', [
                    'user' => $user->name,
                    'title' => $collaborationRequest->title,
                ]),
                route('collaboration.show', $collaborationRequest).'#comment-'.$comment->id,
            );
        }

        return redirect()->route('collaboration.show', $collaborationRequest)
            ->withFragment('comment-'.$comment->id)
            ->with('toast', __('ui.collaboration.comment_sent'));
    }

    public function destroyComment(Request $request, CollaborationComment $collaborationComment): RedirectResponse
    {
        abort_unless(
            $collaborationComment->user_id === $request->user()->id
            || $request->user()->hasRole('moderator'),
            403,
        );
        $collaborationComment->delete();

        return back()->with('toast', __('ui.collaboration.comment_deleted'));
    }

    public function updateComment(Request $request, CollaborationComment $collaborationComment): RedirectResponse
    {
        abort_unless($collaborationComment->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $body = trim(strip_tags($data['body']));
        $this->ensureTextIsAllowed($body, 'Collaboration comment', 'body');
        $collaborationComment->update(['body' => $body]);

        return back();
    }

    public function storeApplicationMessage(Request $request, CollaborationApplication $application): RedirectResponse
    {
        $application->load('collaborationRequest.user', 'user');
        $collaborationRequest = $application->collaborationRequest;
        $participantIds = [$application->user_id, $collaborationRequest->user_id];
        abort_unless(in_array($request->user()->id, $participantIds, true), 403);
        abort_unless(in_array($application->status, ['pending', 'accepted'], true), 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $body = trim(strip_tags($data['body']));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => __('validation.required', ['attribute' => 'body'])]);
        }
        $this->ensureTextIsAllowed($body, $collaborationRequest->title, 'body');

        $message = $application->messages()->create([
            'collaboration_request_id' => $collaborationRequest->id,
            'user_id' => $request->user()->id,
            'body' => $body,
        ]);
        $recipient = $request->user()->id === $application->user_id ? $collaborationRequest->user : $application->user;
        $recipient?->sendPreferredNotification(
            'notify_comments',
            __('ui.notifications.type_collaboration'),
            __('ui.notifications.collaboration_message', ['user' => $request->user()->name, 'title' => $collaborationRequest->title]),
            route('collaboration.show', $collaborationRequest).'#application-'.$application->id,
        );

        return redirect()->route('collaboration.show', $collaborationRequest)
            ->withFragment('application-'.$application->id)
            ->with('toast', __('ui.collaboration.message_sent'));
    }

    public function store(StoreCollaborationRequest $request): RedirectResponse
    {
        if (honeypotTripped($request)) {
            return back()->withErrors(['title' => __('ui.auth.captcha_failed')])->withInput();
        }

        $data = $request->validated();
        $post = ! empty($data['post_id']) ? Post::query()->where('type', 'post')->findOrFail($data['post_id']) : null;
        if ($post) {
            abort_unless($post->user_id === $request->user()->id, 403);
        }
        if ($post && ($post->visibility !== 'public' || $post->is_hidden || $post->moderation_status !== 'approved')) {
            return back()->withErrors(['post_id' => __('ui.collaboration.project_public_error')])->withInput();
        }
        $this->ensureTextIsAllowed($data['summary'], $data['title']);

        $collaborationRequest = CollaborationRequest::create([
            'post_id' => $post?->id,
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'role' => $data['role'],
            'skills' => $this->collaboration->parseSkills((string) ($data['skills'] ?? '')),
            'availability' => $data['availability'],
            'format' => $data['format'],
            'summary' => $data['summary'],
            'status' => 'open',
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 60)),
        ]);

        return redirect()->route('collaboration.show', $collaborationRequest)
            ->with('toast', __('ui.collaboration.posted'));
    }

    public function update(StoreCollaborationRequest $request, CollaborationRequest $collaborationRequest): RedirectResponse
    {
        abort_unless($collaborationRequest->user_id === $request->user()->id, 403);
        $data = $request->validated();
        $post = ! empty($data['post_id']) ? Post::query()->where('type', 'post')->findOrFail($data['post_id']) : null;
        abort_unless(! $post || $post->user_id === $request->user()->id, 403);
        $this->ensureTextIsAllowed($data['summary'], $data['title']);
        $collaborationRequest->update([
            'post_id' => $post?->id, 'title' => $data['title'], 'role' => $data['role'],
            'skills' => $this->collaboration->parseSkills((string) ($data['skills'] ?? '')),
            'availability' => $data['availability'], 'format' => $data['format'], 'summary' => $data['summary'],
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 60)),
        ]);

        return redirect()->route('collaboration.show', $collaborationRequest);
    }

    public function apply(
        StoreCollaborationApplicationRequest $request,
        CollaborationRequest $collaborationRequest,
    ): RedirectResponse {
        if (honeypotTripped($request)) {
            return back()->withErrors(['message' => __('ui.auth.captcha_failed')]);
        }
        $user = $request->user();
        $post = $collaborationRequest->post()->with('user')->first();
        abort_unless(CollaborationRequest::query()->visibleTo($request->user())->whereKey($collaborationRequest->id)->exists(), 404);
        if (! $collaborationRequest->isOpen()) {
            return back()->withErrors(['message' => __('ui.collaboration.closed_error')]);
        }
        if ($collaborationRequest->user_id === $user->id || $post?->user_id === $user->id) {
            abort(403);
        }
        if ($post && $post->members()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            return back()->withErrors(['message' => __('ui.collaboration.member_error')]);
        }

        $data = $request->validated();
        $applicantPostId = isset($data['applicant_post_id']) ? (int) $data['applicant_post_id'] : null;
        if ($applicantPostId && $applicantPostId === $post?->id) {
            throw ValidationException::withMessages([
                'applicant_post_id' => __('ui.collaboration.application_project_same_error'),
            ]);
        }
        if ($applicantPostId && ! Post::query()
            ->whereKey($applicantPostId)
            ->where('type', 'post')
            ->where('user_id', $user->id)
            ->where('visibility', 'public')
            ->where('is_hidden', false)
            ->where('moderation_status', 'approved')
            ->exists()) {
            throw ValidationException::withMessages([
                'applicant_post_id' => __('ui.collaboration.application_project_error'),
            ]);
        }
        $this->ensureTextIsAllowed($data['message'], __('ui.collaboration.application_title'), 'message');
        $application = $collaborationRequest->applications()->firstOrNew(['user_id' => $user->id]);
        if ($application->exists && ! in_array($application->status, ['withdrawn', 'closed'], true)) {
            return back()->withErrors(['message' => __('ui.collaboration.duplicate_application_error')]);
        }
        $application->fill([
            'applicant_post_id' => $applicantPostId,
            'message' => $data['message'],
            'status' => 'pending',
            'decided_at' => null,
        ])->save();

        $collaborationRequest->user->sendNotification(
            __('ui.notifications.type_collaboration'),
            __('ui.notifications.collaboration_applied', [
                'user' => $user->name,
                'title' => $collaborationRequest->title,
            ]),
            route('collaboration.show', $collaborationRequest),
        );

        return back()->with('toast', __('ui.collaboration.application_sent'));
    }

    public function decide(
        Request $request,
        CollaborationApplication $application,
    ): RedirectResponse {
        $data = $request->validate(['status' => ['required', 'in:accepted,rejected']]);
        $application->load('collaborationRequest.post', 'user');
        $collaborationRequest = $application->collaborationRequest;
        abort_unless($collaborationRequest->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($application, $collaborationRequest, $data, $request): void {
            $lockedRequest = CollaborationRequest::query()->whereKey($collaborationRequest->id)->lockForUpdate()->firstOrFail();
            $lockedApplication = CollaborationApplication::query()
                ->whereKey($application->id)
                ->where('collaboration_request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedApplication->status !== 'pending' || ! $lockedRequest->isOpen()) {
                throw ValidationException::withMessages(['status' => __('ui.collaboration.application_decided_error')]);
            }
            $status = $data['status'];
            $lockedApplication->update(['status' => $status, 'decided_at' => now()]);

            if ($status === 'accepted' && $lockedRequest->post_id) {
                ProjectMember::updateOrCreate(
                    ['post_id' => $lockedRequest->post_id, 'user_id' => $lockedApplication->user_id],
                    [
                        'invited_by' => $request->user()->id,
                        'role' => $lockedRequest->role,
                        'status' => 'active',
                        'can_edit' => false,
                        'accepted_at' => now(),
                    ],
                );

            }
        });

        $application->user->sendNotification(
            __('ui.notifications.type_collaboration'),
            $data['status'] === 'accepted'
                ? __('ui.notifications.application_accepted', ['title' => $collaborationRequest->title])
                : __('ui.notifications.application_rejected', ['title' => $collaborationRequest->title]),
            route('collaboration.show', $collaborationRequest),
        );

        return back()->with('toast', __('ui.collaboration.application_updated'));
    }

    public function withdraw(Request $request, CollaborationApplication $application): RedirectResponse
    {
        abort_unless($application->user_id === $request->user()->id, 403);
        if (in_array($application->status, ['pending', 'accepted'], true)) {
            DB::transaction(function () use ($application, $request): void {
                $application->load('collaborationRequest');
                if ($application->status === 'accepted' && $application->collaborationRequest->post_id) {
                    ProjectMember::query()->where('post_id', $application->collaborationRequest->post_id)
                        ->where('user_id', $request->user()->id)->update(['status' => 'removed', 'can_edit' => false]);
                }
                $application->update(['status' => 'withdrawn', 'decided_at' => now()]);
            });
        }

        return back()->with('toast', __('ui.collaboration.application_withdrawn_toast'));
    }

    public function updateStatus(Request $request, CollaborationRequest $collaborationRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,closed']]);
        $collaborationRequest->load('post');
        abort_unless($collaborationRequest->user_id === $request->user()->id, 403);
        $closedApplicants = collect();
        if ($data['status'] === 'closed') {
            $closedApplicants = $collaborationRequest->applications()
                ->with('user')
                ->where('status', 'pending')
                ->get();
            $collaborationRequest->applications()
                ->whereIn('id', $closedApplicants->pluck('id'))
                ->update(['status' => 'closed', 'decided_at' => now()]);
        }
        $collaborationRequest->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
            'expires_at' => $data['status'] === 'open' && $collaborationRequest->expires_at?->isPast()
                ? now()->addDays(60)
                : $collaborationRequest->expires_at,
        ]);
        $closedApplicants->each(function (CollaborationApplication $application) use ($collaborationRequest): void {
            $application->user?->sendNotification(
                __('ui.notifications.type_collaboration'),
                __('ui.notifications.application_closed', ['title' => $collaborationRequest->title]),
                route('collaboration.show', $collaborationRequest),
            );
        });

        return back()->with(
            'toast',
            $data['status'] === 'open'
                ? __('ui.collaboration.request_reopened')
                : __('ui.collaboration.request_closed'),
        );
    }

    public function destroy(Request $request, CollaborationRequest $collaborationRequest): RedirectResponse
    {
        $collaborationRequest->load('post');
        abort_unless($collaborationRequest->user_id === $request->user()->id, 403);
        $applicants = $collaborationRequest->applications()->with('user')->where('status', 'pending')->get();
        $projectUrl = $collaborationRequest->post ? route('project', $collaborationRequest->post->slug) : route('collaboration');
        $title = $collaborationRequest->title;
        $collaborationRequest->delete();
        $applicants->each(function (CollaborationApplication $application) use ($projectUrl, $title): void {
            $application->user?->sendNotification(
                __('ui.notifications.type_collaboration'),
                __('ui.notifications.application_closed', ['title' => $title]),
                $projectUrl,
            );
        });

        return redirect()->route('collaboration')->with('toast', __('ui.collaboration.request_deleted'));
    }

    private function ensureTextIsAllowed(string $text, string $title, string $field = 'summary'): void
    {
        $result = $this->textModeration->analyze($text, ['type' => 'collaboration', 'title' => $title]);
        if (($result['flagged'] ?? false) === true) {
            throw ValidationException::withMessages([
                $field => (string) ($result['summary'] ?: __('ui.collaboration.moderation_error')),
            ]);
        }
    }
}
