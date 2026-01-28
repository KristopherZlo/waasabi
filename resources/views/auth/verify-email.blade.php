@extends('layouts.app')

@section('title', __('ui.auth.verify_title'))
@section('page', 'verify-email')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.auth.verify_headline') }}</h1>
        <p>{{ __('ui.auth.verify_subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="card auth-card">
            <div>
                <div class="section-title">{{ __('ui.auth.verify_notice_title') }}</div>
                <div class="helper">{{ __('ui.auth.verify_notice_body') }}</div>
            </div>
            @if ($errors->any())
                <div class="form-error">{{ $errors->first() }}</div>
            @endif
            <form class="auth-form" method="POST" action="{{ route('verification.send') }}">
                @csrf
                <div class="honeypot-field" aria-hidden="true">
                    <label>
                        <span>Website</span>
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>
                @if (config('waasabi.captcha.enabled') && config('waasabi.captcha.actions.verification') && config('waasabi.captcha.site_key'))
                    <div class="captcha-field">
                        <div class="cf-turnstile" data-sitekey="{{ config('waasabi.captcha.site_key') }}"></div>
                    </div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                @endif
                <button class="submit-btn" type="submit">{{ __('ui.auth.verify_resend') }}</button>
            </form>
            <div class="helper">{{ __('ui.auth.verify_hint') }}</div>
        </div>
    </section>
@endsection
