<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Waasabi')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/js/site-loader.ts', 'resources/css/app.css', 'resources/js/app.ts'])
    @endif
    <script>
        window.APP_I18N = @json(trans('ui.js'));
        window.APP_SEARCH_INDEX = @json($searchIndex ?? []);
    </script>
</head>
<body class="app-shell" data-page="@yield('page', 'feed')" data-app-url="{{ url('/') }}" data-locale="{{ app()->getLocale() }}" data-placeholder="{{ asset('images/placeholder.svg') }}" data-auth-state="{{ Auth::check() && !(Auth::user()?->is_banned ?? false) ? '1' : '0' }}" data-banned="{{ Auth::check() && (Auth::user()?->is_banned ?? false) ? '1' : '0' }}" @if (session('toast')) data-toast-message="{{ session('toast') }}" @endif>
    <div class="site-loader" data-site-loader>
        <div class="site-loader__layer site-loader__layer--main">
            <div class="site-loader__content">
                <div class="site-loader__avatar" data-site-loader-avatar aria-hidden="true"></div>
            </div>
        </div>
        <div class="site-loader__layer site-loader__layer--trail site-loader__layer--trail-one"></div>
        <div class="site-loader__layer site-loader__layer--trail site-loader__layer--trail-two"></div>
    </div>
    <div class="bg-orb"></div>
    <div class="bg-orb bg-orb--two"></div>
    <div class="bg-noise"></div>

    <header class="topbar">
        <div class="topbar__inner">
            <div class="top-left">
                <a class="brand-home" href="{{ route('feed') }}" aria-label="{{ __('ui.app.name') }}">
                    <div class="brand-logo">
                        <img class="brand-logo__img" src="{{ asset('images/logo-black.svg') }}" alt="{{ __('ui.app.name') }}">
                    </div>
                    <div class="brand">{{ __('ui.app.name') }}</div>
                </a>
                <a class="top-link" href="{{ route('feed') }}">{{ __('ui.app.all_streams') }}</a>
            </div>
            @if (!empty($topbar_promo))
                <a class="topbar-promo" href="{{ route('promos.click', $topbar_promo['id']) }}" target="_blank" rel="noreferrer noopener">{{ $topbar_promo['label'] }}</a>
            @endif
            <div class="top-actions">
                <button class="icon-btn" type="button" aria-label="{{ __('ui.topbar.search') }}" data-search-open><i data-lucide="search" class="icon"></i></button>
                <div class="read-later">
                    <button class="icon-btn read-later__trigger" type="button" aria-label="{{ __('ui.topbar.read_later') }}" aria-haspopup="menu" aria-expanded="false" data-read-later-toggle>
                        <i data-lucide="bookmark" class="icon"></i>
                    </button>
                    <div class="read-later-menu" role="menu" aria-label="{{ __('ui.read_later.dropdown_title') }}" data-read-later-menu hidden>
                        <div class="read-later-menu__header">{{ __('ui.read_later.dropdown_title') }}</div>
                        <div class="read-later-menu__list" data-read-later-list hidden></div>
                        <div class="read-later-menu__empty" data-read-later-empty>{{ __('ui.read_later.dropdown_empty') }}</div>
                        <a class="read-later-menu__footer" href="{{ route('read-later') }}">{{ __('ui.read_later.dropdown_all') }}</a>
                    </div>
                </div>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.topbar.settings') }}" data-settings-open><i data-lucide="sliders-horizontal" class="icon"></i></button>
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
                @can('moderate')
                    <button class="ghost-btn" type="button" data-admin-toggle>
                        <i data-lucide="edit-3" class="icon"></i>
                        <span>{{ __('ui.admin.edit_mode') }}</span>
                    </button>
                    <a class="ghost-btn" href="{{ route('admin') }}">
                        {{ (Auth::user()?->isAdmin() ?? false) ? __('ui.admin.title') : __('ui.moderation.title') }}
                    </a>
                @endcan
                @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                    @php
                        $topUser = $current_user ?? [];
                        $topUserName = $topUser['name'] ?? (Auth::user()?->name ?? __('ui.project.anonymous'));
                        $topUserSlug = $topUser['slug'] ?? \Illuminate\Support\Str::slug($topUserName);
                        $topUserTag = $topUserSlug ? '@' . $topUserSlug : '';
                        $topUserAvatarPath = $topUser['avatar'] ?? '/images/avatar-default.svg';
                        $topUserAvatarIsDefault = trim($topUserAvatarPath, '/') === 'images/avatar-default.svg';
                        $topUserAvatarUrl = \Illuminate\Support\Str::startsWith($topUserAvatarPath, ['http://', 'https://'])
                            ? $topUserAvatarPath
                            : asset(ltrim($topUserAvatarPath, '/'));
                        $topUserRoleKey = strtolower($topUser['role'] ?? 'user');
                        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
                        $topUserRoleKey = in_array($topUserRoleKey, $roleKeys, true) ? $topUserRoleKey : 'user';
                        $topUserRoleLabel = __('ui.roles.' . $topUserRoleKey);
                        $topUserBio = $topUser['bio'] ?? '';
                        $topUserFollowers = (int) ($topUser['followers_count'] ?? 0);
                        $topUserFollowing = (int) ($topUser['following_count'] ?? 0);
                    @endphp
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
                                        @if (!empty($topUserTag))
                                            <div class="user-menu__tag">{{ $topUserTag }}</div>
                                        @endif
                                    </div>
                                    <span class="badge badge--{{ $topUserRoleKey }}">{{ $topUserRoleLabel }}</span>
                                </div>
                                <div class="user-menu__bio">{{ $topUserBio !== '' ? $topUserBio : __('ui.profile.bio_empty') }}</div>
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
                            </div>
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
                        </div>
                    </div>
                @else
                    <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.topbar.login') }}</a>
                @endif
                <a class="primary-cta" href="{{ route('publish') }}">{{ __('ui.topbar.publish') }}</a>
            </div>
        </div>
    </header>

    <main class="page">
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

    @include('partials.nav')

    <div class="settings-modal" data-settings-modal hidden>
        <div class="settings-card" data-settings-panel>
            <div class="settings-header">
                <div class="settings-header__meta">
                    <div class="settings-title">{{ __('ui.settings.title') }}</div>
                    <div class="settings-subtitle">{{ __('ui.settings.subtitle') }}</div>
                </div>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.settings.close') }}" data-settings-close>
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <div class="settings-body">
                <div class="settings-section settings-section--locale">
                    <div class="settings-label">{{ __('ui.settings.language') }}</div>
                    <div class="settings-locale">
                        <a class="settings-option {{ app()->getLocale() === 'en' ? 'is-active' : '' }}" href="{{ route('locale', 'en') }}">English</a>
                        <a class="settings-option {{ app()->getLocale() === 'fi' ? 'is-active' : '' }}" href="{{ route('locale', 'fi') }}">Suomi</a>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="settings-label">{{ __('ui.settings.publications') }}</div>
                    <div class="settings-stack">
                        <label class="settings-option"><input type="checkbox" data-setting="publications" value="en" checked>English</label>
                        <label class="settings-option"><input type="checkbox" data-setting="publications" value="fi">Suomi</label>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="settings-label">{{ __('ui.settings.feed_view') }}</div>
                    <div class="settings-stack">
                        <label class="settings-option"><input type="radio" name="feed-view" data-setting="feed_view" value="classic" checked>{{ __('ui.settings.feed_classic') }}</label>
                        <label class="settings-option"><input type="radio" name="feed-view" data-setting="feed_view" value="compact">{{ __('ui.settings.feed_compact') }}</label>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="settings-label">{{ __('ui.settings.theme') }}</div>
                    <div class="settings-stack">
                        <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="dark">{{ __('ui.settings.theme_dark') }}</label>
                        <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="light">{{ __('ui.settings.theme_light') }}</label>
                        <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="system" checked>{{ __('ui.settings.theme_system') }}</label>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="settings-label">{{ __('ui.support.title') }}</div>
                    <div class="settings-stack">
                        <a class="settings-option" href="{{ route('support') }}">
                            <i data-lucide="life-buoy" class="icon"></i>
                            <span>{{ __('ui.profile_settings.support_cta') }}</span>
                        </a>
                    </div>
                </div>
            </div>
            <button type="button" class="submit-btn" data-settings-save>{{ __('ui.settings.save') }}</button>
        </div>
    </div>

    <div class="search-spotlight" data-search-modal hidden>
        <div class="search-spotlight__panel" role="dialog" aria-modal="true" aria-label="{{ __('ui.topbar.search') }}">
            <div class="search-spotlight__input">
                <i data-lucide="search" class="icon"></i>
                <input type="text" data-search-input>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.settings.close') }}" data-search-close>
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <div class="search-spotlight__results" data-search-results hidden></div>
        </div>
        <button class="search-spotlight__backdrop" type="button" aria-label="{{ __('ui.settings.close') }}" data-search-close></button>
    </div>

    <div class="report-modal" data-report-modal hidden>
        <div class="report-card" data-report-panel>
            <div class="report-header">
                <div class="report-title">{{ __('ui.report.title') }}</div>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.report.close') }}" data-report-close>
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <form class="report-form" data-report-form>
                <input type="hidden" name="content_type" data-report-type>
                <input type="hidden" name="content_id" data-report-id>
                <input type="hidden" name="content_url" data-report-url>
                <label>
                    {{ __('ui.report.reason') }}
                    <select class="input" name="reason" data-report-reason>
                        <option value="spam">{{ __('ui.report.reason_spam') }}</option>
                        <option value="abuse">{{ __('ui.report.reason_abuse') }}</option>
                        <option value="offtopic">{{ __('ui.report.reason_offtopic') }}</option>
                        <option value="other">{{ __('ui.report.reason_other') }}</option>
                    </select>
                </label>
                <label>
                    {{ __('ui.report.details') }}
                    <textarea class="input" name="details" rows="4" placeholder="{{ __('ui.report.details_placeholder') }}" data-report-details></textarea>
                </label>
                <div class="report-actions">
                    <button type="button" class="ghost-btn" data-report-cancel>{{ __('ui.report.cancel') }}</button>
                    <button type="submit" class="submit-btn">{{ __('ui.report.submit') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="report-modal moderation-modal" data-moderation-modal hidden>
        <div class="report-card moderation-card" data-moderation-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.moderation.reason_title') }}">
            <div class="report-header">
                <div class="report-title" data-moderation-title>{{ __('ui.moderation.reason_title') }}</div>
                <button class="icon-btn" type="button" aria-label="{{ __('ui.settings.close') }}" data-moderation-close>
                    <i data-lucide="x" class="icon"></i>
                </button>
            </div>
            <form class="report-form" data-moderation-form>
                <label>
                    {{ __('ui.moderation.reason_label') }}
                    <textarea class="input" name="reason" rows="4" placeholder="{{ __('ui.moderation.reason_placeholder') }}" data-moderation-reason></textarea>
                </label>
                <div class="form-error" data-moderation-error hidden>{{ __('ui.moderation.reason_required') }}</div>
                <div class="report-actions">
                    <button type="button" class="ghost-btn" data-moderation-cancel>{{ __('ui.moderation.reason_cancel') }}</button>
                    <button type="submit" class="submit-btn" data-moderation-submit>{{ __('ui.moderation.reason_submit') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="cookie-banner" data-cookie-banner hidden role="dialog" aria-live="polite" aria-label="Cookie notice">
        <div class="cookie-banner__card">
            <div class="cookie-banner__text">
                <div class="cookie-banner__title">This site uses cookies</div>
                <p>
                    We use essential cookies to keep you signed in and protect the forum.
                    We do not use analytics or marketing cookies at this time.
                    See our <a href="{{ route('legal.cookies') }}">Cookie Policy</a> and <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
                </p>
            </div>
            <div class="cookie-banner__actions">
                <button type="button" class="ghost-btn" data-cookie-essential>Only essential</button>
                <button type="button" class="submit-btn" data-cookie-accept>Accept</button>
            </div>
        </div>
    </div>

    <button class="scroll-top-rail" type="button" data-scroll-top data-visible="0" aria-label="{{ __('ui.scroll_top') }}">
        <span class="scroll-top-rail__icon" aria-hidden="true">
            <i data-lucide="arrow-up" class="icon"></i>
        </span>
    </button>

    <div class="media-viewer" data-media-viewer hidden>
        <button class="media-viewer__backdrop" type="button" aria-label="{{ __('ui.report.close') }}" data-media-viewer-close></button>
        <div class="media-viewer__panel" role="dialog" aria-modal="true" aria-label="{{ __('ui.js.media_viewer_label') }}">
            <button class="icon-btn media-viewer__close" type="button" aria-label="{{ __('ui.report.close') }}" data-media-viewer-close>
                <i data-lucide="x" class="icon"></i>
            </button>
            <div class="media-viewer__stage">
                <button class="icon-btn media-viewer__nav media-viewer__nav--prev" type="button" aria-label="{{ __('ui.js.carousel_prev') }}" data-media-viewer-prev>
                    <i data-lucide="chevron-left" class="icon"></i>
                </button>
                <img class="media-viewer__image" src="" alt="" data-media-viewer-image>
                <button class="icon-btn media-viewer__nav media-viewer__nav--next" type="button" aria-label="{{ __('ui.js.carousel_next') }}" data-media-viewer-next>
                    <i data-lucide="chevron-right" class="icon"></i>
                </button>
            </div>
            <div class="media-viewer__thumbs" data-media-viewer-thumbs></div>
        </div>
    </div>

    <footer class="site-footer">
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
