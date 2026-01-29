<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Services\CoauthorService;
use App\Services\ContentImageService;
use App\Services\MarkdownService;
use App\Services\ImageUploadService;
use App\Services\ModerationService;
use App\Services\TextModerationService;
use App\Http\Requests\StorePublishRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
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
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
            'coauthor_suggestions' => $coauthorSuggestions,
        ]);
    }

    public function edit(string $slug)
    {
        if (!safeHasTable('posts')) {
            abort(503);
        }
        $post = Post::with(['user', 'editedBy'])->where('slug', $slug)->firstOrFail();
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }
        Gate::authorize('update', $post);

        $coauthorsValue = '';
        if (safeHasColumn('posts', 'coauthor_user_ids') && safeHasColumn('users', 'slug')) {
            $coauthorIds = collect($post->coauthor_user_ids ?? [])
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values();
            if ($coauthorIds->isNotEmpty()) {
                $coauthorUsers = User::query()
                    ->select(['id', 'slug', 'name'])
                    ->whereIn('id', $coauthorIds->all());
                if (safeHasColumn('users', 'is_banned')) {
                    $coauthorUsers->where('is_banned', false);
                }
                if (safeHasColumn('users', 'privacy_allow_mentions')) {
                    $coauthorUsers->where('privacy_allow_mentions', true);
                }
                $coauthorsValue = $coauthorUsers
                    ->get()
                    ->map(static fn (User $user) => $user->slug ? '@' . $user->slug : '')
                    ->filter()
                    ->implode(', ');
            }
        }

        $coauthorSuggestions = app(CoauthorService::class)->listSuggestions(200, $user);

        return view('publish', [
            'edit_post' => [
                'id' => $post->id,
                'type' => $post->type,
                'title' => $post->title,
                'subtitle' => $post->subtitle ?? '',
                'status' => $post->status ?? 'in_progress',
                'nsfw' => (bool) ($post->nsfw ?? false),
                'tags' => collect($post->tags ?? [])->implode(', '),
                'coauthors' => $coauthorsValue,
                'body' => $post->body_markdown ?? '',
                'question_body' => $post->body_markdown ?? '',
            ],
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
            'coauthor_suggestions' => $coauthorSuggestions,
        ]);
    }

    public function store(StorePublishRequest $request, ImageUploadService $uploadService, ModerationService $moderation, ContentImageService $contentImages)
    {
        if (!safeHasTable('posts')) {
            abort(503);
        }

        $data = $request->validated();

        $postId = $data['post_id'] ?? null;
        $editingPost = $postId ? Post::find($postId) : null;
        if ($postId && !$editingPost) {
            abort(404);
        }
        if ($editingPost) {
            Gate::authorize('update', $editingPost);
        }

        $type = $editingPost ? $editingPost->type : $data['publish_type'];
        if ($type === 'post') {
            $data['body'] = (string) ($data['body'] ?? '');
            if (trim($data['body']) === '') {
                throw ValidationException::withMessages([
                    'body' => __('validation.required', ['attribute' => 'body']),
                ]);
            }
        } else {
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
                $candidate = $root . '-' . $counter;
                $counter += 1;
            }
            return $candidate;
        };
        if (!$slug) {
            $slug = $ensureUniqueSlug($slugRoot, $slugCounter);
        }

        $tags = collect(explode(',', $data['tags'] ?? ''))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();

        $existingCoauthorIds = [];
        if ($editingPost && safeHasColumn('posts', 'coauthor_user_ids')) {
            $existingCoauthorIds = collect($editingPost->coauthor_user_ids ?? [])
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $coauthorResult = app(CoauthorService::class)->resolveUsers((string) ($data['coauthors'] ?? ''), $request->user(), 8);
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
        if (!$request->user()->hasRole('moderator')) {
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
        ): void {
            if (!$scanResult) {
                return;
            }
            $labels = $scanResult['labels'] ?? [];
            if (!empty($labels)) {
                $moderationFlagged = true;
                $moderationDetails[] = formatModerationDetails($labels, $context);
                return;
            }
            $status = (string) ($scanResult['status'] ?? '');
            if ($status !== 'ok') {
                if ($moderationFallbackAction === 'mod') {
                    $moderationFallback = true;
                    $moderationDetails[] = formatModerationFallbackDetails($scanResult['reason'] ?? null, $context);
                } elseif ($moderationFallbackAction === 'nsfw') {
                    $moderationFlagged = true;
                }
            }
        };

        if ($type === 'post') {
            $bodyImagePaths = $contentImages->extractUploadedImagePathsFromHtml($bodyHtml);
            foreach ($bodyImagePaths as $bodyImagePath) {
                $scanResult = maybeFlagImageForModeration($bodyImagePath, $request->user(), 'editor');
                $captureModeration($scanResult, 'editor');
            }
        }

        $coverImages = [];
        $maxCoverImages = max(1, (int) config('waasabi.upload.max_images_per_post', 8));
        if ($type === 'post' && $request->hasFile('cover_images')) {
            $coverFiles = $request->file('cover_images') ?? [];
            if (!is_array($coverFiles)) {
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
                    return redirect()
                        ->back()
                        ->withErrors(['cover_images' => $exception->getMessage()])
                        ->withInput();
                }
                $coverImages[] = $result['path'];
                $scanResult = maybeFlagImageForModeration($result['path'], $request->user(), 'cover');
                $captureModeration($scanResult, 'cover');
            }
        }

        if ($moderationFlagged) {
            $nsfw = true;
        }

        $coverUrl = $editingPost?->cover_url;
        $albumUrls = $editingPost?->album_urls;
        if (is_string($albumUrls)) {
            $decoded = json_decode($albumUrls, true);
            $albumUrls = is_array($decoded) ? $decoded : preg_split('/\r\n|\n|\r/', $albumUrls);
        }
        $albumUrls = is_array($albumUrls) ? $albumUrls : [];
        if (!empty($coverImages)) {
            $coverUrl = $coverImages[0] ?? $coverUrl;
            $albumUrls = array_slice($coverImages, 1);
        }

        $coverUrl = $coverUrl !== '' ? $coverUrl : null;
        $albumUrls = array_values(array_filter($albumUrls));

        $post = $editingPost ?: new Post();
        $post->user_id = $request->user()->id;
        $post->type = $type;
        $post->title = $title;
        $post->subtitle = $subtitle;
        $post->slug = $slug;
        $post->body_markdown = $bodyMarkdown;
        $post->body_html = $bodyHtml;
        $post->read_time_minutes = $readMinutes;
        $post->status = $status;
        $post->nsfw = $nsfw;
        $post->tags = $tags;
        if (safeHasColumn('posts', 'cover_url')) {
            $post->cover_url = $coverUrl;
        }
        if (safeHasColumn('posts', 'album_urls')) {
            $post->album_urls = $albumUrls;
        }
        if (safeHasColumn('posts', 'coauthor_user_ids')) {
            $post->coauthor_user_ids = $coauthorIds;
        }

        if ($editingPost) {
            $post->edited_at = now();
            $post->edited_by = $request->user()->id;
        }

        $post->save();

        if (!empty($coauthorUsers)) {
            foreach ($coauthorUsers as $coauthorUser) {
                if (!$coauthorUser instanceof User) {
                    continue;
                }
                if ($editingPost && in_array($coauthorUser->id, $existingCoauthorIds, true)) {
                    continue;
                }
                $coauthorUser->sendNotification(
                    __('ui.notifications.title'),
                    __('ui.notifications.coauthor_tagged', [
                        'author' => $request->user()->name ?? __('ui.support.portal_you'),
                        'title' => $post->title,
                    ]),
                    $type === 'question'
                        ? route('questions.show', $post->slug)
                        : route('project', $post->slug),
                );
            }
        }

        if (safeHasColumn('posts', 'moderation_status')) {
            $moderationStatus = $moderationFlagged || $moderationFallback ? 'pending' : 'approved';
            if ($moderationStatus !== 'approved') {
                $post->moderation_status = $moderationStatus;
                $post->is_hidden = true;
                $post->hidden_at = now();
                $post->hidden_by = $request->user()->id;
                $post->save();
            }
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
                'Moderation',
                __('ui.moderation.text_queued_notification'),
                $contentUrl,
            );
            $toastMessage = __('ui.moderation.text_queued_toast');
        }

        $redirect = $type === 'question'
            ? redirect()->route('questions.show', $post->slug)
            : redirect()->route('project', $post->slug);

        return $toastMessage !== null
            ? $redirect->with('toast', $toastMessage)
            : $redirect;
    }
}
