@php
    $topProjects = $top_projects ?? [];
    $readingNow = $reading_now ?? [];
    $subscriptionsList = $subscriptions ?? [];
@endphp

<div class="sidebar-card">
    <div class="sidebar-title">{{ __('ui.sidebar.top_projects') }}</div>
    <div class="sidebar-list">
        @forelse ($topProjects as $project)
            <a class="sidebar-item" href="{{ route('project', $project['slug']) }}">
                <span>{{ $project['title'] }}</span>
                <span>+{{ $project['score'] }}</span>
            </a>
        @empty
            <div class="sidebar-item muted">{{ __('ui.sidebar.top_projects_empty') }}</div>
        @endforelse
    </div>
</div>

<div class="sidebar-card">
    <div class="sidebar-title">{{ __('ui.sidebar.reading_now') }}</div>
    <div class="sidebar-list">
        @forelse ($readingNow as $entry)
            <a class="sidebar-item" href="{{ route('project', $entry['slug']) }}">
                <span>{{ $entry['title'] }}</span>
                <span>{{ __('ui.sidebar.readers_count', ['count' => $entry['readers'] ?? 0]) }}</span>
            </a>
        @empty
            <div class="sidebar-item muted">{{ __('ui.sidebar.reading_empty') }}</div>
        @endforelse
    </div>
</div>

<div class="sidebar-card">
    <div class="sidebar-title">{{ __('ui.sidebar.subscriptions') }}</div>
    <div class="sidebar-list">
        @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
            @forelse ($subscriptionsList as $person)
                <a class="sidebar-item" href="{{ route('profile.show', $person['slug']) }}">
                    <span>{{ '@' . $person['slug'] }}</span>
                    <span>{{ $person['count'] }}</span>
                </a>
            @empty
                <div class="sidebar-item muted">{{ __('ui.sidebar.subscriptions_empty') }}</div>
            @endforelse
        @else
            <a class="sidebar-item" href="{{ route('login') }}">
                <span>{{ __('ui.sidebar.subscriptions_login') }}</span>
            </a>
        @endif
    </div>
</div>
