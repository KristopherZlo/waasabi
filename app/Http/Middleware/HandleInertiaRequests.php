<?php

namespace App\Http\Middleware;

use App\Models\ContentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'studio';

    public function share(Request $request): array
    {
        $user = $request->user();
        $quick = ['notifications' => [], 'saved' => [], 'unread' => 0];
        if ($user) {
            $quick['notifications'] = $user->notifications()->latest()->take(5)->get()
                ->map(fn ($notification) => $notification->only('id', 'type', 'text', 'link') + [
                    'unread' => $notification->read_at === null,
                    'date' => $notification->created_at?->toISOString(),
                ])->values();
            $quick['unread'] = $user->notifications()->whereNull('read_at')->count();
            $quick['saved'] = $user->savedPosts()->where('posts.is_hidden', false)->where('posts.moderation_status', 'approved')
                ->whereHas('user', fn ($query) => $query->where('is_banned', false))->orderByPivot('created_at', 'desc')->take(5)
                ->get(['posts.id', 'posts.slug', 'posts.title', 'posts.type'])
                ->map(fn ($post) => ['id' => $post->id, 'title' => $post->title,
                    'url' => $post->type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug)])->values();
        }

        $moderation = null;
        if ($user?->can('moderate')) {
            $queueQuery = ContentReport::query()
                ->whereIn('resolved_status', ['pending', 'auto_hidden'])
                ->whereNotNull('content_id')
                ->where('content_id', '<>', '')
                ->select('content_type', 'content_id')
                ->groupBy('content_type', 'content_id');
            $queue = DB::query()->fromSub($queueQuery, 'moderation_queue')->count();
            $moderation = [
                'queue' => $queue,
                'threshold' => (float) config('moderation.reports.auto_hide.base_threshold', 16),
                'minimum_reports' => (int) config('moderation.reports.auto_hide.minimum_reports', 3),
            ];
        }

        return array_merge(parent::share($request), [
            'auth' => ['user' => $user ? $user->only('id', 'name', 'slug', 'avatar', 'email_verified_at') + [
                'verified' => (bool) $user->is_profile_verified,
                'moderator' => $user->can('moderate'), 'admin' => $user->can('admin'), 'banned' => $user->is_banned,
            ] : null],
            'copy' => fn () => trans('studio', [], 'en'),
            'quick' => $quick,
            'moderation' => $moderation,
            'csrf' => fn () => csrf_token(),
            'flash' => fn () => ['message' => session('toast') ?? session('status'), 'clearDraft' => session('clear_publish_draft')],
        ]);
    }
}
