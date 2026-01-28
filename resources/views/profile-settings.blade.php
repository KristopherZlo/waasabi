@extends('layouts.app')

@section('title', __('ui.profile_settings.title'))
@section('page', 'profile-settings')

@section('content')
    @php
        $userName = $user?->name ?? $current_user['name'] ?? '';
        $userEmail = $user?->email ?? '';
        $userBio = $user?->bio ?? '';
        $userRole = $user?->roleKey() ?? 'user';
        $avatarValue = $user?->avatar ?? '';
        $profileSlug = $user?->slug ?? $current_user['slug'] ?? \Illuminate\Support\Str::slug($userName ?: 'user');
        $avatarPath = $avatarValue ?: 'images/avatar-default.svg';
        $avatarIsDefault = trim($avatarPath, '/') === 'images/avatar-default.svg';
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : asset(ltrim($avatarPath, '/'));
        $privacyShareActivity = old('privacy_share_activity', $user?->privacy_share_activity ?? true);
        $privacyAllowMentions = old('privacy_allow_mentions', $user?->privacy_allow_mentions ?? true);
        $privacyPersonalized = old(
            'privacy_personalized_recommendations',
            $user?->privacy_personalized_recommendations ?? false,
        );
        $notifyComments = old('notify_comments', $user?->notify_comments ?? true);
        $notifyReviews = old('notify_reviews', $user?->notify_reviews ?? true);
        $notifyFollows = old('notify_follows', $user?->notify_follows ?? true);
        $connectionsAllowFollow = old('connections_allow_follow', $user?->connections_allow_follow ?? true);
        $connectionsShowCounts = old('connections_show_follow_counts', $user?->connections_show_follow_counts ?? true);
        $securityLoginAlerts = old('security_login_alerts', $user?->security_login_alerts ?? true);
    @endphp

    <section class="settings-shell" data-settings-root>
        <aside class="settings-sidebar">
            <div class="settings-user">
                <img class="avatar avatar--lg" src="{{ $avatarUrl }}" alt="{{ $userName }}" @if ($avatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $userName }}" @endif>
                <div class="settings-user__info">
                    <div class="settings-user__name">{{ $userName }}</div>
                    <div class="settings-user__meta">
                        <span class="badge badge--{{ $userRole }}">{{ __('ui.roles.' . $userRole) }}</span>
                        <span class="settings-user__email">{{ $userEmail }}</span>
                    </div>
                </div>
            </div>
            <label class="settings-search">
                <i data-lucide="search" class="icon"></i>
                <input type="text" placeholder="{{ __('ui.profile_settings.search_placeholder') }}" data-settings-search>
            </label>
            <div class="settings-group">
                <div class="settings-group__title">{{ __('ui.profile_settings.nav_user') }}</div>
                <div class="settings-group__list">
                    <button class="settings-nav is-active" type="button" data-settings-nav="account" aria-controls="settings-account">{{ __('ui.profile_settings.nav_account') }}</button>
                    <button class="settings-nav" type="button" data-settings-nav="profile" aria-controls="settings-profile">{{ __('ui.profile_settings.nav_profile') }}</button>
                    <button class="settings-nav" type="button" data-settings-nav="privacy" aria-controls="settings-privacy">{{ __('ui.profile_settings.nav_privacy') }}</button>
                </div>
            </div>
            <div class="settings-group">
                <div class="settings-group__title">{{ __('ui.profile_settings.nav_app') }}</div>
                <div class="settings-group__list">
                    <button class="settings-nav" type="button" data-settings-nav="notifications" aria-controls="settings-notifications">{{ __('ui.profile_settings.nav_notifications') }}</button>
                    <button class="settings-nav" type="button" data-settings-nav="connections" aria-controls="settings-connections">{{ __('ui.profile_settings.nav_connections') }}</button>
                    <button class="settings-nav" type="button" data-settings-nav="devices" aria-controls="settings-devices">{{ __('ui.profile_settings.nav_devices') }}</button>
                    <button class="settings-nav" type="button" data-settings-nav="support" aria-controls="settings-support">{{ __('ui.profile_settings.nav_support') }}</button>
                </div>
            </div>
        </aside>

        <div class="settings-content">
            <div class="settings-topbar">
                <div>
                    <div class="settings-topbar__eyebrow">{{ __('ui.profile_settings.title') }}</div>
                    <h1 class="settings-topbar__title" data-settings-title>{{ __('ui.profile_settings.account_title') }}</h1>
                </div>
                <a class="icon-btn settings-topbar__close" href="{{ route('profile.show', $profileSlug) }}" aria-label="{{ __('ui.profile_settings.view_profile') }}">
                    <i data-lucide="x" class="icon"></i>
                </a>
            </div>

            <form class="settings-form profile-settings" method="POST" action="{{ route('profile.settings.update') }}" enctype="multipart/form-data" data-profile-settings-form data-profile-settings-live="1">
                @csrf
                <div class="settings-panel" id="settings-account" data-settings-section="account">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.account_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.account_hint') }}</div>
                        </div>
                        <a class="ghost-btn" href="{{ route('profile.show', $profileSlug) }}">{{ __('ui.profile_settings.view_profile') }}</a>
                    </div>
                    <div class="settings-account">
                        <img class="avatar avatar--xl" src="{{ $avatarUrl }}" alt="{{ $userName }}" @if ($avatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $userName }}" @endif>
                        <div class="settings-account__details">
                            <div class="settings-account__name">{{ $userName }}</div>
                            <div class="settings-account__email">{{ $userEmail }}</div>
                            <div class="settings-account__meta">
                                <span class="badge badge--{{ $userRole }}">{{ __('ui.roles.' . $userRole) }}</span>
                                @if (!empty($user?->id))
                                    <span class="helper">{{ __('ui.profile.id_label') }} {{ $user->id }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-panel" id="settings-profile" data-settings-section="profile">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.profile_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.profile_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-row settings-row--info">
                        <div class="settings-row__label">
                            <span>{{ __('ui.profile_settings.profile_media_title') }}</span>
                        </div>
                        <div class="settings-row__control">
                            <div class="settings-note">{{ __('ui.profile_settings.profile_media_hint') }}</div>
                            <a class="ghost-btn ghost-btn--compact" href="{{ route('profile.show', $profileSlug) }}">{{ __('ui.profile_settings.profile_media_cta') }}</a>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row__label">
                            <label for="settings-name">{{ __('ui.profile_settings.name') }}</label>
                        </div>
                        <div class="settings-row__control">
                            <input id="settings-name" class="input" type="text" name="name" value="{{ old('name', $userName) }}">
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row__label">
                            <label for="settings-email">{{ __('ui.profile_settings.email') }}</label>
                        </div>
                        <div class="settings-row__control">
                            <input id="settings-email" class="input" type="email" value="{{ $userEmail }}" disabled>
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-row__label">
                            <label for="settings-bio">{{ __('ui.profile_settings.bio') }}</label>
                        </div>
                        <div class="settings-row__control">
                            <textarea id="settings-bio" class="input" name="bio">{{ old('bio', $userBio) }}</textarea>
                        </div>
                    </div>
                    @if ($user?->isAdmin())
                        <div class="settings-row settings-row--info">
                            <div class="settings-row__label">
                                <span>{{ __('ui.profile_settings.role') }}</span>
                            </div>
                            <div class="settings-row__control">
                                <span class="badge badge--{{ $userRole }}">{{ __('ui.roles.' . $userRole) }}</span>
                                <div class="settings-note">{{ __('ui.profile_settings.role_admin_hint') }}</div>
                            </div>
                        </div>
                    @else
                        <div class="settings-row">
                            <div class="settings-row__label">
                                <span>{{ __('ui.profile_settings.role') }}</span>
                            </div>
                            <div class="settings-row__control">
                                <span class="badge badge--{{ $userRole }}">{{ __('ui.roles.' . $userRole) }}</span>
                            </div>
                        </div>
                    @endif
                    <div class="settings-actions">
                        <button type="submit" class="submit-btn" name="section" value="profile">{{ __('ui.profile_settings.save') }}</button>
                    </div>
                </div>

                <div class="settings-panel" id="settings-privacy" data-settings-section="privacy">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.privacy_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.privacy_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.privacy_activity_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.privacy_activity_desc') }}</div>
                        </div>
                        <input type="hidden" name="privacy_share_activity" value="0">
                        <label class="switch">
                            <input type="checkbox" name="privacy_share_activity" value="1" @checked($privacyShareActivity)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.privacy_mentions_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.privacy_mentions_desc') }}</div>
                        </div>
                        <input type="hidden" name="privacy_allow_mentions" value="0">
                        <label class="switch">
                            <input type="checkbox" name="privacy_allow_mentions" value="1" @checked($privacyAllowMentions)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.privacy_recommendations_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.privacy_recommendations_desc') }}</div>
                        </div>
                        <input type="hidden" name="privacy_personalized_recommendations" value="0">
                        <label class="switch">
                            <input type="checkbox" name="privacy_personalized_recommendations" value="1" @checked($privacyPersonalized)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="submit-btn" name="section" value="privacy">{{ __('ui.profile_settings.save') }}</button>
                    </div>
                </div>

                <div class="settings-panel" id="settings-notifications" data-settings-section="notifications">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.notifications_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.notifications_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.notifications_comments_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.notifications_comments_desc') }}</div>
                        </div>
                        <input type="hidden" name="notify_comments" value="0">
                        <label class="switch">
                            <input type="checkbox" name="notify_comments" value="1" @checked($notifyComments)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.notifications_reviews_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.notifications_reviews_desc') }}</div>
                        </div>
                        <input type="hidden" name="notify_reviews" value="0">
                        <label class="switch">
                            <input type="checkbox" name="notify_reviews" value="1" @checked($notifyReviews)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.notifications_follows_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.notifications_follows_desc') }}</div>
                        </div>
                        <input type="hidden" name="notify_follows" value="0">
                        <label class="switch">
                            <input type="checkbox" name="notify_follows" value="1" @checked($notifyFollows)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="submit-btn" name="section" value="notifications">{{ __('ui.profile_settings.save') }}</button>
                    </div>
                </div>

                <div class="settings-panel" id="settings-connections" data-settings-section="connections">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.connections_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.connections_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.connections_allow_follow_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.connections_allow_follow_desc') }}</div>
                        </div>
                        <input type="hidden" name="connections_allow_follow" value="0">
                        <label class="switch">
                            <input type="checkbox" name="connections_allow_follow" value="1" @checked($connectionsAllowFollow)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.connections_show_counts_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.connections_show_counts_desc') }}</div>
                        </div>
                        <input type="hidden" name="connections_show_follow_counts" value="0">
                        <label class="switch">
                            <input type="checkbox" name="connections_show_follow_counts" value="1" @checked($connectionsShowCounts)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="submit-btn" name="section" value="connections">{{ __('ui.profile_settings.save') }}</button>
                    </div>
                </div>

                <div class="settings-panel" id="settings-devices" data-settings-section="devices">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.devices_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.devices_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-toggle">
                        <div>
                            <div class="settings-toggle__title">{{ __('ui.profile_settings.devices_login_alerts_title') }}</div>
                            <div class="settings-toggle__desc">{{ __('ui.profile_settings.devices_login_alerts_desc') }}</div>
                        </div>
                        <input type="hidden" name="security_login_alerts" value="0">
                        <label class="switch">
                            <input type="checkbox" name="security_login_alerts" value="1" @checked($securityLoginAlerts)>
                            <span class="switch__track"></span>
                        </label>
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="submit-btn" name="section" value="devices">{{ __('ui.profile_settings.save') }}</button>
                    </div>
                </div>

                <div class="settings-panel" id="settings-support" data-settings-section="support">
                    <div class="settings-panel__header">
                        <div>
                            <div class="settings-panel__title">{{ __('ui.profile_settings.support_title') }}</div>
                            <div class="settings-panel__subtitle">{{ __('ui.profile_settings.support_hint') }}</div>
                        </div>
                    </div>
                    <div class="settings-row settings-row--info">
                        <div class="settings-row__label">
                            <span>{{ __('ui.support.title') }}</span>
                        </div>
                        <div class="settings-row__control">
                            <div class="settings-note">{{ __('ui.profile_settings.support_note') }}</div>
                            <a class="ghost-btn ghost-btn--compact" href="{{ route('support') }}">{{ __('ui.profile_settings.support_cta') }}</a>
                        </div>
                    </div>
                </div>

                <div class="settings-empty helper" data-settings-empty hidden>{{ __('ui.profile_settings.search_empty') }}</div>
            </form>
        </div>
    </section>
@endsection
