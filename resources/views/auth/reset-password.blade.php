@extends('layouts.app')

@section('title', __('ui.auth.reset_title'))
@section('robots', 'noindex, nofollow')
@section('page', 'reset-password')

@section('content')
    <section class="section auth-section">
        <div class="card auth-card">
            <h1 class="section-title">{{ __('ui.auth.reset_title') }}</h1>
            @if ($errors->any()) <div class="form-error" role="alert">{{ $errors->first() }}</div> @endif
            <form class="auth-form" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label><span>{{ __('ui.auth.email') }}</span><input class="input" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
                <label><span>{{ __('ui.auth.new_password') }}</span><input class="input" type="password" name="password" required autocomplete="new-password"></label>
                <label><span>{{ __('ui.auth.password_confirm') }}</span><input class="input" type="password" name="password_confirmation" required autocomplete="new-password"></label>
                <button class="submit-btn" type="submit">{{ __('ui.auth.reset_password') }}</button>
            </form>
        </div>
    </section>
@endsection
