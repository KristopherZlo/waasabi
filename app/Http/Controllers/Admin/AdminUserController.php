<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminBanRequest;
use App\Http\Requests\AdminRoleRequest;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;

class AdminUserController extends Controller
{
    public function updateRole(AdminRoleRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (
            $user->isAdmin()
            && ! $user->is_banned
            && $data['role'] !== 'admin'
            && User::where('role', 'admin')->where('is_banned', false)->count() <= 1
        ) {
            return back()->withErrors(['role' => __('ui.profile_settings.last_admin')]);
        }

        $oldRole = $user->role;
        $user->update(['role' => $data['role']]);

        logAuditEvent($request, 'admin.user.role_change', $request->user(), [
            'from' => $oldRole,
            'to' => $data['role'],
        ], 'user', (string) $user->id);

        return redirect()->route('admin', ['tab' => 'users', 'user' => $user->id]);
    }

    public function toggleBan(AdminBanRequest $request, User $user, ModerationService $moderation): RedirectResponse
    {
        $moderator = $request->user();
        if (! $moderator) {
            return redirect()->route('login');
        }

        $data = $request->validated();
        $reason = trim((string) $data['reason']);

        if ($moderator->id === $user->id) {
            return redirect()->back();
        }
        if ($moderation->shouldBlock($moderator, $user)) {
            abort(403);
        }

        $wasBanned = (bool) $user->is_banned;
        $user->update(['is_banned' => ! $wasBanned]);
        $nowBanned = (bool) $user->is_banned;
        $action = $nowBanned ? 'ban' : 'unban';
        $contentUrl = ! empty($user->slug) ? route('profile.show', $user->slug) : null;

        $moderation->logAction(
            $request,
            $moderator,
            $action,
            'user',
            (string) $user->id,
            $contentUrl,
            $reason,
            [
                'name' => $user->name,
                'author_name' => $user->name,
                'slug' => $user->slug,
                'was_banned' => $wasBanned,
                'is_banned' => $nowBanned,
                'role' => $user->role,
            ],
        );

        return redirect()->back();
    }
}
