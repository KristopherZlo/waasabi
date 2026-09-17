@extends('layouts.app')

@section('title', $profile_user['name'] ?? __('ui.profile.title'))
@section('description', \Illuminate\Support\Str::limit(strip_tags($profile_user['bio'] ?? __('ui.app.description')), 155))
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
        $canChangeBanner = $canChangeAvatar;
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
                            <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="profile" data-report-id="{{ $profile_user['id'] ?? '' }}" data-report-url="{{ url()->current() }}">
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
        @if (!empty($profile_user['skills']))<p>{{ $profile_user['skills'] }}</p>@endif
        @if (!empty($profile_user['open_to_help']))<p class="availability-note">{{ __('waasabi.available') }}</p>@endif
        @if (!empty($profile_user['portfolio_url']))<a href="{{ $profile_user['portfolio_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('waasabi.view_portfolio') }} ↗</a>@endif
        @if ($isBanned)
            <div class="profile-banned">{{ __('ui.profile.banned_notice') }}</div>
        @endif
        <div class="meta">
            @if ($isBanned)
                <span class="badge badge--banned">{{ __('ui.admin.banned') }}</span>
            @endif
            <span class="badge badge--{{ $roleKey }}">{{ __('ui.roles.' . $roleKey) }}</span>
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

    @if ($help_requests->isNotEmpty())
        <section class="profile-contributions"><h2>{{ __('waasabi.help_requests') }}</h2>
            @foreach ($help_requests as $helpRequest)
                <a href="{{ route('collaboration.show', $helpRequest) }}">{{ $helpRequest->title }} <span class="helper">{{ __('ui.collaboration.status_'.($helpRequest->isOpen() ? 'open' : 'closed')) }}</span></a>
            @endforeach
        </section>
    @endif
    @if (($contributions ?? collect())->isNotEmpty())
        <section class="profile-contributions"><h2>{{ __('waasabi.contributions') }}</h2>
            @foreach ($contributions as $contribution)<a href="{{ route('project', $contribution->slug) }}">{{ $contribution->title }} <span class="helper">{{ $contribution->user->name }}</span></a>@endforeach
        </section>
    @endif
    @include('partials.profile-modals')

    <div class="profile-content">
        <div class="tabs profile-tabs" data-tabs role="tablist" aria-label="{{ __('ui.profile.title') }}">
            <button type="button" class="tab is-active" data-tab="projects" role="tab" aria-selected="true">
                <i data-lucide="folder" class="icon" aria-hidden="true"></i>
                <span>{{ __('ui.profile.projects') }}</span>
                <span class="tab__count">{{ count($projects) }}</span>
            </button>
            <button type="button" class="tab" data-tab="questions" role="tab" aria-selected="false" tabindex="-1">
                <i data-lucide="circle-help" class="icon" aria-hidden="true"></i>
                <span>{{ __('ui.profile.questions') }}</span>
                <span class="tab__count">{{ count($questions) }}</span>
            </button>
            <button type="button" class="tab" data-tab="comments" role="tab" aria-selected="false" tabindex="-1">
                <i data-lucide="message-circle" class="icon" aria-hidden="true"></i>
                <span>{{ __('ui.profile.comments') }}</span>
                <span class="tab__count">{{ count($comments) }}</span>
            </button>
        </div>

    <section class="section profile-section tab-panel is-active" data-tab-panel="projects">
        <div class="list">
            @forelse ($projects as $project)
                @if (($profile_user['featured_post_id'] ?? null) === $project['id'])<h2>{{ __('waasabi.featured') }}</h2>@endif
                @include('partials.project-card', ['project' => $project])
            @empty
                <div class="profile-empty">
                    <p>{{ $is_owner ? __('ui.profile.no_posts_owner') : __('ui.profile.no_posts') }}</p>
                    @if ($is_owner && !$isBanned)
                        <a class="ghost-btn" href="{{ route('publish') }}">{{ __('ui.profile.publish_project') }}</a>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    <section class="section profile-section tab-panel" data-tab-panel="questions" hidden>
        <div class="list">
            @forelse ($questions as $question)
                <div class="list-item">
                    <a href="{{ route('questions.show', $question['slug']) }}">{{ $question['title'] }}</a>
                </div>
            @empty
                <div class="profile-empty">
                    <p>{{ $is_owner ? __('ui.profile.no_questions_owner') : __('ui.profile.no_questions') }}</p>
                    @if ($is_owner && !$isBanned)
                        <a class="ghost-btn" href="{{ route('publish') }}">{{ __('ui.profile.ask_question') }}</a>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    <section class="section profile-section tab-panel" data-tab-panel="comments" hidden>
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
                <div class="profile-empty">
                    <p>{{ __('ui.profile.no_comments') }}</p>
                </div>
            @endforelse
        </div>
    </section>
    </div>
@endsection
