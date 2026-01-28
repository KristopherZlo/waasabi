<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\NotificationService;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $unreadNotifications = collect();
        $readNotifications = collect();
        $unreadTotal = 0;
        $readTotal = 0;
        $unreadPaginator = null;
        $readPaginator = null;

        if (safeHasTable('user_notifications')) {
            $perPage = 20;
            $cutoff = now()->subDays(30);
            $baseQuery = $user->notifications()
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at');

            $unreadQuery = (clone $baseQuery)->whereNull('read_at');
            $readQuery = (clone $baseQuery)->whereNotNull('read_at');

            $unreadTotal = (clone $unreadQuery)->count();
            $readTotal = (clone $readQuery)->count();

            $unreadPaginator = $unreadQuery->paginate($perPage, ['*'], 'new_page');
            $readPaginator = $readQuery->paginate($perPage, ['*'], 'read_page');

            $mapNotifications = static function ($collection) {
                return $collection
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
                    ->values();
            };

            $unreadNotifications = $mapNotifications($unreadPaginator->getCollection());
            $readNotifications = $mapNotifications($readPaginator->getCollection());
        } else {
            $payload = app(NotificationService::class)->buildPayload((array) config('notifications.seed', []));
            $unreadNotifications = collect($payload['unreadNotifications']);
            $readNotifications = collect($payload['notifications'])
                ->filter(static fn (array $item) => ($item['read'] ?? false))
                ->values();
            $unreadTotal = $unreadNotifications->count();
            $readTotal = $readNotifications->count();
        }

        return view('notifications', [
            'unread_notifications' => $unreadNotifications,
            'read_notifications' => $readNotifications,
            'unread_total' => $unreadTotal,
            'read_total' => $readTotal,
            'unread_paginator' => $unreadPaginator,
            'read_paginator' => $readPaginator,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    public function markRead(Request $request, int $notification)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!safeHasTable('user_notifications')) {
            abort(503);
        }

        $updated = $user->notifications()
            ->where('id', $notification)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => $updated > 0]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!safeHasTable('user_notifications')) {
            abort(503);
        }

        $updated = $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'updated' => $updated]);
    }
}
