@extends('layouts.static')

@section('title', __('ui.errors.not_found_title'))
@section('page', 'not-found')
@section('robots', 'noindex, follow')

@section('content')
<section class="not-found-page" aria-labelledby="not-found-title">
    <span class="not-found-code">404</span>
    <h1 id="not-found-title">{{ __('ui.errors.not_found_title') }}</h1>
    <p>{{ __('ui.errors.not_found_note') }}</p>
    <a class="text-link" href="{{ route('feed') }}">{{ __('ui.errors.not_found_home') }} →</a>
</section>
@endsection
