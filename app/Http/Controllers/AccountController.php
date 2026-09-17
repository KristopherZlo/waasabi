<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AutoModerationService;
use App\Services\UploadAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        Auth::logoutOtherDevices($data['current_password']);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('toast', __('ui.profile_settings.password_updated'));
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user()->load([
            'posts.attachments',
            'posts.updates',
            'postComments',
            'postReviews',
            'collaborationRequests',
            'collaborationApplications',
            'projectMemberships',
            'badges',
            'notifications',
            'supportTickets',
        ]);
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'profile' => $user->only(['id', 'name', 'slug', 'email', 'bio', 'role', 'created_at', 'legal_version', 'legal_accepted_at', 'skills', 'open_to_help', 'portfolio_url', 'featured_post_id', 'profile_readme', 'wall_mode']),
            'showcase_project_ids' => $user->showcaseProjects()->pluck('posts.id'),
            'wall_posts' => DB::table('profile_wall_posts')->where(fn ($query) => $query->where('profile_user_id', $user->id)->orWhere('user_id', $user->id))->get(),
            'followed_projects' => DB::table('project_follows')->where('user_id', $user->id)->pluck('post_id'),
            'projects' => $user->posts,
            'comments' => $user->postComments,
            'reviews' => $user->postReviews,
            'collaboration_requests' => $user->collaborationRequests,
            'collaboration_applications' => $user->collaborationApplications,
            'project_memberships' => $user->projectMemberships,
            'badges' => $user->badges,
            'notifications' => $user->notifications,
            'support_tickets' => $user->supportTickets,
        ];

        return response()->streamDownload(
            static fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'waasabi-account-'.$user->id.'.json',
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public function destroy(Request $request, UploadAssetService $assets, AutoModerationService $reports): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();
        if (
            $user->isAdmin()
            && ! $user->is_banned
            && User::where('role', 'admin')->where('is_banned', false)->count() <= 1
        ) {
            return back()->withErrors(['password' => __('ui.profile_settings.last_admin')]);
        }

        $reports->withdrawReportsForUser($user);
        foreach ($user->posts()->with('attachments')->get() as $post) {
            $assets->deletePostMedia($post);
        }
        $assets->deleteUserUploads($user);
        $this->deleteProfileMedia((string) $user->avatar);
        $this->deleteProfileMedia((string) $user->banner_url);

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('feed')->with('toast', __('ui.profile_settings.account_deleted'));
    }

    private function deleteProfileMedia(string $path): void
    {
        $path = ltrim((string) (parse_url($path, PHP_URL_PATH) ?: $path), '/');
        $relative = ltrim(Str::after($path, 'storage/'), '/');
        if (
            str_starts_with($path, 'storage/uploads/')
            && ! str_contains($relative, '..')
            && ! str_contains($relative, '\\')
            && preg_match('/\Auploads\/(?:avatars|banners)\/[A-Za-z0-9_.\/-]+\z/', $relative)
        ) {
            Storage::disk('public')->delete($relative);
        }
    }
}
