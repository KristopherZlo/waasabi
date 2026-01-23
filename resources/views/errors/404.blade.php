@extends('layouts.app')

@section('title', __('ui.errors.not_found_title'))
@section('page', 'not-found')

@section('content')
<div class="not-found" data-not-found data-not-found-star-src="{{ asset('images/star.svg') }}" aria-label="{{ __('ui.errors.not_found_title') }}">
    <div class="not-found__note" data-not-found-note data-not-found-note-win="{{ __('ui.errors.not_found_note_win') }}">
        {{ __('ui.errors.not_found_note') }}
    </div>
    <div class="not-found__score" data-not-found-score data-not-found-base="{{ __('ui.errors.not_found_code') }}">
        {{ __('ui.errors.not_found_code') }}
    </div>
    <button class="not-found__retry" type="button" data-not-found-retry>
        <i data-lucide="rotate-ccw" class="icon" aria-hidden="true"></i>
        {{ __('ui.errors.not_found_retry') }}
    </button>
    <div class="not-found__stage" aria-hidden="true">
        <div class="not-found__stars" data-not-found-stars></div>
        <div class="not-found__orbit" data-not-found-orbit>
            <img class="not-found__box" src="{{ asset('images/box.svg') }}" alt="" aria-hidden="true" data-not-found-box>
        </div>
        <div class="not-found__planet-wrap">
            <img class="not-found__planet" src="{{ asset('images/earth.svg') }}" alt="" aria-hidden="true">
        </div>
    </div>
</div>
@endsection
