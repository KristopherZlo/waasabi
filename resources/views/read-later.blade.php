@extends('layouts.app')

@section('title', __('ui.read_later.title'))
@section('robots', 'noindex, nofollow')
@section('page', 'read-later')

@section('content')
    <section class="saved-library">
        <header class="saved-library__header">
            <div>
                <h1>{{ __('ui.read_later.title') }}</h1>
                <p>{{ __('ui.read_later.subtitle') }}</p>
            </div>
            <span class="saved-library__count" data-read-later-page-count>{{ count($items) }}</span>
        </header>
        <div class="saved-library__list" data-read-later-page-list>
            @foreach ($items as $item)
                @include('partials.read-later-item', ['item' => $item])
            @endforeach
            <div class="saved-library__empty" data-read-later-page-empty @if (count($items) > 0) hidden @endif>{{ __('ui.read_later.empty') }}</div>
        </div>
    </section>
@endsection
