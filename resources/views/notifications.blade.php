@extends('layouts.app')

@section('title', __('ui.notifications.title'))
@section('page', 'notifications')

@section('content')
    @php
        $hasPagedNotifications = isset($unread_notifications) || isset($read_notifications);
        if ($hasPagedNotifications) {
            $unreadNotifications = collect($unread_notifications ?? []);
            $readNotifications = collect($read_notifications ?? []);
            $unreadTotal = (int) ($unread_total ?? $unreadNotifications->count());
            $readTotal = (int) ($read_total ?? $readNotifications->count());
            $unreadPaginator = $unread_paginator ?? null;
            $readPaginator = $read_paginator ?? null;
        } else {
            $unreadNotifications = collect($notifications ?? [])->filter(static fn (array $item) => !($item['read'] ?? false));
            $readNotifications = collect($notifications ?? [])->filter(static fn (array $item) => ($item['read'] ?? false));
            $unreadTotal = $unreadNotifications->count();
            $readTotal = $readNotifications->count();
            $unreadPaginator = null;
            $readPaginator = null;
        }
        $activeTab = (string) request('tab', 'new');
        $tabOptions = ['new', 'read'];
        if (!in_array($activeTab, $tabOptions, true)) {
            $activeTab = 'new';
        }
    @endphp
    <section class="hero">
        <h1>{{ __('ui.notifications.title') }}</h1>
        <p>{{ __('ui.notifications.subtitle') }}</p>
    </section>

    <section
        class="section notifications-page"
        data-notifications-root
        data-notifications-total-new="{{ $unreadTotal }}"
        data-notifications-total-read="{{ $readTotal }}"
    >
        <div class="notifications-toolbar">
            <div class="tabs" data-tabs>
                <button class="tab {{ $activeTab === 'new' ? 'is-active' : '' }}" type="button" data-tab="new">
                    {{ __('ui.notifications.tab_new') }}
                    <span class="chip chip--count" data-notifications-count="new">{{ $unreadTotal }}</span>
                </button>
                <button class="tab {{ $activeTab === 'read' ? 'is-active' : '' }}" type="button" data-tab="read">
                    {{ __('ui.notifications.tab_read') }}
                    <span class="chip chip--count" data-notifications-count="read">{{ $readTotal }}</span>
                </button>
            </div>
        </div>

        <div class="tab-panels">
            <div class="tab-panel {{ $activeTab === 'new' ? 'is-active' : '' }}" data-tab-panel="new">
                <div class="list" data-notifications-list="new">
                    @foreach ($unreadNotifications as $notification)
                        @php
                            $notificationId = $notification['id'] ?? null;
                            $notificationLink = $notification['link'] ?? null;
                            $isRead = $notification['read'] ?? false;
                        @endphp
                        <div class="list-item notification-item" data-notification-item @if ($notificationId) data-notification-id="{{ $notificationId }}" @endif data-notification-read="{{ $isRead ? '1' : '0' }}">
                            <div class="meta">
                                <span class="chip">{{ $notification['type'] }}</span>
                                <span>{{ $notification['time'] }}</span>
                            </div>
                            <p class="context">{{ $notification['text'] }}</p>
                            <div class="notification-actions">
                                @if ($notificationLink)
                                    <a class="ghost-btn ghost-btn--compact" href="{{ $notificationLink }}">{{ __('ui.notifications.open') }}</a>
                                @endif
                                @if ($notificationId)
                                    <button type="button" class="ghost-btn ghost-btn--compact" data-notification-read>{{ __('ui.notifications.mark_read') }}</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="list-item notification-empty" data-notifications-empty="new" @if ($unreadNotifications->isNotEmpty()) hidden @endif>
                        {{ __('ui.notifications.empty_new') }}
                    </div>
                </div>
                @if ($unreadPaginator && method_exists($unreadPaginator, 'links') && $unreadPaginator->hasPages())
                    <div class="notifications-pagination">
                        {{ $unreadPaginator->appends(['tab' => 'new'])->links() }}
                    </div>
                @endif
            </div>

            <div class="tab-panel {{ $activeTab === 'read' ? 'is-active' : '' }}" data-tab-panel="read">
                <div class="list" data-notifications-list="read">
                    @foreach ($readNotifications as $notification)
                        @php
                            $notificationId = $notification['id'] ?? null;
                            $notificationLink = $notification['link'] ?? null;
                            $isRead = $notification['read'] ?? false;
                        @endphp
                        <div class="list-item notification-item" data-notification-item @if ($notificationId) data-notification-id="{{ $notificationId }}" @endif data-notification-read="{{ $isRead ? '1' : '0' }}">
                            <div class="meta">
                                <span class="chip">{{ $notification['type'] }}</span>
                                <span>{{ $notification['time'] }}</span>
                            </div>
                            <p class="context">{{ $notification['text'] }}</p>
                            @if ($notificationLink)
                                <div class="notification-actions">
                                    <a class="ghost-btn ghost-btn--compact" href="{{ $notificationLink }}">{{ __('ui.notifications.open') }}</a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <div class="list-item notification-empty" data-notifications-empty="read" @if ($readNotifications->isNotEmpty()) hidden @endif>
                        {{ __('ui.notifications.empty_read') }}
                    </div>
                </div>
                @if ($readPaginator && method_exists($readPaginator, 'links') && $readPaginator->hasPages())
                    <div class="notifications-pagination">
                        {{ $readPaginator->appends(['tab' => 'read'])->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
