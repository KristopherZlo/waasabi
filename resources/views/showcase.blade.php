@extends('layouts.app')

@section('title', __('ui.showcase.title'))
@section('page', 'showcase')

@section('content')
    <section class="hero">
        <h1>{{ __('ui.showcase.title') }}</h1>
        <p>{{ __('ui.showcase.subtitle') }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        @foreach ($showcase as $collection)
            <div class="section" style="margin-bottom: 20px;">
                <div class="section-title">{{ $collection['title'] }}</div>
                <div class="list">
                    @foreach ($collection['projects'] as $project)
                        @include('partials.project-card', ['project' => $project])
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
@endsection
