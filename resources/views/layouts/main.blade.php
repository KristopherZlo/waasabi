<main
    class="page"
    id="main-content"
    tabindex="-1"
    data-page="@yield('page', 'feed')"
    data-title="{{ $fullTitle }}"
    data-canonical="{{ url()->current() }}"
    @if(session('clear_publish_draft')) data-clear-draft="{{ session('clear_publish_draft') }}" @endif
    @if (session('toast')) data-toast-message="{{ session('toast') }}" @endif
>
    @hasSection('sidebar')
        <div class="layout">
            <div class="content">
                @yield('content')
            </div>
            <aside class="sidebar">
                @yield('sidebar')
            </aside>
        </div>
    @else
        <div class="layout layout--single">
            <div class="content">
                @yield('content')
            </div>
        </div>
    @endif
</main>
