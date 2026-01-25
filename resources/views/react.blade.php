@extends('layouts.app')

@section('title', __('ui.react.title'))
@section('page', 'react')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.react.headline') }}</h1>
        <p>{{ __('ui.react.subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="card">
            <div class="section-title">{{ __('ui.react.choose_title') }}</div>
            <p class="helper">{{ __('ui.react.choose_subtitle') }}</p>
            @include('partials.react-gate')
            <a class="ghost-btn" href="{{ route('feed') }}">{{ __('ui.react.skip') }}</a>
        </div>
    </section>
@endsection
