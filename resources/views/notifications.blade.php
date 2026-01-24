@extends('layouts.app')

@section('title', __('ui.notifications.title'))
@section('page', 'notifications')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.notifications.title') }}</h1>
        <p>{{ __('ui.notifications.subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="list">
            @foreach ($notifications as $notification)
                @php
                    $notificationId = $notification['id'] ?? null;
                    $isRead = $notification['read'] ?? false;
                @endphp
                <div class="list-item" data-notification-item @if ($notificationId) data-notification-id="{{ $notificationId }}" @endif data-notification-read="{{ $isRead ? '1' : '0' }}">
                    <div class="meta">
                        <span class="chip">{{ $notification['type'] }}</span>
                        <span>{{ $notification['time'] }}</span>
                    </div>
                    <p class="context">{{ $notification['text'] }}</p>
                    @if (!$isRead && $notificationId)
                        <button type="button" class="ghost-btn ghost-btn--compact" data-notification-read>{{ __('ui.notifications.mark_read') }}</button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endsection
