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
        $oldRole = $user->role;
        $user->update(['role' => $data['role']]);

        logAuditEvent($request, 'admin.user.role_change', $request->user(), [
            'from' => $oldRole,
            'to' => $data['role'],
        ], 'user', (string) $user->id);

        return redirect()->route('admin');
    }

    public function toggleBan(AdminBanRequest $request, User $user, ModerationService $moderation): RedirectResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
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

        $wasBanned = (bool) ($user->is_banned ?? false);
        if (safeHasColumn('users', 'is_banned')) {
            $user->is_banned = !$user->is_banned;
            $user->role = $user->is_banned ? 'BANNED' : 'user';
            $user->save();
        }
        $nowBanned = (bool) ($user->is_banned ?? false);
        $action = $nowBanned ? 'ban' : 'unban';
        $contentUrl = !empty($user->slug) ? route('profile.show', $user->slug) : null;

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
