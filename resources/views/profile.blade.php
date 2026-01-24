@extends('layouts.app')

@section('title', __('ui.profile.title'))
@section('page', 'profile')

@section('content')
    @php
        $profileAvatarPath = $profile_user['avatar'] ?? 'images/avatar-default.svg';
        $profileAvatarIsDefault = trim($profileAvatarPath, '/') === 'images/avatar-default.svg';
        $profileAvatarUrl = \Illuminate\Support\Str::startsWith($profileAvatarPath, ['http://', 'https://'])
            ? $profileAvatarPath
            : asset(ltrim($profileAvatarPath, '/'));
        $profileBannerPath = trim((string) ($profile_user['banner_url'] ?? ''));
        $profileBannerUrl = $profileBannerPath !== ''
            ? (\Illuminate\Support\Str::startsWith($profileBannerPath, ['http://', 'https://'])
                ? $profileBannerPath
                : asset(ltrim($profileBannerPath, '/')))
            : '';
        $hasCustomBanner = $profileBannerUrl !== '';
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $roleKey = strtolower($profile_user['role'] ?? 'user');
        $roleKey = in_array($roleKey, $roleKeys, true) ? $roleKey : 'user';
        $profileSlug = $profile_user['slug'] ?? '';
        $allowFollow = $profile_user['allow_follow'] ?? true;
        $showFollowCounts = $profile_user['show_follow_counts'] ?? true;
        $canShowCounts = $showFollowCounts || $is_owner;
        $badges = $badges ?? [];
        $isBanned = $profile_user['is_banned'] ?? false;
        $viewer = Auth::user();
        $viewerRoleKey = strtolower($viewer?->role ?? 'user');
        $viewerRoleKey = in_array($viewerRoleKey, $roleKeys, true) ? $viewerRoleKey : 'user';
        $canManageBadges = $viewer?->isAdmin() ?? false;
        $canModerateUser = $viewer?->hasRole('moderator') ?? false;
        $canChangeAvatar = $is_owner && !$isBanned && Auth::check();
        $canChangeBanner = $canChangeAvatar && function_exists('safeHasColumn')
            ? safeHasColumn('users', 'banner_url')
            : false;
    @endphp
    <section class="hero">
        <div class="profile-banner-wrap">
            <div class="action-menu action-menu--card profile-banner__menu" data-action-menu-container>
                <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.profile.actions') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                    <i data-lucide="more-horizontal" class="icon"></i>
                </button>
                <div class="action-menu__panel" role="menu" data-action-menu hidden>
                    @if ($canChangeBanner)
                        <button type="button" class="action-menu__item" data-profile-banner-change>
                            <i data-lucide="image" class="icon"></i>
                            <span>{{ __('ui.profile.action_change_banner') }}</span>
                        </button>
                    @endif
                    @if ($canManageBadges)
                        <button type="button" class="action-menu__item" data-profile-action="grant-badge">
                            <i data-lucide="award" class="icon"></i>
                            <span>{{ __('ui.profile.action_grant_badge') }}</span>
                        </button>
                        @if (!$is_owner && !empty($profile_user['id']))
                            <form method="POST" action="{{ route('admin.users.ban', $profile_user['id']) }}" data-moderation-reason-form data-moderation-action="{{ $isBanned ? 'unban' : 'ban' }}">
                                @csrf
                                <input type="hidden" name="reason" value="">
                                <button type="submit" class="action-menu__item action-menu__item--danger">
                                    <i data-lucide="ban" class="icon"></i>
                                    <span>{{ $isBanned ? __('ui.admin.unban') : __('ui.admin.ban') }}</span>
                                </button>
                            </form>
                        @endif
                        <button type="button" class="action-menu__item action-menu__item--danger" data-profile-action="revoke-badge">
                            <i data-lucide="x-circle" class="icon"></i>
                            <span>{{ __('ui.profile.action_revoke_badge') }}</span>
                        </button>
                    @elseif ($canModerateUser)
                        @if (!$is_owner && !empty($profile_user['id']))
                            <form method="POST" action="{{ route('admin.users.ban', $profile_user['id']) }}" data-moderation-reason-form data-moderation-action="{{ $isBanned ? 'unban' : 'ban' }}">
                                @csrf
                                <input type="hidden" name="reason" value="">
                                <button type="submit" class="action-menu__item action-menu__item--danger">
                                    <i data-lucide="ban" class="icon"></i>
                                    <span>{{ $isBanned ? __('ui.admin.unban') : __('ui.admin.ban') }}</span>
                                </button>
                            </form>
                        @endif
                    @else
                        @if (!$is_owner)
                            <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="content" data-report-id="{{ $profile_user['id'] ?? '' }}" data-report-url="{{ url()->current() }}">
                                <i data-lucide="flag" class="icon"></i>
                                <span>{{ __('ui.report.title') }}</span>
                            </button>
                        @endif
                    @endif
                </div>
            </div>
            @if ($canChangeBanner)
                <input type="file" accept="image/*" data-profile-banner-input hidden>
            @endif
            <div class="profile-banner {{ $hasCustomBanner ? 'has-custom-banner' : '' }}" data-profile-banner data-profile-name="{{ $profile_user['name'] }}" data-profile-user-id="{{ $profile_user['id'] ?? '' }}" data-profile-user-slug="{{ $profileSlug }}" data-profile-banner-image="{{ $hasCustomBanner ? $profileBannerUrl : '' }}" @if ($hasCustomBanner) style="--profile-banner-image: url('{{ $profileBannerUrl }}');" @endif>
                <div class="profile-banner__glow" data-profile-banner-glow></div>
                <div class="profile-banner__scribble" data-profile-banner-scribble></div>
            </div>
        </div>
        <div class="profile-header">
            <div class="profile-header__identity">
                @if ($canChangeAvatar)
                    <input type="file" accept="image/*" data-profile-avatar-input hidden>
                    <label class="profile-avatar-edit" data-profile-avatar-trigger tabindex="0">
                        <img class="avatar avatar--xl profile-avatar-edit__image" src="{{ $profileAvatarUrl }}" alt="{{ $profile_user['name'] }}" data-profile-avatar-image @if ($profileAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $profile_user['name'] }}" @endif>
                        <span class="profile-avatar-edit__overlay">
                            <i data-lucide="pencil" class="icon"></i>
                        </span>
                    </label>
                @else
                    <img class="avatar avatar--xl" src="{{ $profileAvatarUrl }}" alt="{{ $profile_user['name'] }}" @if ($profileAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $profile_user['name'] }}" @endif>
                @endif
                <div>
                    <h1>{{ $profile_user['name'] }}</h1>
                    @if (!empty($profileSlug))
                        <div class="profile-handle">{{ '@' . $profileSlug }}</div>
                    @endif
                </div>
            </div>
            @if (!empty($badges))
                <div class="profile-badges">
                    <div class="profile-badges__list">
                        @foreach ($badges as $badge)
                        @php
                            $badgeLabel = $badge['label'] ?? '';
                            $badgeReason = $badge['reason'] ?? '';
                            if ($badgeReason === '') {
                                $badgeReason = $badge['description'] ?? '';
                            }
                            $badgeIssued = $badge['issued_at'] ?? '';
                            $badgeTooltip = implode(' - ', array_filter([$badgeIssued, $badgeReason]));
                        @endphp
                            <button
                                type="button"
                                class="profile-badge"
                                data-badge-view
                                data-badge-icon="{{ $badge['icon'] ?? '' }}"
                                data-badge-label="{{ $badgeLabel }}"
                                data-badge-description="{{ $badgeReason }}"
                                data-badge-issued="{{ $badgeIssued }}"
                                data-tooltip="{{ $badgeTooltip !== '' ? $badgeTooltip : $badgeLabel }}"
                                aria-label="{{ $badgeLabel }}"
                            >
                                <img src="{{ $badge['icon'] ?? '' }}" alt="{{ $badgeLabel }}">
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @if (!empty($profile_user['bio']))
            <p>{{ $profile_user['bio'] }}</p>
        @endif
        @if ($isBanned)
            <div class="profile-banned">{{ __('ui.profile.banned_notice') }}</div>
        @endif
        <div class="meta">
            @if ($isBanned)
                <span class="badge badge--banned">{{ __('ui.admin.banned') }}</span>
            @endif
            <span class="badge badge--{{ $roleKey }}">{{ __('ui.roles.' . $roleKey) }}</span>
            @if (!empty($profile_user['id']))
                <span class="chip chip--comment">{{ __('ui.profile.id_label') }} {{ $profile_user['id'] }}</span>
            @endif
            @if ($canShowCounts)
                <span class="chip chip--comment">{{ __('ui.profile.followers') }}: <span data-followers-count>{{ $followers_count }}</span></span>
                <span class="chip chip--comment">{{ __('ui.profile.following') }}: <span data-following-count>{{ $following_count }}</span></span>
            @endif
        </div>
        <div class="profile-actions">
            @if ($is_owner)
                <a class="ghost-btn" href="{{ route('profile.settings') }}">{{ __('ui.profile.settings_cta') }}</a>
            @else
                @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                    @if ($allowFollow && !empty($profileSlug))
                        <form method="POST" action="{{ route('profile.follow', $profileSlug) }}" data-follow-form data-following="{{ $is_following ? '1' : '0' }}">
                            @csrf
                            <button class="ghost-btn" type="submit" data-follow-button data-follow-label="{{ __('ui.profile.follow') }}" data-unfollow-label="{{ __('ui.profile.unfollow') }}">
                                {{ $is_following ? __('ui.profile.unfollow') : __('ui.profile.follow') }}
                            </button>
                        </form>
                    @endif
                @endif
            @endif
        </div>
    </section>

    @if ($canChangeAvatar || $canChangeBanner)
        <div class="report-modal profile-media-modal" data-profile-media-modal hidden>
            <div class="report-card profile-media-card" data-profile-media-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.profile.media_modal_title') }}">
                <div class="report-header">
                    <div class="report-title" data-profile-media-title>{{ __('ui.profile.media_modal_title') }}</div>
                    <button class="icon-btn" type="button" aria-label="{{ __('ui.report.close') }}" data-profile-media-close>
                        <i data-lucide="x" class="icon"></i>
                    </button>
                </div>
                <div class="profile-media-info" data-profile-media-info></div>
                <div class="profile-media-editor" data-profile-media-editor hidden>
                    <div class="profile-media-editor__frame" data-profile-media-frame>
                        <img class="profile-media-editor__image" data-profile-media-image alt="">
                    </div>
                    <label class="profile-media-editor__zoom">
                        <span>{{ __('ui.profile.media_zoom') }}</span>
                        <input class="input" type="range" min="1" max="3" step="0.01" value="1" data-profile-media-zoom>
                        <span class="profile-media-editor__zoom-value" data-profile-media-zoom-value>100%</span>
                    </label>
                </div>
                <div class="form-error" data-profile-media-error hidden></div>
                <div class="profile-media-actions">
                    <button type="button" class="ghost-btn ghost-btn--danger" data-profile-media-remove hidden>{{ __('ui.profile.media_remove') }}</button>
                    <button type="button" class="ghost-btn" data-profile-media-choose>{{ __('ui.profile.media_choose') }}</button>
                    <div class="profile-media-actions__spacer"></div>
                    <button type="button" class="ghost-btn" data-profile-media-cancel>{{ __('ui.report.cancel') }}</button>
                    <button type="button" class="submit-btn" data-profile-media-apply disabled>{{ __('ui.profile.media_apply') }}</button>
                </div>
                <div class="profile-media-loading" data-profile-media-loading hidden>
                    <div class="profile-media-loading__spinner" aria-hidden="true"></div>
                    <div class="profile-media-loading__text">{{ __('ui.profile.media_uploading') }}</div>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($badges) || $canManageBadges)
        <div class="badge-modal badge-modal--view" data-badge-view-modal hidden>
            <div class="badge-view-card" data-badge-view-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.view_title') }}">
                <button type="button" class="icon-btn badge-view-card__close" data-badge-view-close aria-label="{{ __('ui.report.close') }}">
                    <i data-lucide="x" class="icon"></i>
                </button>
                <div class="badge-view-card__media">
                    <div class="badge-view-card__glow" aria-hidden="true"></div>
                    <img class="badge-view-card__icon" data-badge-view-icon alt="">
                </div>
                <div class="badge-view-card__body">
                    <div class="badge-view-card__title" data-badge-view-label></div>
                    <div class="badge-view-card__desc" data-badge-view-description></div>
                    <div class="badge-view-card__meta">
                        <span class="badge-view-card__meta-label">{{ __('ui.badges.view_issued') }}</span>
                        <span class="badge-view-card__meta-value" data-badge-view-issued>{{ __('ui.badges.view_issued_unknown') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($canManageBadges)
        <script type="application/json" data-badge-catalog>@json($badge_catalog ?? [])</script>
        <script type="application/json" data-user-badges>@json($badges)</script>

        <div class="badge-modal" data-badge-modal hidden>
            <div class="badge-card" data-badge-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.grant_title') }}">
                <div class="badge-header">
                    <div>
                        <div class="badge-title">{{ __('ui.badges.grant_title') }}</div>
                        <div class="badge-subtitle">{{ __('ui.badges.grant_subtitle') }}</div>
                    </div>
                    <button type="button" class="icon-btn" data-badge-close aria-label="{{ __('ui.report.close') }}">
                        <i data-lucide="x" class="icon"></i>
                    </button>
                </div>
                <div class="badge-grid" data-badge-grid></div>
                <form class="badge-form" data-badge-form>
                    <label>
                        {{ __('ui.badges.custom_name') }}
                        <input class="input" type="text" data-badge-name placeholder="{{ __('ui.badges.custom_name_placeholder') }}">
                    </label>
                    <label>
                        {{ __('ui.badges.custom_description') }}
                        <textarea class="input" rows="3" data-badge-description placeholder="{{ __('ui.badges.custom_description_placeholder') }}"></textarea>
                    </label>
                    <label>
                        {{ __('ui.badges.reason') }}
                        <input class="input" type="text" data-badge-reason placeholder="{{ __('ui.badges.reason_placeholder') }}">
                    </label>
                    <button type="submit" class="submit-btn" data-badge-submit>{{ __('ui.badges.grant_cta') }}</button>
                </form>
            </div>
        </div>

        <div class="badge-modal" data-badge-revoke-modal hidden>
            <div class="badge-card" data-badge-revoke-panel role="dialog" aria-modal="true" aria-label="{{ __('ui.badges.revoke_title') }}">
                <div class="badge-header">
                    <div>
                        <div class="badge-title">{{ __('ui.badges.revoke_title') }}</div>
                        <div class="badge-subtitle">{{ __('ui.badges.revoke_subtitle') }}</div>
                    </div>
                    <button type="button" class="icon-btn" data-badge-revoke-close aria-label="{{ __('ui.report.close') }}">
                        <i data-lucide="x" class="icon"></i>
                    </button>
                </div>
                <div class="badge-revoke-list" data-badge-revoke-list></div>
                <div class="badge-revoke-empty" data-badge-revoke-empty hidden>{{ __('ui.badges.revoke_empty') }}</div>
            </div>
        </div>
    @endif

    <section class="section" style="margin-top: 24px;">
        <div class="section-title">{{ __('ui.profile.projects') }}</div>
        <div class="list">
            @forelse ($projects as $project)
                @include('partials.project-card', ['project' => $project])
            @empty
                <div class="list-item">{{ __('ui.profile.no_posts') }}</div>
            @endforelse
        </div>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="section-title">{{ __('ui.profile.questions') }}</div>
        <div class="list">
            @forelse ($questions as $question)
                <div class="list-item">
                    <a href="{{ route('questions.show', $question['slug']) }}">{{ $question['title'] }}</a>
                </div>
            @empty
                <div class="list-item">{{ __('ui.profile.no_questions') }}</div>
            @endforelse
        </div>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="section-title">{{ __('ui.profile.comments') }}</div>
        <div class="list">
            @forelse ($comments as $comment)
                <div class="list-item">
                    @php
                        $commentTargetUrl = ($comment['post_type'] ?? 'post') === 'question'
                            ? route('questions.show', $comment['post_slug'])
                            : route('project', $comment['post_slug']);
                    @endphp
                    <div class="comment-meta">
                        <span class="post-author">
                            <a href="{{ $commentTargetUrl }}">
                                {{ $comment['post_title'] }}
                            </a>
                        </span>
                        <span class="dot">&bull;</span>
                        <span class="comment-time">{{ $comment['time'] }}</span>
                        @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                            @if (!$is_owner)
                                <div class="action-menu action-menu--inline" data-action-menu-container>
                                    <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                                        <i data-lucide="more-horizontal" class="icon"></i>
                                    </button>
                                    <div class="action-menu__panel" role="menu" data-action-menu hidden>
                                        <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="comment" data-report-id="{{ $comment['id'] ?? $loop->index }}" data-report-url="{{ $commentTargetUrl }}">
                                            <i data-lucide="flag" class="icon"></i>
                                            <span>{{ __('ui.report.title') }}</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    <p class="comment-body">{{ $comment['body'] }}</p>
                </div>
            @empty
                <div class="list-item">{{ __('ui.profile.no_comments') }}</div>
            @endforelse
        </div>
    </section>
@endsection

