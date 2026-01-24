@php
    $navUser = Auth::user();
    $profileSlug = $navUser?->slug ?? ($navUser?->name ? \Illuminate\Support\Str::slug($navUser->name) : '');
    $profileRoute = $profileSlug ? route('profile.show', $profileSlug) : route('profile');
@endphp

@if (Auth::check() && !(Auth::user()?->is_banned ?? false))
    <nav class="nav">
        <a href="{{ route('feed') }}" class="{{ request()->routeIs('feed') ? 'is-active' : '' }}"><i data-lucide="home" class="icon"></i><span>{{ __('ui.nav.feed') }}</span></a>
        <a href="{{ route('read-later') }}" class="{{ request()->routeIs('read-later') ? 'is-active' : '' }}"><i data-lucide="bookmark" class="icon"></i><span>{{ __('ui.nav.read_later') }}</span></a>
        <a href="{{ route('publish') }}" class="{{ request()->routeIs('publish') ? 'is-active' : '' }}"><i data-lucide="plus-circle" class="icon"></i><span>{{ __('ui.nav.publish') }}</span></a>
        <a href="{{ route('showcase') }}" class="{{ request()->routeIs('showcase') ? 'is-active' : '' }}"><i data-lucide="layers" class="icon"></i><span>{{ __('ui.nav.showcase') }}</span></a>
        <a href="{{ route('notifications') }}" class="{{ request()->routeIs('notifications') ? 'is-active' : '' }}"><i data-lucide="bell" class="icon"></i><span>{{ __('ui.nav.notifications') }}</span></a>
        <a href="{{ route('support') }}" class="{{ request()->routeIs('support*') ? 'is-active' : '' }}"><i data-lucide="life-buoy" class="icon"></i><span>{{ __('ui.nav.support') }}</span></a>
        <a href="{{ $profileRoute }}" class="{{ request()->routeIs('profile*') ? 'is-active' : '' }}"><i data-lucide="user" class="icon"></i><span>{{ __('ui.nav.profile') }}</span></a>
    </nav>
@else
    <nav class="nav">
        <a href="{{ route('feed') }}" class="{{ request()->routeIs('feed') ? 'is-active' : '' }}"><i data-lucide="home" class="icon"></i><span>{{ __('ui.nav.feed') }}</span></a>
        <a href="{{ route('showcase') }}" class="{{ request()->routeIs('showcase') ? 'is-active' : '' }}"><i data-lucide="layers" class="icon"></i><span>{{ __('ui.nav.showcase') }}</span></a>
        <a href="{{ route('support') }}" class="{{ request()->routeIs('support*') ? 'is-active' : '' }}"><i data-lucide="life-buoy" class="icon"></i><span>{{ __('ui.nav.support') }}</span></a>
        <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'is-active' : '' }}"><i data-lucide="log-in" class="icon"></i><span>{{ __('ui.nav.login') }}</span></a>
        <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'is-active' : '' }}"><i data-lucide="user-plus" class="icon"></i><span>{{ __('ui.nav.register') }}</span></a>
    </nav>
@endif
