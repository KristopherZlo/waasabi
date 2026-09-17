@extends('layouts.app')

@section('title', __('ui.auth.register_title'))
@section('robots', 'noindex, nofollow')
@section('page', 'register')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.auth.register_headline') }}</h1>
        <p>{{ __('ui.auth.register_subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="card auth-card">
            @if ($errors->any())
                <div class="form-error">{{ $errors->first() }}</div>
            @endif
            <form class="auth-form" method="POST" action="{{ route('register.store') }}">
                @csrf
                <div class="honeypot-field" aria-hidden="true">
                    <label>
                        <span>{{ __('ui.auth.website') }}</span>
                        <input type="text" name="contact_time" tabindex="-1" autocomplete="off">
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
                        {{ __('ui.auth.legal_agree') }} <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer">{{ __('ui.footer.terms') }}</a>
                        {{ __('ui.auth.legal_acknowledge') }} <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('ui.footer.privacy') }}</a>.
                    </span>
                </label>
                @if (config('hub.captcha.enabled') && config('hub.captcha.actions.register') && config('hub.captcha.site_key'))
                    <div class="captcha-field">
                        <div class="cf-turnstile" data-sitekey="{{ config('hub.captcha.site_key') }}"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif
                <button class="submit-btn" type="submit">{{ __('ui.auth.create_account') }}</button>
            </form>
            <div class="helper">{{ __('ui.auth.register_have_account') }} <a href="{{ route('login') }}">{{ __('ui.auth.register_sign_in') }}</a></div>
        </div>
    </section>
@endsection
