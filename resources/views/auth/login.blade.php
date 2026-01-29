@extends('layouts.app')

@section('title', __('ui.auth.login_title'))
@section('page', 'login')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.auth.login_headline') }}</h1>
        <p>{{ __('ui.auth.login_subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="card auth-card">
            <div>
                <div class="section-title">{{ __('ui.auth.login_provider_title') }}</div>
                <div class="helper">{{ __('ui.auth.login_provider_helper') }}</div>
            </div>
            <div class="auth-grid">
                <a class="provider-btn provider-btn--google" href="#" data-auth="Google">
                    <span>{{ __('ui.auth.login_google') }}</span>
                    <span class="helper">{{ __('ui.auth.login_google_helper') }}</span>
                </a>
                <a class="provider-btn provider-btn--discord" href="#" data-auth="Discord">
                    <span>{{ __('ui.auth.login_discord') }}</span>
                    <span class="helper">{{ __('ui.auth.login_discord_helper') }}</span>
                </a>
            </div>
            <div class="auth-divider"><span>{{ __('ui.auth.email_divider_login') }}</span></div>
            @if ($errors->any())
                <div class="form-error">{{ $errors->first() }}</div>
            @endif
            <form class="auth-form" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="honeypot-field" aria-hidden="true">
                    <label>
                        <span>Website</span>
                        <input type="text" name="contact_time" tabindex="-1" autocomplete="off">
                    </label>
                </div>
                <label>
                    <span>{{ __('ui.auth.email') }}</span>
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <label>
                    <span>{{ __('ui.auth.password') }}</span>
                    <input class="input" type="password" name="password" required autocomplete="current-password">
                </label>
                @if (config('waasabi.captcha.enabled') && config('waasabi.captcha.actions.login') && config('waasabi.captcha.site_key'))
                    <div class="captcha-field">
                        <div class="cf-turnstile" data-sitekey="{{ config('waasabi.captcha.site_key') }}"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif
                <button class="submit-btn" type="submit">{{ __('ui.auth.sign_in') }}</button>
            </form>
            <div class="helper">{{ __('ui.auth.login_no_account') }} <a href="{{ route('register') }}">{{ __('ui.auth.login_create') }}</a></div>
        </div>
    </section>
@endsection
