@extends('layouts.app')
@section('title', __('waasabi.people'))
@section('page', 'people')
@section('content')
    <header class="community-heading">
        <div><h1>{{ __('waasabi.people') }}</h1><p>{{ __('waasabi.people_detail') }}</p></div>
        <a class="cta-btn" href="{{ route('collaboration.create') }}">{{ __('waasabi.help') }}</a>
    </header>
    <form class="community-search" method="GET">
        <label for="people-query">{{ __('waasabi.search_people') }}</label>
        <input class="input" id="people-query" name="q" maxlength="80" value="{{ request('q') }}">
        <button class="ghost-btn">{{ __('waasabi.search') }}</button>
    </form>
    <div class="people-grid">
        @forelse ($people as $person)
            <article class="person-card">
                <img class="avatar" src="{{ $person->avatar ?: asset('images/avatar-default.svg') }}" alt="" @if(!$person->avatar) data-avatar-auto="1" data-avatar-name="{{ $person->name }}" @endif>
                <h2><a href="{{ route('profile.show', $person->slug) }}">{{ $person->name }}</a></h2>
                <p>{{ $person->skills }}</p>
                <p class="helper">{{ \Illuminate\Support\Str::limit($person->bio, 180) }}</p>
                <a href="{{ route('profile.show', $person->slug) }}">{{ __('ui.nav.profile') }} →</a>
            </article>
        @empty
            <p>{{ __('waasabi.people_empty') }}</p>
        @endforelse
    </div>
    {{ $people->links() }}
@endsection
