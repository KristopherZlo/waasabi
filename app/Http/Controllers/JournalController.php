<?php

namespace App\Http\Controllers;

use App\Models\ContentReport;
use App\Models\Post;
use App\Models\ProjectUpdate;
use App\Services\ContentImageService;
use App\Services\MarkdownService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\UploadAssetService;
use App\Services\UserPayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JournalController extends Controller
{
    public function create(Request $request, string $slug)
    {
        $post = Post::where('type', 'post')->where('slug', $slug)->firstOrFail();
        Gate::authorize('update', $post);

        return view('journal-edit', ['post' => $post, 'update' => null,
            'current_user' => app(UserPayloadService::class)->currentUserPayload()]);
    }

    public function edit(Request $request, string $slug, ProjectUpdate $projectUpdate)
    {
        $post = $projectUpdate->post;
        abort_unless($post->slug === $slug, 404);
        Gate::authorize('update', $post);
        abort_unless($post->user_id === $request->user()->id || $projectUpdate->user_id === $request->user()->id, 403);

        return view('journal-edit', ['post' => $post, 'update' => $projectUpdate,
            'current_user' => app(UserPayloadService::class)->currentUserPayload()]);
    }

    public function store(Request $request, string $slug)
    {
        $post = Post::where('type', 'post')->where('slug', $slug)->firstOrFail();
        Gate::authorize('update', $post);
        $data = $this->validated($request);
        $update = $post->updates()->create($data + ['user_id' => $request->user()->id]);
        $this->syncAssets($post, $request);
        if (! $update->is_hidden) {
            $post->update(['activity_at' => now()]);
            if ($post->visibility === 'public' && ! $post->is_hidden && $post->moderation_status === 'approved') {
                $post->followers()->where('users.id', '!=', $request->user()->id)
                    ->where('users.is_banned', false)->each(function ($follower) use ($post, $request, $update): void {
                        $follower->sendNotification(__('waasabi.journal'), __('waasabi.update_notice', [
                            'name' => $request->user()->name, 'title' => $post->title,
                        ]), route('project', $post->slug).'#update-'.$update->id);
                    });
            }
        }

        return redirect()->route('project', $slug)->withFragment('update-'.$update->id)
            ->with('clear_publish_draft', 'journal-'.$post->id.'-new')
            ->with('toast', $update->is_hidden ? __('ui.moderation.text_queued_toast') : __('waasabi.update_saved'));
    }

    public function update(Request $request, string $slug, ProjectUpdate $projectUpdate)
    {
        $post = $projectUpdate->post;
        abort_unless($post->slug === $slug, 404);
        Gate::authorize('update', $post);
        abort_unless($post->user_id === $request->user()->id || $projectUpdate->user_id === $request->user()->id, 403);
        $data = $this->validated($request);
        // Editing cannot undo a staff decision; restoration is explicit.
        $data['is_hidden'] = $projectUpdate->is_hidden || $data['is_hidden'];
        $projectUpdate->update($data);
        $this->syncAssets($post, $request);

        return redirect()->route('project', $slug)->withFragment('update-'.$projectUpdate->id)
            ->with('clear_publish_draft', 'journal-'.$post->id.'-'.$projectUpdate->id)
            ->with('toast', __('waasabi.update_saved'));
    }

    public function destroy(Request $request, string $slug, ProjectUpdate $projectUpdate)
    {
        $post = $projectUpdate->post;
        abort_unless($post->slug === $slug, 404);
        $actor = $request->user();
        abort_unless($post->user_id === $actor->id || $projectUpdate->user_id === $actor->id || $actor->hasRole('moderator'), 403);
        if ($actor->hasRole('moderator')) {
            abort_if(app(ModerationService::class)->shouldBlock($actor, $post->user), 403);
            app(ModerationService::class)->logAction($request, $actor, 'delete', 'project_update', (string) $projectUpdate->id,
                route('project', $slug), null, ['title' => $projectUpdate->title]);
        }
        $projectUpdate->delete();
        $this->syncAssets($post, $request);

        return redirect()->route('project', $slug)->with('toast', __('ui.project.update_deleted'));
    }

    public function moderate(Request $request, ProjectUpdate $projectUpdate)
    {
        $data = $request->validate(['is_hidden' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:500']]);
        $moderation = app(ModerationService::class);
        abort_if($moderation->shouldBlock($request->user(), $projectUpdate->post->user), 403);
        $projectUpdate->update(['is_hidden' => $data['is_hidden']]);
        $moderation->logAction($request, $request->user(), $data['is_hidden'] ? 'hide' : 'restore', 'project_update',
            (string) $projectUpdate->id, route('project', $projectUpdate->post->slug), $data['reason']);

        return back()->with('toast', __('waasabi.update_saved'));
    }

    public function moderation()
    {
        return view('journal-moderation', [
            'updates' => ProjectUpdate::with('post', 'user')->orderByDesc('is_hidden')->latest()->paginate(20),
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:2', 'max:100000'],
        ]);
        $result = app(TextModerationService::class)->analyze($data['body'], ['type' => 'project_update', 'title' => $data['title']]);
        if ($result['flagged'] ?? false) {
            throw ValidationException::withMessages(['body' => $result['summary'] ?: __('ui.errors.revise_update')]);
        }
        $paths = app(ContentImageService::class)->extractUploadedImagePathsFromHtml(app(MarkdownService::class)->render($data['body']));
        $data['is_hidden'] = $paths && ContentReport::where('content_type', 'content')->where('resolved_status', 'pending')->whereIn('content_id', $paths)->exists();

        return $data;
    }

    private function syncAssets(Post $post, Request $request): void
    {
        app(UploadAssetService::class)->syncEditorAssets($post, $request->user(), $post->body_markdown ?? '');
    }
}
