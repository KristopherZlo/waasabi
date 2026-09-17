@extends('layouts.app')

@section('title', __('ui.auth.forgot_title'))
@section('robots', 'noindex, nofollow')
@section('page', 'forgot-password')

@section('content')
    <section class="section auth-section">
        <div class="card auth-card">
            <h1 class="section-title">{{ __('ui.auth.forgot_title') }}</h1>
            <p class="helper">{{ __('ui.auth.forgot_hint') }}</p>
            @if (session('status')) <div class="form-success" role="status">{{ session('status') }}</div> @endif
            @if ($errors->any()) <div class="form-error" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label><span>{{ __('ui.auth.email') }}</span><input class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></label>
                <button class="submit-btn" type="submit">{{ __('ui.auth.send_reset_link') }}</button>
            </form>
        </div>
    </section>
@endsection
