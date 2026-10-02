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
        @vite(['resources/js/studio/static.css', 'resources/js/studio/static.ts'])
    @endif
</head>
<body class="static-shell" data-page="@yield('page', 'static')" data-external-warning-title="{{ __('studio.leave_site') }}" data-external-warning-text="{{ __('studio.leave_site_hint') }}" data-external-warning-cancel="{{ __('studio.stay_here') }}" data-external-warning-continue="{{ __('studio.continue_external') }}">
    <a class="skip" href="#main-content">{{ __('ui.app.skip_to_content') }}</a>
    @include('partials.studio-topbar')
    @hasSection('context-nav')<div class="static-context-nav">@yield('context-nav')</div>@endif
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
    <script nonce="{{ $csp_nonce ?? '' }}">document.querySelector('[data-static-theme]')?.addEventListener('click', () => {const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = next; try { localStorage.setItem('waasabi:theme', next); } catch {}}); document.addEventListener('keydown', event => {if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {event.preventDefault(); document.querySelector('[data-static-search]')?.focus();}});</script>
</body>
</html>
