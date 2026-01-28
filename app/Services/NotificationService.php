<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function buildPayload(array $seedNotifications, ?User $user = null): array
    {
        $authUser = $user ?? Auth::user();
        $notifications = $seedNotifications;

        if ($authUser && safeHasTable('user_notifications')) {
            $cutoff = now()->subDays(30);
            DB::table('user_notifications')
                ->where('created_at', '<', $cutoff)
                ->delete();

            $userNotifications = $authUser->notifications()
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at')
                ->limit(200)
                ->get();

            if ($userNotifications->isNotEmpty()) {
                $notifications = $userNotifications
                    ->map(static function ($notification) {
                        $createdAt = $notification->created_at ?? null;
                        $time = $createdAt ? Carbon::parse($createdAt)->diffForHumans() : '';
                        return [
                            'id' => $notification->id,
                            'type' => $notification->type ?? 'Update',
                            'time' => $time,
                            'text' => $notification->text ?? '',
                            'link' => $notification->link ?? null,
                            'read' => !empty($notification->read_at),
                        ];
                    })
                    ->values()
                    ->all();
            }
        }

        $notifications = $authUser ? $notifications : [];

        $unreadNotifications = array_values(array_filter($notifications, function (array $notification) {
            return !($notification['read'] ?? false);
        }));
        $unreadCount = count($unreadNotifications);
        $unreadPreview = array_slice($unreadNotifications, 0, 4);

        return [
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
            'unreadCount' => $unreadCount,
            'unreadPreview' => $unreadPreview,
        ];
    }
}
