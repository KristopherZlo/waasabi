<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', __('ui.app.description'))">
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <script nonce="{{ $csp_nonce ?? '' }}">try { document.documentElement.dataset.theme = localStorage.getItem('waasabi:theme') || 'dark'; } catch { document.documentElement.dataset.theme = 'dark'; }</script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/js/studio/static.css')
    @endif
</head>
<body class="static-shell" data-page="@yield('page', 'static')">
    <a class="skip" href="#main-content">{{ __('ui.app.skip_to_content') }}</a>
    <header class="topbar static-topbar">
        <div class="topbar-inner static-topbar-inner">
            <a class="brand" href="{{ route('feed') }}"><img src="{{ asset('images/logo-black.svg') }}" alt=""><span>Waasabi</span></a>
            @yield('context-nav')
            <nav class="static-nav" aria-label="Primary">
                <a href="{{ route('feed') }}">{{ __('ui.nav.feed') }}</a>
                <a href="{{ route('collaboration') }}">{{ __('ui.feed.tab_collaboration') }}</a>
                <a href="{{ route('support') }}">{{ __('ui.support.title') }}</a>
                @auth
                    <a href="{{ route('profile.show', Auth::user()->slug) }}">{{ Auth::user()->name }}</a>
                @else
                    <a href="{{ route('login') }}">{{ __('ui.topbar.login') }}</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="page static-page" id="main-content" tabindex="-1">@if(session('toast'))<p class="notice" role="status">{{ session('toast') }}</p>@endif @yield('content')</main>
    <footer class="static-footer">
        <div class="static-footer-inner">
            <div class="footer-grid">
                <div><strong>Waasabi</strong><p>{{ __('ui.app.description') }}</p></div>
                <div><strong>{{ __('ui.footer.sections') }}</strong><a href="{{ route('feed') }}">{{ __('ui.nav.feed') }}</a><a href="{{ route('collaboration') }}">{{ __('ui.feed.tab_collaboration') }}</a><a href="{{ route('support') }}">{{ __('ui.support.title') }}</a></div>
                <div><strong>{{ __('ui.footer.legal') }}</strong><a href="{{ route('legal.terms') }}">{{ __('ui.footer.terms') }}</a><a href="{{ route('legal.privacy') }}">{{ __('ui.footer.privacy') }}</a><a href="{{ route('legal.cookies') }}">{{ __('ui.footer.cookies') }}</a></div>
            </div>
            <div class="footer-bottom"><span>{{ __('ui.footer.copyright', ['year' => now()->year]) }}</span><a href="mailto:zloydeveloper.info@gmail.com">{{ __('ui.footer.contact') }}</a></div>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
