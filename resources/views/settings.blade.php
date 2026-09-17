@extends('layouts.app')

@section('title', __('ui.settings.title'))
@section('robots', 'noindex, nofollow')
@section('page', 'settings')

@section('content')
    <section class="device-settings" data-device-settings>
        <header class="device-settings__header">
            <p class="device-settings__eyebrow">{{ __('ui.topbar.settings') }}</p>
            <h1>{{ __('ui.settings.title') }}</h1>
            <p>{{ __('ui.settings.subtitle') }}</p>
        </header>

        <div class="device-settings__rows">
            <section class="device-settings__row">
                <div class="device-settings__copy">
                    <h2>{{ __('ui.settings.language') }}</h2>
                    <p>{{ __('ui.settings.language_hint') }}</p>
                </div>
                <div class="settings-locale" aria-label="{{ __('ui.settings.language') }}">
                    <a class="settings-option {{ app()->getLocale() === 'en' ? 'is-active' : '' }}" href="{{ route('locale', 'en') }}">English</a>
                    <a class="settings-option {{ app()->getLocale() === 'fi' ? 'is-active' : '' }}" href="{{ route('locale', 'fi') }}">Suomi</a>
                </div>
            </section>

            <section class="device-settings__row">
                <div class="device-settings__copy">
                    <h2>{{ __('ui.settings.publications') }}</h2>
                    <p>{{ __('ui.settings.publications_hint') }}</p>
                </div>
                <div class="settings-stack">
                    <label class="settings-option"><input type="checkbox" data-setting="publications" value="en">English</label>
                    <label class="settings-option"><input type="checkbox" data-setting="publications" value="fi">Suomi</label>
                </div>
            </section>

            <section class="device-settings__row">
                <div class="device-settings__copy">
                    <h2>{{ __('ui.settings.feed_view') }}</h2>
                    <p>{{ __('ui.settings.feed_view_hint') }}</p>
                </div>
                <div class="settings-stack">
                    <label class="settings-option"><input type="radio" name="feed-view" data-setting="feed_view" value="classic">{{ __('ui.settings.feed_classic') }}</label>
                    <label class="settings-option"><input type="radio" name="feed-view" data-setting="feed_view" value="compact">{{ __('ui.settings.feed_compact') }}</label>
                </div>
            </section>

            <section class="device-settings__row">
                <div class="device-settings__copy">
                    <h2>{{ __('ui.settings.theme') }}</h2>
                    <p>{{ __('ui.settings.theme_hint') }}</p>
                </div>
                <div class="settings-stack">
                    <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="dark">{{ __('ui.settings.theme_dark') }}</label>
                    <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="light">{{ __('ui.settings.theme_light') }}</label>
                    <label class="settings-option"><input type="radio" name="theme" data-setting="theme" value="system">{{ __('ui.settings.theme_system') }}</label>
                </div>
            </section>
        </div>

        <footer class="device-settings__footer">
            <div class="device-settings__links">
                @auth
                    <a href="{{ route('profile.settings') }}">{{ __('ui.settings.account') }}</a>
                @endauth
                <a href="{{ route('support') }}">{{ __('ui.profile_settings.support_cta') }}</a>
            </div>
            <button type="button" class="submit-btn" data-settings-save>{{ __('ui.settings.save') }}</button>
        </footer>
    </section>
@endsection
