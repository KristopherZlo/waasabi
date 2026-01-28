@extends('layouts.app')

@section('title', __('ui.auth.register_title'))
@section('page', 'register')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.auth.register_headline') }}</h1>
        <p>{{ __('ui.auth.register_subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="card auth-card">
            <div>
                <div class="section-title">{{ __('ui.auth.register_provider_title') }}</div>
                <div class="helper">{{ __('ui.auth.register_provider_helper') }}</div>
            </div>
            <div class="auth-grid">
                <a class="provider-btn provider-btn--google" href="#" data-auth="Google">
                    <span>{{ __('ui.auth.register_google') }}</span>
                    <span class="helper">{{ __('ui.auth.register_google_helper') }}</span>
                </a>
                <a class="provider-btn provider-btn--discord" href="#" data-auth="Discord">
                    <span>{{ __('ui.auth.register_discord') }}</span>
                    <span class="helper">{{ __('ui.auth.register_discord_helper') }}</span>
                </a>
            </div>
            <div class="auth-divider"><span>{{ __('ui.auth.email_divider_register') }}</span></div>
            @if ($errors->any())
                <div class="form-error">{{ $errors->first() }}</div>
            @endif
            <form class="auth-form" method="POST" action="{{ route('register.store') }}">
                @csrf
                <div class="honeypot-field" aria-hidden="true">
                    <label>
                        <span>Website</span>
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>
                <label>
                    <span>{{ __('ui.auth.name') }}</span>
                    <input class="input" type="text" name="name" value="{{ old('name') }}" required autocomplete="name">
                </label>
                <label>
                    <span>{{ __('ui.auth.email') }}</span>
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <label>
                    <span>{{ __('ui.auth.password') }}</span>
                    <input class="input" type="password" name="password" required autocomplete="new-password">
                </label>
                <label>
                    <span>{{ __('ui.auth.password_confirm') }}</span>
                    <input class="input" type="password" name="password_confirmation" required autocomplete="new-password">
                </label>
                <label class="legal-check">
                    <input type="checkbox" name="accept_legal" value="1" required @checked(old('accept_legal'))>
                    <span>
                        I agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                        and acknowledge the <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                    </span>
                </label>
                @if (config('waasabi.captcha.enabled') && config('waasabi.captcha.actions.register') && config('waasabi.captcha.site_key'))
                    <div class="captcha-field">
                        <div class="cf-turnstile" data-sitekey="{{ config('waasabi.captcha.site_key') }}"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif
                <button class="submit-btn" type="submit">{{ __('ui.auth.create_account') }}</button>
            </form>
            <div class="helper">{{ __('ui.auth.register_have_account') }} <a href="{{ route('login') }}">{{ __('ui.auth.register_sign_in') }}</a></div>
        </div>
    </section>
@endsection
