<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileSettingsRequest;
use App\Services\ContentModerationService;
use App\Services\ImageUploadService;
use App\Services\UserPayloadService;
use App\Services\UserSlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ProfileSettingsController extends Controller
{
    public function page(): View
    {
        return view('settings', [
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    public function edit(): View
    {
        return view('profile-settings', [
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
            'user' => Auth::user(),
        ]);
    }

    public function update(ProfileSettingsRequest $request, UserSlugService $slugService, ImageUploadService $uploadService, ContentModerationService $moderation): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $section = $this->normalizeSection($request->input('section', 'profile'));
        $data = $request->validated();
        $previousAvatar = (string) ($user->avatar ?? '');

        $user->name = $data['name'];
        if ($request->hasFile('avatar_file')) {
            try {
                $result = $uploadService->process($request->file('avatar_file'), [
                    'dir' => 'uploads/avatars',
                    'max_side' => 1024,
                    'max_side_input' => 1024,
                    'min_side' => 512,
                    'format' => 'webp',
                ]);
                $user->avatar = $result['path'];
                $moderation->moderateUploadedImage($result['path'], $user, 'avatar');
            } catch (RuntimeException $exception) {
                return redirect(route('profile.settings').'#'.$section)
                    ->withErrors(['avatar_file' => $exception->getMessage()])
                    ->withInput();
            }
        }

        foreach (['bio', 'skills', 'open_to_help', 'portfolio_url', 'featured_post_id', 'profile_readme', 'wall_mode'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        $booleanFields = [
            'privacy_allow_mentions',
            'notify_comments',
            'notify_reviews',
            'notify_follows',
            'connections_allow_follow',
            'connections_show_follow_counts',
            'security_login_alerts',
        ];
        foreach ($booleanFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $user->{$field} = (bool) $data[$field];
        }

        if (empty($user->slug)) {
            $user->slug = $slugService->generate($user->name);
        }
        $user->save();
        if ($request->boolean('showcase_project_ids_present')) {
            $user->showcaseProjects()->sync(collect($data['showcase_project_ids'] ?? [])->values()->mapWithKeys(fn ($id, $position) => [(int) $id => ['position' => $position]])->all());
        }
        if ($previousAvatar !== (string) ($user->avatar ?? '')) {
            $this->deleteUploadedMedia($previousAvatar);
        }

        $toastMessage = $section === 'profile'
            ? __('ui.js.profile_saved')
            : __('ui.js.settings_saved');

        $route = $request->boolean('return_to_profile')
            ? route('profile.show', $user->slug)
            : route('profile.settings').'#'.$section;

        return redirect($route)->with('toast', $toastMessage);
    }

    public function updateBanner(Request $request, string $slug, ImageUploadService $uploadService, ContentModerationService $moderation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $request->validate([
            'banner_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $file = $request->file('banner_file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => __('ui.errors.invalid_upload')], 422);
        }

        try {
            $result = $uploadService->process($file, [
                'dir' => 'uploads/banners',
                'max_side_input' => 4096,
                'max_pixels' => 16000000,
                'min_width' => 1200,
                'min_height' => 300,
                'crop_aspect' => 4,
                'target_width' => 1600,
                'target_height' => 400,
                'format' => 'webp',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $previous = (string) ($user->banner_url ?? '');
        $user->banner_url = $result['path'];
        $user->save();
        $this->deleteUploadedMedia($previous);
        $moderation->moderateUploadedImage($result['path'], $user, 'banner');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => asset($result['path']),
            ]);
        }

        return redirect()
            ->route('profile.show', $slug)
            ->with('toast', __('ui.js.profile_banner_updated'));
    }

    public function deleteBanner(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $previous = trim((string) ($user->banner_url ?? ''));
        $user->banner_url = null;
        $user->save();

        $this->deleteUploadedMedia($previous);

        return response()->json(['ok' => true]);
    }

    public function updateAvatar(Request $request, string $slug, ImageUploadService $uploadService, ContentModerationService $moderation): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $request->validate([
            'avatar_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $file = $request->file('avatar_file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => __('ui.errors.invalid_upload')], 422);
        }

        try {
            $result = $uploadService->process($file, [
                'dir' => 'uploads/avatars',
                'max_side_input' => 4096,
                'max_pixels' => 16000000,
                'min_width' => 256,
                'min_height' => 256,
                'crop_aspect' => 1,
                'target_width' => 512,
                'target_height' => 512,
                'format' => 'webp',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $previous = (string) ($user->avatar ?? '');
        $user->avatar = $result['path'];
        $user->save();
        $this->deleteUploadedMedia($previous);
        $moderation->moderateUploadedImage($result['path'], $user, 'avatar');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => asset($result['path']),
            ]);
        }

        return redirect()
            ->route('profile.show', $slug)
            ->with('toast', __('ui.js.profile_avatar_updated'));
    }

    public function deleteAvatar(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $previous = trim((string) ($user->avatar ?? ''));
        $user->avatar = null;
        $user->save();

        $this->deleteUploadedMedia($previous);

        return response()->json([
            'ok' => true,
            'default_url' => asset('images/avatar-default.svg'),
        ]);
    }

    private function normalizeSection(string $section): string
    {
        $section = match ($section) {
            'connections' => 'privacy',
            'devices' => 'security',
            default => $section,
        };
        $allowed = ['profile', 'privacy', 'notifications', 'security', 'data'];
        if (! in_array($section, $allowed, true)) {
            return 'profile';
        }

        return $section;
    }

    private function deleteUploadedMedia(string $path): void
    {
        $path = ltrim((string) (parse_url($path, PHP_URL_PATH) ?: $path), '/');
        $relative = ltrim(Str::after($path, 'storage/'), '/');
        if (
            ! str_starts_with($path, 'storage/uploads/')
            || str_contains($relative, '..')
            || str_contains($relative, '\\')
            || ! preg_match('/\Auploads\/(?:avatars|banners)\/[A-Za-z0-9_.\/-]+\z/', $relative)
        ) {
            return;
        }

        try {
            Storage::disk('public')->delete($relative);
        } catch (\Throwable $exception) {
            Log::warning('Unable to delete profile media.', ['path' => $path, 'error' => $exception->getMessage()]);
        }
    }
}
