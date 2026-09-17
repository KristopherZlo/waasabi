<div class="collaboration-page">
    <header class="collaboration-heading">
        <div>
            <h1>{{ __('ui.collaboration.openings') }}</h1>
            <p>{{ trans_choice('ui.collaboration.results_count', $collaboration_requests->total(), ['count' => $collaboration_requests->total()]) }}</p>
        </div>
        @can('publish')
            <a class="cta-btn" href="{{ route('collaboration.create') }}">
                <i data-lucide="plus" class="icon"></i>
                <span>{{ __('ui.collaboration.cta') }}</span>
            </a>
        @else
            @guest
                <a class="cta-btn" href="{{ route('login') }}">{{ __('ui.collaboration.cta') }}</a>
            @endguest
        @endcan
    </header>

    <form class="collaboration-filters" method="GET" action="{{ route('feed') }}">
        <input type="hidden" name="stream" value="collaboration">
        <label class="collaboration-filters__search">
            <span class="label-text">{{ __('ui.collaboration.filter_search') }}</span>
            <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.collaboration.filter_search_placeholder') }}">
        </label>
        <label>
            <span class="label-text">{{ __('ui.collaboration.filter_role') }}</span>
            <select class="input" name="role">
                <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                @foreach ($collaboration_roles as $roleKey => $roleLabel)
                    <option value="{{ $roleKey }}" @selected(request('role') === $roleKey)>{{ $roleLabel }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="label-text">{{ __('ui.collaboration.filter_availability') }}</span>
            <select class="input" name="availability">
                <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                @foreach ($collaboration_availability as $key => $label)
                    <option value="{{ $key }}" @selected(request('availability') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="label-text">{{ __('ui.collaboration.filter_format') }}</span>
            <select class="input" name="format">
                <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                @foreach ($collaboration_formats as $key => $label)
                    <option value="{{ $key }}" @selected(request('format') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="label-text">{{ __('ui.collaboration.filter_status') }}</span>
            <select class="input" name="status">
                @foreach (['open', 'filled', 'closed', 'all'] as $status)
                    <option value="{{ $status }}" @selected(request('status', 'open') === $status)>{{ __('ui.collaboration.status_'.$status) }}</option>
                @endforeach
            </select>
        </label>
        <div class="collaboration-filters__actions">
            @if (request()->hasAny(['q', 'role', 'availability', 'format']) || request('status', 'open') !== 'open')
                <a href="{{ route('feed', ['stream' => 'collaboration']) }}">{{ __('ui.collaboration.filter_reset') }}</a>
            @endif
            <button class="ghost-btn" type="submit">{{ __('ui.collaboration.filter_submit') }}</button>
        </div>
    </form>

    <section class="collaboration-list" aria-live="polite">
        <div class="collaboration-list__items">
            @forelse ($collaboration_requests as $collaborationRequest)
                @php
                    $roleLabel = $collaboration_roles[$collaborationRequest->role] ?? $collaborationRequest->role;
                    $statusKey = $collaborationRequest->isOpen() ? 'open' : ($collaborationRequest->status === 'open' ? 'closed' : $collaborationRequest->status);
                @endphp
                <article class="collaboration-opening-card">
                    <div class="collaboration-opening-card__main">
                        <div class="collaboration-opening-card__title-row">
                            <h2><a href="{{ route('collaboration.show', $collaborationRequest) }}">{{ $collaborationRequest->title }}</a></h2>
                        </div>
                        <div class="collaboration-opening-card__project">
@if ($collaborationRequest->post)
                            <a href="{{ route('project', $collaborationRequest->post->slug) }}">{{ $collaborationRequest->post->title }}</a>
@endif
                            <span aria-hidden="true">&middot;</span>
                            <a href="{{ route('profile.show', $collaborationRequest->user->slug) }}">{{ $collaborationRequest->user->name }}</a>
                            <span class="collaboration-opening-card__divider" aria-hidden="true"></span>
                            <span>{{ __('ui.collaboration.posted_time', ['time' => $collaborationRequest->created_at->translatedFormat('j M Y')]) }}</span>
                        </div>
                        <div class="collaboration-opening-card__facts">
                            <div><i data-lucide="map-pin" class="icon"></i><span>{{ $collaboration_formats[$collaborationRequest->format] ?? $collaborationRequest->format }}</span></div>
                            @if ($collaborationRequest->expires_at)
                                <div><i data-lucide="calendar-days" class="icon"></i><span>{{ __('ui.collaboration.deadline_date', ['date' => $collaborationRequest->expires_at->translatedFormat('j M Y')]) }}</span></div>
                            @endif
                            <div class="collaboration-opening-card__tags">
                                <span>{{ $roleLabel }}</span>
                                <span>{{ $collaboration_availability[$collaborationRequest->availability] ?? $collaborationRequest->availability }}</span>
                                @if ($statusKey !== 'open')
                                    <span>{{ __('ui.collaboration.status_'.$statusKey) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="collaboration-list__empty">{{ __('ui.collaboration.empty') }}</div>
            @endforelse
        </div>

        {{ $collaboration_requests->links() }}
    </section>
</div>
