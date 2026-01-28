<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Waasabi Support')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/site-loader.ts', 'resources/css/app.css', 'resources/js/app.ts'])
    @endif
    <script nonce="{{ $csp_nonce ?? '' }}">
        window.APP_I18N = @json(trans('ui.js'));
        window.APP_SEARCH_INDEX = @json($searchIndex ?? []);
    </script>
</head>
<body class="app-shell" data-page="@yield('page', 'support')" data-app-url="{{ url('/') }}" data-locale="{{ app()->getLocale() }}" data-placeholder="{{ asset('images/placeholder.svg') }}" data-auth-state="{{ Auth::check() && !(Auth::user()?->is_banned ?? false) ? '1' : '0' }}" data-banned="{{ Auth::check() && (Auth::user()?->is_banned ?? false) ? '1' : '0' }}" data-no-spa="1" @if (session('toast')) data-toast-message="{{ session('toast') }}" @endif>
    @php
        $supportTab = (string) request('tab', '');
        $supportTabs = ['home', 'tickets', 'new'];
        if (!in_array($supportTab, $supportTabs, true)) {
            $supportTab = request()->has('ticket') ? 'tickets' : 'home';
        }
        $supportIsArticle = request()->routeIs('support.kb') || request()->routeIs('support.docs');
    @endphp
    <header class="support-topbar">
        <div class="support-topbar__inner">
            <a class="support-brand" href="{{ route('support') }}" aria-label="{{ __('ui.app.name') }}">
                <span class="support-brand__logo">
                    <img src="{{ asset('images/logo-black.svg') }}" alt="{{ __('ui.app.name') }}">
                </span>
                <span class="support-brand__text">
                    <span class="support-brand__name">{{ __('ui.app.name') }}</span>
                    <span class="support-brand__context">support</span>
                </span>
            </a>
            <div class="support-topbar__actions">
                <nav class="support-nav" aria-label="{{ __('ui.support.title') }}">
                    @if ($supportIsArticle)
                        <a class="support-nav__link {{ $supportTab === 'home' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'home']) }}">
                            {{ __('ui.support.portal_nav_home') }}
                        </a>
                        <a class="support-nav__link {{ $supportTab === 'tickets' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'tickets']) }}">
                            {{ __('ui.support.portal_nav_tickets') }}
                        </a>
                        <a class="support-nav__link {{ $supportTab === 'new' ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'new']) }}">
                            {{ __('ui.support.portal_nav_new') }}
                        </a>
                    @else
                        <button class="support-nav__link {{ $supportTab === 'home' ? 'is-active' : '' }}" type="button" data-tab="home">
                            {{ __('ui.support.portal_nav_home') }}
                        </button>
                        <button class="support-nav__link {{ $supportTab === 'tickets' ? 'is-active' : '' }}" type="button" data-tab="tickets">
                            {{ __('ui.support.portal_nav_tickets') }}
                        </button>
                        <button class="support-nav__link {{ $supportTab === 'new' ? 'is-active' : '' }}" type="button" data-tab="new">
                            {{ __('ui.support.portal_nav_new') }}
                        </button>
                    @endif
                </nav>
                @php
                    $isAuthed = Auth::check() && !(Auth::user()?->is_banned ?? false);
                    $supportUser = $current_user ?? (function_exists('currentUserPayload') ? currentUserPayload() : []);
                    $topUserName = $supportUser['name'] ?? (Auth::user()?->name ?? __('ui.project.anonymous'));
                    $topUserSlug = $supportUser['slug'] ?? \Illuminate\Support\Str::slug($topUserName);
                    $topUserTag = $topUserSlug ? '@' . $topUserSlug : '';
                    $topUserAvatarPath = $supportUser['avatar'] ?? '/images/avatar-default.svg';
                    $topUserAvatarIsDefault = trim($topUserAvatarPath, '/') === 'images/avatar-default.svg';
                    $topUserAvatarUrl = \Illuminate\Support\Str::startsWith($topUserAvatarPath, ['http://', 'https://'])
                        ? $topUserAvatarPath
                        : asset(ltrim($topUserAvatarPath, '/'));
                    $topUserRoleKey = strtolower($supportUser['role'] ?? 'user');
                    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
                    $topUserRoleKey = in_array($topUserRoleKey, $roleKeys, true) ? $topUserRoleKey : 'user';
                    $topUserRoleLabel = __('ui.roles.' . $topUserRoleKey);
                    $topUserBio = $supportUser['bio'] ?? '';
                    $topUserFollowers = (int) ($supportUser['followers_count'] ?? 0);
                    $topUserFollowing = (int) ($supportUser['following_count'] ?? 0);
                @endphp
                @if ($isAuthed)
                    <div class="notifications">
                        <button class="icon-btn notifications-trigger" type="button" aria-label="{{ __('ui.topbar.notifications') }}" aria-haspopup="menu" aria-expanded="false" aria-controls="notifications-menu" data-notifications-toggle>
                            <i data-lucide="bell" class="icon"></i>
                            @if (($unreadCount ?? 0) > 0)
                                <span class="notification-badge" aria-hidden="true"></span>
                            @endif
                        </button>
                        <div class="notifications-menu" id="notifications-menu" role="menu" aria-label="{{ __('ui.notifications.dropdown_title') }}" data-notifications-menu hidden>
                            <div class="notifications-menu__header">
                                <span>{{ __('ui.notifications.dropdown_title') }}</span>
                                @if (($unreadCount ?? 0) > 0)
                                    <span class="notifications-menu__count">{{ $unreadCount }}</span>
                                @endif
                            </div>
                            <div class="notifications-menu__list">
                                @foreach (($unreadNotifications ?? []) as $notification)
                                    @php
                                        $notificationLink = $notification['link'] ?? route('notifications');
                                        $notificationId = $notification['id'] ?? '';
                                    @endphp
                                    <a class="notifications-menu__item" href="{{ $notificationLink }}" role="menuitem" @if ($notificationId !== '') data-notification-id="{{ $notificationId }}" @endif>
                                        <div class="notifications-menu__meta">
                                            <span class="notifications-menu__type">{{ $notification['type'] }}</span>
                                            <span class="notifications-menu__time">{{ $notification['time'] }}</span>
                                        </div>
                                        <div class="notifications-menu__text">{{ $notification['text'] }}</div>
                                    </a>
                                @endforeach
                                <div class="notifications-menu__empty" @if (($unreadCount ?? 0) > 0) hidden @endif>
                                    {{ __('ui.notifications.dropdown_empty') }}
                                </div>
                            </div>
                            <a class="notifications-menu__footer" href="{{ route('notifications') }}">{{ __('ui.notifications.dropdown_all') }}</a>
                        </div>
                    </div>
                @endif
                <div class="action-menu user-menu" data-action-menu-container>
                    <button class="user-menu__trigger" type="button" aria-label="{{ $topUserName }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                        <img class="avatar avatar--sm" src="{{ $topUserAvatarUrl }}" alt="{{ $topUserName }}" @if ($topUserAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $topUserName }}" @endif>
                        <span class="user-menu__name">{{ $topUserName }}</span>
                        <i data-lucide="chevron-down" class="icon"></i>
                    </button>
                    <div class="action-menu__panel user-menu__panel" role="menu" data-action-menu hidden>
                        <div class="user-menu__card">
                            <div class="user-menu__identity">
                                <img class="avatar" src="{{ $topUserAvatarUrl }}" alt="{{ $topUserName }}" @if ($topUserAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $topUserName }}" @endif>
                                <div class="user-menu__meta">
                                    <div class="user-menu__title">{{ $topUserName }}</div>
                                    @if ($isAuthed && !empty($topUserTag))
                                        <div class="user-menu__tag">{{ $topUserTag }}</div>
                                    @endif
                                </div>
                                <span class="badge badge--{{ $topUserRoleKey }}">{{ $topUserRoleLabel }}</span>
                            </div>
                            <div class="user-menu__bio">{{ $topUserBio !== '' ? $topUserBio : __('ui.profile.bio_empty') }}</div>
                            @if ($isAuthed)
                                <div class="user-menu__stats">
                                    <div class="user-menu__stat">
                                        <span class="user-menu__stat-value">{{ $topUserFollowers }}</span>
                                        <span class="user-menu__stat-label">{{ __('ui.profile.followers') }}</span>
                                    </div>
                                    <div class="user-menu__stat">
                                        <span class="user-menu__stat-value">{{ $topUserFollowing }}</span>
                                        <span class="user-menu__stat-label">{{ __('ui.profile.following') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                        @if ($isAuthed)
                            @if (!empty($topUserSlug))
                                <a class="action-menu__item" href="{{ route('profile.show', $topUserSlug) }}">
                                    <i data-lucide="user" class="icon"></i>
                                    <span>{{ __('ui.profile.title') }}</span>
                                </a>
                            @endif
                            <a class="action-menu__item" href="{{ route('profile.settings') }}">
                                <i data-lucide="settings" class="icon"></i>
                                <span>{{ __('ui.profile_settings.title') }}</span>
                            </a>
                            <form class="user-menu__logout" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="action-menu__item action-menu__item--danger" type="submit">
                                    <i data-lucide="log-out" class="icon"></i>
                                    <span>{{ __('ui.topbar.logout') }}</span>
                                </button>
                            </form>
                        @else
                            <a class="action-menu__item" href="{{ route('login') }}">
                                <i data-lucide="log-in" class="icon"></i>
                                <span>{{ __('ui.topbar.login') }}</span>
                            </a>
                            <a class="action-menu__item" href="{{ route('register') }}">
                                <i data-lucide="user-plus" class="icon"></i>
                                <span>{{ __('ui.nav.register') }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="page">
        <div class="layout layout--single">
            <div class="content">
                @yield('content')
            </div>
        </div>
    </main>

    <footer class="site-footer site-footer--light site-footer--compact">
        <div class="site-footer__inner">
            <div class="footer-grid">
                <div class="footer-col footer-col--account">
                    <div class="footer-title">{{ __('ui.footer.account') }}</div>
                    <a href="{{ route('login') }}" class="footer-link">{{ __('ui.footer.sign_in') }}</a>
                    <a href="{{ route('register') }}" class="footer-link">{{ __('ui.footer.register') }}</a>
                </div>
                <div class="footer-col">
                    <div class="footer-title">{{ __('ui.footer.sections') }}</div>
                    <a href="{{ route('feed') }}" class="footer-link">{{ __('ui.nav.feed') }}</a>
                    <a href="{{ route('feed', ['stream' => 'questions']) }}" class="footer-link">{{ __('ui.feed.tab_questions') }}</a>
                    <a href="{{ route('showcase') }}" class="footer-link">{{ __('ui.nav.showcase') }}</a>
                    <a href="{{ route('read-later') }}" class="footer-link">{{ __('ui.nav.read_later') }}</a>
                    <a href="{{ route('profile') }}" class="footer-link">{{ __('ui.nav.profile') }}</a>
                </div>
                <div class="footer-col">
                    <div class="footer-title">Legal</div>
                    <a href="{{ route('legal.terms') }}" class="footer-link">Terms of Service</a>
                    <a href="{{ route('legal.privacy') }}" class="footer-link">Privacy Policy</a>
                    <a href="{{ route('legal.cookies') }}" class="footer-link">Cookie Policy</a>
                    <a href="{{ route('legal.guidelines') }}" class="footer-link">Community Guidelines</a>
                    <a href="{{ route('legal.notice') }}" class="footer-link">Notice &amp; Action</a>
                    <a href="{{ route('legal.legal-notice') }}" class="footer-link">Legal Notice</a>
                    <a href="mailto:zloydeveloper.info@gmail.com" class="footer-link">Contact</a>
                </div>
                <div class="footer-col">
                    <div class="footer-title">{{ __('ui.footer.services') }}</div>
                    <a href="{{ route('publish') }}" class="footer-link">{{ __('ui.publish.title') }}</a>
                    <a href="{{ route('read-later') }}" class="footer-link">{{ __('ui.read_later.title') }}</a>
                    <a href="{{ route('showcase') }}" class="footer-link">{{ __('ui.showcase.title') }}</a>
                    <a href="{{ route('notifications') }}" class="footer-link">{{ __('ui.notifications.title') }}</a>
                    <a href="{{ route('support') }}" class="footer-link">{{ __('ui.support.title') }}</a>
                </div>
            </div>
            <div class="footer-bottom">
                @php
                    $footerStartYear = 2026;
                    $footerCurrentYear = now()->year;
                    $footerYearRange = $footerCurrentYear > $footerStartYear
                        ? $footerStartYear . '-' . $footerCurrentYear
                        : (string) $footerStartYear;
                @endphp
                <div class="footer-copy">{{ __('ui.footer.copyright', ['year' => $footerYearRange]) }}</div>
                <div class="footer-links">
                    <a href="{{ route('legal.terms') }}" class="footer-link">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="footer-link">Privacy</a>
                    <a href="{{ route('legal.cookies') }}" class="footer-link">Cookies</a>
                    <a href="mailto:zloydeveloper.info@gmail.com" class="footer-link">Contact</a>
                </div>
                <div class="footer-social">
                    <a class="social-chip" href="https://github.com" target="_blank" rel="noreferrer noopener">{{ __('ui.footer.github') }}</a>
                </div>
            </div>
        </div>
    </footer>

    <div class="toast" data-toast></div>
</body>
</html>
