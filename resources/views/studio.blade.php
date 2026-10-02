<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($pageDescription = $profileMeta['description'] ?? 'A community for IT, design, video editing, and practical collaboration.')
    <meta name="description" content="{{ $pageDescription }}">
    @if(isset($profileMeta))
        <meta property="og:type" content="profile">
        <meta property="og:site_name" content="waasabi">
        <meta property="og:title" content="{{ $profileMeta['title'] }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $profileMeta['url'] }}">
        @if($profileMeta['image'])
            <meta property="og:image" content="{{ $profileMeta['image'] }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
    @endif
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
