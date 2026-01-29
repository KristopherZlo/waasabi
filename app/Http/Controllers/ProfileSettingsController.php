<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileSettingsRequest;
use App\Services\UserPayloadService;
use App\Services\UserSlugService;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProfileSettingsController extends Controller
{
    public function edit(): \Illuminate\View\View
    {
        return view('profile-settings', [
            'current_user' => app(UserPayloadService::class)->currentUserPayload(),
            'user' => Auth::user(),
        ]);
    }

    public function update(ProfileSettingsRequest $request, UserSlugService $slugService, ImageUploadService $uploadService): RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $section = $this->normalizeSection($request->input('section', 'profile'));
        $data = $request->validated();

        $user->name = $data['name'];
        $avatarUrl = $data['avatar'] ?? null;

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
                maybeFlagImageForModeration($result['path'], $user, 'avatar');
            } catch (RuntimeException $exception) {
                return redirect()
                    ->route('profile.settings', ['section' => $section])
                    ->withErrors(['avatar_file' => $exception->getMessage()])
                    ->withInput();
            }
        } elseif (!empty($avatarUrl)) {
            $user->avatar = $avatarUrl;
        }

        $user->bio = $data['bio'] ?? null;
        if ($request->user()?->isAdmin() && isset($data['role']) && !$user->isAdmin()) {
            // Prevent admin self-demotion from the profile settings screen.
            $user->role = $data['role'];
        }

        $booleanFields = [
            'privacy_share_activity',
            'privacy_allow_mentions',
            'privacy_personalized_recommendations',
            'notify_comments',
            'notify_reviews',
            'notify_follows',
            'connections_allow_follow',
            'connections_show_follow_counts',
            'security_login_alerts',
        ];
        foreach ($booleanFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (safeHasColumn('users', $field)) {
                $user->{$field} = (bool) $data[$field];
            }
        }

        if (safeHasColumn('users', 'slug') && empty($user->slug)) {
            $user->slug = $slugService->generate($user->name);
        }
        $user->save();

        $toastMessage = $section === 'profile'
            ? __('ui.js.profile_saved')
            : __('ui.js.settings_saved');

        return redirect()
            ->route('profile.settings', ['section' => $section])
            ->with('toast', $toastMessage);
    }

    public function updateBanner(Request $request, string $slug, ImageUploadService $uploadService): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        if (!safeHasColumn('users', 'banner_url')) {
            return response()->json(['message' => 'Banner storage unavailable.'], 503);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $request->validate([
            'banner_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $file = $request->file('banner_file');
        if (!$file instanceof UploadedFile) {
            return response()->json(['message' => 'Invalid upload.'], 422);
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

        $user->banner_url = $result['path'];
        $user->save();
        maybeFlagImageForModeration($result['path'], $user, 'banner');

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
        if (!$user) {
            abort(401);
        }
        if (!safeHasColumn('users', 'banner_url')) {
            return response()->json(['message' => 'Banner storage unavailable.'], 503);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $previous = trim((string) ($user->banner_url ?? ''));
        $user->banner_url = null;
        $user->save();

        if ($previous !== '' && isUserUploadedMediaPath($previous)) {
            try {
                $relative = Str::after(ltrim($previous, '/'), 'storage/');
                Storage::disk('public')->delete($relative);
            } catch (\Throwable $exception) {
                Log::warning('Unable to delete banner file.', ['path' => $previous, 'error' => $exception->getMessage()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function updateAvatar(Request $request, string $slug, ImageUploadService $uploadService): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $request->validate([
            'avatar_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $file = $request->file('avatar_file');
        if (!$file instanceof UploadedFile) {
            return response()->json(['message' => 'Invalid upload.'], 422);
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

        $user->avatar = $result['path'];
        $user->save();
        maybeFlagImageForModeration($result['path'], $user, 'avatar');

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
        if (!$user) {
            abort(401);
        }
        if (($user->slug ?? '') !== $slug) {
            abort(403);
        }

        $previous = trim((string) ($user->avatar ?? ''));
        $user->avatar = null;
        $user->save();

        if ($previous !== '' && isUserUploadedMediaPath($previous)) {
            try {
                $relative = Str::after(ltrim($previous, '/'), 'storage/');
                Storage::disk('public')->delete($relative);
            } catch (\Throwable $exception) {
                Log::warning('Unable to delete avatar file.', ['path' => $previous, 'error' => $exception->getMessage()]);
            }
        }

        return response()->json([
            'ok' => true,
            'default_url' => asset('images/avatar-default.svg'),
        ]);
    }

    private function normalizeSection(string $section): string
    {
        $allowed = ['profile', 'privacy', 'notifications', 'connections', 'devices'];
        if (!in_array($section, $allowed, true)) {
            return 'profile';
        }

        return $section;
    }
}
