@php
    $topbarUser = Auth::user();
    $topbarAvatar = $topbarUser?->avatar ?: '/images/avatar-default.svg';
    $topbarAvatar = \Illuminate\Support\Str::startsWith($topbarAvatar, ['http://', 'https://', '/']) ? $topbarAvatar : asset($topbarAvatar);
@endphp
<header class="topbar static-topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('feed') }}"><img src="{{ asset('images/logo-black.svg') }}" alt=""><span>waasabi</span></a>
        <form class="top-search static-search" method="GET" action="{{ route('feed') }}" role="search">
            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input name="q" placeholder="{{ __('studio.search') }}" aria-label="{{ __('studio.search') }}" data-static-search>
            <kbd>Ctrl K</kbd>
        </form>
        <div class="top-actions">
            <button class="icon-button" type="button" data-static-theme aria-label="{{ __('studio.theme') }}"><svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
            @auth
                <a class="icon-button" href="{{ route('notifications') }}" aria-label="{{ __('studio.notifications') }}"><svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>
                <a class="icon-button" href="{{ route('read-later') }}" aria-label="{{ __('studio.saved') }}"><svg viewBox="0 0 24 24" width="19" height="19" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></a>
                <a class="avatar-button" href="{{ route('profile.show', $topbarUser->slug) }}" aria-label="{{ __('studio.profile') }}"><img class="avatar" src="{{ $topbarAvatar }}" alt=""></a>
            @else
                <a class="button small" href="{{ route('login') }}">{{ __('studio.login') }}</a>
            @endauth
        </div>
    </div>
</header>
