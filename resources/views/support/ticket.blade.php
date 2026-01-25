@extends('layouts.support')

@section('title', __('ui.support.title_full'))
@section('page', 'support-ticket')

@section('content')
    <div class="support-portal">
        <section class="support-tab-panel is-active">
            <div class="support-section">
                <div class="support-section__header">
                    <h2>{{ __('ui.support.portal_nav_new') }}</h2>
                </div>
                @include('support.partials.ticket-form')
            </div>
        </section>
    </div>
@endsection
