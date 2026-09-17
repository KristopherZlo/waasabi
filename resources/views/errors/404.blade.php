@extends('layouts.app')

@section('title', __('ui.errors.not_found_title'))
@section('page', 'not-found')

@section('content')
<section
    class="not-found-page"
    data-not-found-game
    data-win-text="{{ __('ui.errors.not_found_win') }}"
    aria-labelledby="not-found-title"
>
    <canvas class="not-found-game__sky" data-game-sky aria-hidden="true"></canvas>
    <div class="not-found-game__score" data-game-score data-score="{{ __('ui.errors.not_found_code') }}" aria-hidden="true">
        <span data-game-digit>4</span><span data-game-digit>0</span><span data-game-digit>4</span>
    </div>

    <div class="not-found-page__body">
        <h1 id="not-found-title" data-game-title>{{ __('ui.errors.not_found_note') }}</h1>
        <p>{{ __('ui.errors.not_found_note') }}</p>
        <a class="primary-cta" href="{{ route('feed') }}">{{ __('ui.errors.not_found_home') }}</a>
    </div>

    <div
        class="not-found-game__stage"
        data-game-stage
        tabindex="0"
        role="application"
        aria-label="{{ __('ui.errors.not_found_game_label') }}"
    >
        <div class="not-found-game__orbit-position" data-game-orbit aria-hidden="true">
            <div class="not-found-game__orbit"></div>
        </div>

        <div class="not-found-game__planet-position" data-game-planet aria-hidden="true">
            <div class="not-found-game__planet-effect">
                <img class="not-found-game__planet" src="{{ asset('images/earth.svg') }}" alt="">
            </div>
        </div>

        <div class="not-found-game__catcher-position" data-game-catcher aria-hidden="true">
            <div class="not-found-game__catcher-effect">
                <img class="not-found-game__catcher" src="{{ asset('images/box.svg') }}" alt="">
            </div>
        </div>

        <img class="not-found-game__star" data-game-star src="{{ asset('images/star.svg') }}" alt="" aria-hidden="true">
    </div>

    <button class="not-found-game__retry" type="button" data-game-retry hidden><span aria-hidden="true">↻</span>{{ __('ui.errors.not_found_retry') }}</button>
    <p class="sr-only" aria-live="polite" data-game-status>{{ __('ui.errors.not_found_game_started') }}</p>
    <p class="sr-only">{{ __('ui.errors.not_found_game_controls') }}</p>
</section>
@endsection
