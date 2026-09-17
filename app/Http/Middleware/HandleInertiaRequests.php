<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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

        return array_merge(parent::share($request), [
            'auth' => ['user' => $user ? $user->only('id', 'name', 'slug', 'avatar', 'email_verified_at') + [
                'moderator' => $user->can('moderate'), 'banned' => $user->is_banned,
            ] : null],
            'copy' => fn () => trans('studio', [], 'en'),
            'quick' => $quick,
            'csrf' => fn () => csrf_token(),
            'flash' => fn () => ['message' => session('toast') ?? session('status'), 'clearDraft' => session('clear_publish_draft')],
        ]);
    }
}
