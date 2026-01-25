@extends('layouts.app')

@section('title', __('ui.read_later.title'))
@section('page', 'read-later')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.read_later.title') }}</h1>
        <p>{{ __('ui.read_later.subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <div class="list" data-read-later-page-list>
            @foreach ($items as $item)
                @if (($item['type'] ?? 'project') === 'question')
                    @include('partials.question-card', ['question' => $item['data'] ?? []])
                @else
                    @include('partials.project-card', ['project' => $item['data'] ?? []])
                @endif
            @endforeach
            <div class="list-item" data-read-later-page-empty hidden>{{ __('ui.read_later.empty') }}</div>
        </div>
    </section>
@endsection
