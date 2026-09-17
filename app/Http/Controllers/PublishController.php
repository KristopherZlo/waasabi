<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublishRequest;
use App\Models\ContentReport;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Services\CoauthorService;
use App\Services\ContentImageService;
use App\Services\ContentModerationService;
use App\Services\ImageUploadService;
use App\Services\MarkdownService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Services\UploadAssetService;
use App\Services\UserPayloadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PublishController extends Controller
{
    public function create()
    {
        $user = Auth::user();
        $coauthorSuggestions = $user ? app(CoauthorService::class)->listSuggestions(200, $user) : [];

        return view('publish', [
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
            'coauthor_suggestions' => $coauthorSuggestions,
            'project_categories' => $this->projectOptions('categories'),
            'project_media_types' => $this->projectOptions('media_types'),
            'project_licenses' => $this->projectOptions('licenses'),
            'can_manage_team' => true,
        ]);
    }

    public function edit(string $slug)
    {
        $post = Post::with(['user', 'editedBy', 'attachments'])->where('slug', $slug)->firstOrFail();
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }
        Gate::authorize('update', $post);

        $canManageTeam = $post->user_id === $user->id;
        $coauthorsValue = $canManageTeam
            ? $post->members()
                ->with('user:id,slug,name')
                ->whereIn('role', ['coauthor', 'Coauthor'])
                ->whereIn('status', ['invited', 'active'])
                ->get()
                ->map(static fn ($membership) => $membership->user?->slug ? '@'.$membership->user->slug : '')
                ->filter()
                ->implode(', ')
            : '';

        $coauthorSuggestions = $canManageTeam
            ? app(CoauthorService::class)->listSuggestions(200, $user)
            : [];

        return view('publish', [
            'edit_post' => [
                'id' => $post->id,
                'saved_at' => $post->updated_at?->getTimestampMs() ?? 0,
                'type' => $post->type,
                'title' => $post->title,
                'subtitle' => $post->subtitle ?? '',
                'feedback_mode' => $post->feedback_mode,
                'category' => $post->category ?? 'other',
                'media_type' => $post->media_type ?? 'mixed',
                'license' => $post->license ?? 'all-rights-reserved',
                'external_url' => $post->external_url ?? '',
                'repository_url' => $post->repository_url ?? '',
                'visibility' => $post->visibility ?? 'public',
                'status' => $post->status ?? 'in_progress',
                'nsfw' => (bool) ($post->nsfw ?? false),
                'tags' => collect($post->tags ?? [])->implode(', '),
                'coauthors' => $coauthorsValue,
                'body' => $post->body_markdown ?? '',
                'question_body' => $post->body_markdown ?? '',
                'attachments' => $post->attachments->map(fn (PostAttachment $attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'size' => $attachment->size,
                    'url' => Storage::disk('public')->url($attachment->path),
                ])->all(),
            ],
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
            'coauthor_suggestions' => $coauthorSuggestions,
            'project_categories' => $this->projectOptions('categories'),
            'project_media_types' => $this->projectOptions('media_types'),
            'project_licenses' => $this->projectOptions('licenses'),
            'can_manage_team' => $canManageTeam,
        ]);
    }

    public function store(StorePublishRequest $request, ImageUploadService $uploadService, ModerationService $moderation, ContentImageService $contentImages, UploadAssetService $assets, ContentModerationService $imageModeration)
    {
        $data = $request->validated();
        $isDraft = ($data['publish_action'] ?? 'publish') === 'draft';

        $postId = $data['post_id'] ?? null;
        $editingPost = $postId ? Post::find($postId) : null;
        if ($postId && ! $editingPost) {
            abort(404);
        }
        if ($editingPost) {
            Gate::authorize('update', $editingPost);
        }

        $firstPublication = ! $editingPost || ! $editingPost->published_at;
        $type = $editingPost ? $editingPost->type : $data['publish_type'];
        if ($type === 'post' && ! $isDraft) {
            $data['body'] = (string) ($data['body'] ?? '');
            if (trim($data['body']) === '') {
                throw ValidationException::withMessages([
                    'body' => __('validation.required', ['attribute' => 'body']),
                ]);
            }
        } elseif ($type === 'question' && ! $isDraft) {
            $data['question_body'] = (string) ($data['question_body'] ?? '');
            if (trim($data['question_body']) === '') {
                throw ValidationException::withMessages([
                    'question_body' => __('validation.required', ['attribute' => 'question_body']),
                ]);
            }
        }

        $title = $data['title'];
        $slug = $editingPost?->slug;
        $baseSlug = Str::slug($title);
        $slugRoot = $baseSlug !== '' ? $baseSlug : Str::random(6);
        $slugCounter = 2;
        $ensureUniqueSlug = static function (string $root, int &$counter): string {
            $candidate = $root;
            while (Post::where('slug', $candidate)->exists()) {
                $candidate = $root.'-'.$counter;
                $counter += 1;
            }

            return $candidate;
        };
        if (! $slug) {
            $slug = $ensureUniqueSlug($slugRoot, $slugCounter);
        }

        $tags = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $canManageTeam = $type === 'post' && (! $editingPost || $editingPost->user_id === $request->user()->id);
        $coauthorResult = $canManageTeam
            ? app(CoauthorService::class)->resolveUsers((string) ($data['coauthors'] ?? ''), $request->user(), 8)
            : ['users' => collect(), 'ids' => []];
        $coauthorUsers = $coauthorResult['users'];
        $coauthorIds = array_values(array_unique(array_map('intval', $coauthorResult['ids'] ?? [])));

        $bodyMarkdown = $type === 'post' ? ($data['body'] ?? '') : ($data['question_body'] ?? '');
        $bodyHtml = app(MarkdownService::class)->render($bodyMarkdown);
        $wordCount = str_word_count(strip_tags($bodyMarkdown));
        $readMinutes = max(1, (int) ceil($wordCount / 200));

        $status = $data['status'] ?? 'in_progress';
        $status = in_array($status, ['in_progress', 'done', 'paused'], true) ? $status : 'in_progress';
        $nsfw = (bool) ($data['nsfw'] ?? false);
        $subtitle = $type === 'post'
            ? ($data['subtitle'] ?? null)
            : Str::limit($bodyMarkdown, 160);

        $moderationFlagged = false;
        $moderationFallback = false;
        $moderationDetails = [];
        $textModerationResult = [
            'flagged' => false,
            'signals' => [],
            'details' => [],
            'score' => 0.0,
            'threshold' => 0.0,
            'metrics' => [],
            'summary' => '',
        ];
        if (! $isDraft && ! $request->user()->hasRole('moderator')) {
            $textModerationResult = app(TextModerationService::class)->analyze($bodyMarkdown, [
                'type' => $type,
                'title' => $title,
                'subtitle' => $subtitle,
            ]);
        }
        $textModerationFlagged = (bool) ($textModerationResult['flagged'] ?? false);
        if ($textModerationFlagged) {
            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            if ($summary !== '') {
                $moderationDetails[] = $summary;
            }
        }
        $moderationFallbackAction = (string) config('services.rekognition.fallback_action', 'mod');
        $moderationFallbackAction = in_array($moderationFallbackAction, ['post', 'nsfw', 'mod'], true)
            ? $moderationFallbackAction
            : 'mod';
        $captureModeration = static function (?array $scanResult, string $context) use (
            &$moderationFlagged,
            &$moderationFallback,
            &$moderationDetails,
            $moderationFallbackAction,
            $imageModeration,
        ): void {
            if (! $scanResult) {
                return;
            }
            $labels = $scanResult['labels'] ?? [];
            if (! empty($labels)) {
                $moderationFlagged = true;
                $moderationDetails[] = $imageModeration->formatLabels($labels, $context);

                return;
            }
            $status = (string) ($scanResult['status'] ?? '');
            if ($status !== 'ok') {
                if ($moderationFallbackAction === 'mod') {
                    $moderationFallback = true;
                    $moderationDetails[] = $imageModeration->formatFallback($scanResult['reason'] ?? null, $context);
                } elseif ($moderationFallbackAction === 'nsfw') {
                    $moderationFlagged = true;
                }
            }
        };

        if ($type === 'post') {
            $bodyImagePaths = $contentImages->extractUploadedImagePathsFromHtml($bodyHtml);
            foreach ($bodyImagePaths as $bodyImagePath) {
                $scanResult = $imageModeration->moderateUploadedImage($bodyImagePath, $request->user(), 'editor');
                $captureModeration($scanResult, 'editor');
            }
        }

        $coverImages = [];
        $maxCoverImages = max(1, (int) config('hub.upload.max_images_per_post', 8));
        if ($type === 'post' && $request->hasFile('cover_images')) {
            $coverFiles = $request->file('cover_images') ?? [];
            if (! is_array($coverFiles)) {
                $coverFiles = [$coverFiles];
            }
            $coverFiles = array_values(array_filter($coverFiles, static fn ($file) => $file instanceof UploadedFile));
            $coverFiles = array_slice($coverFiles, 0, $maxCoverImages);
            foreach ($coverFiles as $coverFile) {
                try {
                    $result = $uploadService->process($coverFile, [
                        'dir' => 'uploads/covers',
                        'max_side' => 2560,
                        'max_pixels' => 16000000,
                    ]);
                } catch (RuntimeException $exception) {
                    foreach ($coverImages as $uploadedCover) {
                        $assets->deletePublicPath($uploadedCover, 'uploads/covers/');
                    }

                    return redirect()
                        ->back()
                        ->withErrors(['cover_images' => $exception->getMessage()])
                        ->withInput();
                }
                $coverImages[] = $result['path'];
                $scanResult = $imageModeration->moderateUploadedImage($result['path'], $request->user(), 'cover');
                $captureModeration($scanResult, 'cover');
            }
        }

        if ($moderationFlagged) {
            $nsfw = true;
        }

        $previousCoverPaths = $editingPost
            ? array_filter(array_merge([$editingPost->cover_url], (array) ($editingPost->album_urls ?? [])))
            : [];
        $coverUrl = $editingPost?->cover_url;
        $albumUrls = $editingPost?->album_urls;
        if (is_string($albumUrls)) {
            $decoded = json_decode($albumUrls, true);
            $albumUrls = is_array($decoded) ? $decoded : preg_split('/\r\n|\n|\r/', $albumUrls);
        }
        $albumUrls = is_array($albumUrls) ? $albumUrls : [];
        if (! empty($coverImages)) {
            $coverUrl = $coverImages[0] ?? $coverUrl;
            $albumUrls = array_slice($coverImages, 1);
        }

        $coverUrl = $coverUrl !== '' ? $coverUrl : null;
        $albumUrls = array_values(array_filter($albumUrls));

        $post = $editingPost ?: new Post;
        if (! $editingPost) {
            $post->user_id = $request->user()->id;
        }
        $post->type = $type;
        $post->is_project = $editingPost?->is_project ?? (bool) ($data['is_project'] ?? true);
        $post->feedback_mode = $data['feedback_mode'] ?? $post->feedback_mode ?? 'sharing';
        $post->category = $type === 'post' ? ($data['category'] ?? 'other') : null;
        $post->media_type = $type === 'post' ? ($data['media_type'] ?? 'mixed') : 'text';
        $post->license = $type === 'post' ? ($data['license'] ?? 'all-rights-reserved') : 'all-rights-reserved';
        $post->title = $title;
        $post->subtitle = $subtitle;
        $post->slug = $slug;
        $post->body_markdown = $bodyMarkdown;
        $post->body_html = $bodyHtml;
        $post->external_url = $type === 'post' ? ($data['external_url'] ?? null) : null;
        $post->repository_url = $type === 'post' ? ($data['repository_url'] ?? null) : null;
        $post->read_time_minutes = $readMinutes;
        $post->status = $type === 'post' ? $status : null;
        $post->visibility = $isDraft ? 'draft' : ($data['visibility'] ?? 'public');
        if (! $isDraft && ! $post->published_at) {
            $post->published_at = now();
        }
        $post->nsfw = $nsfw;
        $post->tags = $tags;
        $post->cover_url = $coverUrl;
        $post->album_urls = $albumUrls;
        if ($editingPost) {
            $post->edited_by = $request->user()->id;
        }

        $post->save();

        if (! empty($coverImages) && $editingPost) {
            foreach ($previousCoverPaths as $oldCover) {
                if (! in_array($oldCover, $coverImages, true)) {
                    $assets->deletePublicPath((string) $oldCover, 'uploads/covers/');
                }
            }
        }

        $removeAttachmentIds = array_values(array_unique(array_map('intval', $data['remove_attachment_ids'] ?? [])));
        if ($editingPost && $removeAttachmentIds !== []) {
            $attachmentsToRemove = $post->attachments()->whereIn('id', $removeAttachmentIds)->get();
            foreach ($attachmentsToRemove as $attachment) {
                Storage::disk('public')->delete($attachment->path);
                $attachment->delete();
            }
        }

        $attachmentFiles = $request->file('attachments', []);
        if (! is_array($attachmentFiles)) {
            $attachmentFiles = [$attachmentFiles];
        }
        if ($type === 'post') {
            foreach ($attachmentFiles as $attachmentFile) {
                if (! $attachmentFile instanceof UploadedFile) {
                    continue;
                }
                $path = $attachmentFile->store('post-attachments/'.$post->id, 'public');
                $mimeType = (string) ($attachmentFile->getMimeType() ?: 'application/octet-stream');
                $kind = str_starts_with($mimeType, 'audio/')
                    ? 'audio'
                    : (str_starts_with($mimeType, 'video/') ? 'video' : 'file');
                $post->attachments()->create([
                    'user_id' => $request->user()->id,
                    'path' => $path,
                    'original_name' => mb_substr($attachmentFile->getClientOriginalName(), 0, 255),
                    'mime_type' => $mimeType,
                    'size' => $attachmentFile->getSize(),
                    'kind' => $kind,
                ]);
            }
        }

        if ($type === 'post') {
            $assets->syncEditorAssets($post, $request->user(), $bodyHtml."\n".$bodyMarkdown);
        }

        if ($canManageTeam) {
            $coauthorMemberships = $post->members()->whereIn('role', ['coauthor', 'Coauthor'])->get()->keyBy('user_id');
            foreach ($coauthorIds as $coauthorId) {
                $membership = $coauthorMemberships->get($coauthorId);
                $needsInvitation = ! $membership || in_array($membership->status, ['declined', 'removed'], true);
                if ($membership) {
                    if ($needsInvitation) {
                        $membership->update(['status' => 'invited', 'invited_by' => $request->user()->id, 'accepted_at' => null]);
                    }
                } else {
                    $post->members()->create([
                        'user_id' => $coauthorId,
                        'invited_by' => $request->user()->id,
                        'role' => 'coauthor',
                        'status' => 'invited',
                        'can_edit' => true,
                    ]);
                }
                if ($needsInvitation) {
                    $coauthorUsers->firstWhere('id', $coauthorId)?->sendNotification(
                        __('ui.notifications.type_project_invitation'),
                        __('ui.notifications.project_invited', [
                            'user' => $request->user()->name,
                            'title' => $post->title,
                        ]),
                        route('project', $post->slug),
                    );
                }
            }

            $removedMemberships = $post->members()
                ->with('user')
                ->whereIn('role', ['coauthor', 'Coauthor'])
                ->whereNotIn('user_id', $coauthorIds ?: [0])
                ->whereIn('status', ['invited', 'active'])
                ->get();
            foreach ($removedMemberships as $membership) {
                $membership->update(['status' => 'removed', 'accepted_at' => null]);
                $membership->user?->sendNotification(
                    __('ui.notifications.type_project_team'),
                    __('ui.notifications.member_removed', ['title' => $post->title]),
                    route('project', $post->slug),
                );
            }
        }

        $currentMediaPaths = array_values(array_unique(array_filter(array_merge(
            [$post->cover_url],
            (array) $post->album_urls,
            $type === 'post' ? $contentImages->extractUploadedImagePathsFromHtml($bodyHtml) : [],
        ))));
        $hasPendingMediaReview = $currentMediaPaths !== []
            && ContentReport::query()
                ->where('content_type', 'content')
                ->where('resolved_status', 'pending')
                ->whereIn('content_id', $currentMediaPaths)
                ->exists();
        $moderationStatus = $moderationFlagged || $moderationFallback || $hasPendingMediaReview
            ? 'pending'
            : 'approved';
        $staffModerated = $post->hidden_by !== null && (int) $post->hidden_by !== (int) $post->user_id;
        if (! $staffModerated) {
            $post->moderation_status = $moderationStatus;
            $post->is_hidden = $moderationStatus !== 'approved';
            $post->hidden_at = $moderationStatus !== 'approved' ? now() : null;
            $post->hidden_by = null;
            $post->save();
        }

        $toastMessage = null;
        if ($textModerationFlagged) {
            $signals = array_values(array_unique($textModerationResult['signals'] ?? []));
            $contentUrl = $type === 'question'
                ? route('questions.show', $post->slug)
                : route('project', $post->slug);

            $summary = trim((string) ($textModerationResult['summary'] ?? ''));
            $moderation->logSystemAction(
                $request,
                'text_moderation',
                'post',
                (string) $post->id,
                $contentUrl,
                $summary !== '' ? $summary : null,
                [
                    'details' => $moderationDetails,
                    'actor_id' => $request->user()?->id,
                    'meta' => [
                        'reason' => 'text_moderation',
                        'score' => $textModerationResult['score'] ?? null,
                        'threshold' => $textModerationResult['threshold'] ?? null,
                        'signals' => array_values(array_unique($signals)),
                        'details' => $textModerationResult['details'] ?? [],
                        'metrics' => $textModerationResult['metrics'] ?? [],
                    ],
                ],
            );

            $request->user()?->sendNotification(
                __('ui.notifications.type_moderation'),
                __('ui.moderation.text_queued_notification'),
                $contentUrl,
            );
            $toastMessage = __('ui.moderation.text_queued_toast');
        }

        if ($isDraft) {
            return redirect()->route('posts.edit', $post->slug)->with('toast', __('ui.publish.draft_saved'))
                ->with('clear_publish_draft', $postId ?: 'new');
        }

        if ($firstPublication && $post->visibility === 'public' && ! $post->is_hidden && $post->moderation_status === 'approved') {
            $post->user->followers()->where('users.is_banned', false)->each(function ($follower) use ($post): void {
                $follower->sendNotification(__('ui.notifications.type_project_team'), __('waasabi.post_notice', [
                    'name' => $post->user->name, 'title' => $post->title,
                ]), $post->type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug));
            });
        }

        $redirect = $type === 'question'
            ? redirect()->route('questions.show', $post->slug)
            : redirect()->route('project', $post->slug);
        $redirect->with('clear_publish_draft', $postId ?: 'new');

        return $toastMessage !== null
            ? $redirect->with('toast', $toastMessage)
            : $redirect;
    }

    private function projectOptions(string $group): array
    {
        return collect(config('projects.'.$group, []))
            ->map(static fn (string $translationKey): string => __($translationKey))
            ->all();
    }
}
