<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="A community for creative work, projects and small collaborations.">
    <script nonce="{{ $csp_nonce ?? '' }}">try { document.documentElement.dataset.theme = localStorage.getItem('waasabi:theme') || 'dark'; } catch { document.documentElement.dataset.theme = 'dark'; }</script>
    @viteReactRefresh
    @vite('resources/js/studio/app.tsx')
    @inertiaHead
</head>
<body data-external-warning-title="{{ __('studio.leave_site') }}" data-external-warning-text="{{ __('studio.leave_site_hint') }}" data-external-warning-cancel="{{ __('studio.stay_here') }}" data-external-warning-continue="{{ __('studio.continue_external') }}">
    @inertia
    <noscript><p>Enable JavaScript to use the community editor and navigation.</p></noscript>
</body>
</html>
