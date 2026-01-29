@extends('layouts.app')

@section('title', __('ui.collaboration.title'))
@section('page', 'collaboration')

@section('content')
    <div data-collaboration-page>
        <section class="hero collaboration-hero">
        <h1>{{ __('ui.collaboration.title') }}</h1>
        <p>{{ __('ui.collaboration.subtitle') }}</p>
        <div class="collaboration-hero__actions">
            <a class="cta-btn" href="#collaboration-form">
                <i data-lucide="users" class="icon"></i>
                <span>{{ __('ui.collaboration.cta') }}</span>
            </a>
            <a class="ghost-btn ghost-btn--compact" href="{{ route('feed', ['stream' => 'collaboration']) }}">
                {{ __('ui.collaboration.view_feed') }}
            </a>
        </div>
        </section>

    <section class="section" style="margin-top: 18px;">
        <div class="card collaboration-filters" data-collaboration-filters>
            <label>
                <span class="label-text">{{ __('ui.collaboration.filter_role') }}</span>
                <select class="input" data-collaboration-filter="role">
                    <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                    @foreach (($collaboration_roles ?? []) as $roleKey => $roleLabel)
                        <option value="{{ $roleKey }}">{{ $roleLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label-text">{{ __('ui.collaboration.filter_availability') }}</span>
                <select class="input" data-collaboration-filter="availability">
                    <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                    @foreach (($collaboration_availability ?? []) as $availabilityKey => $availabilityLabel)
                        <option value="{{ $availabilityKey }}">{{ $availabilityLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label-text">{{ __('ui.collaboration.filter_format') }}</span>
                <select class="input" data-collaboration-filter="format">
                    <option value="">{{ __('ui.collaboration.filter_any') }}</option>
                    @foreach (($collaboration_formats ?? []) as $formatKey => $formatLabel)
                        <option value="{{ $formatKey }}">{{ $formatLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="collaboration-filters__search">
                <span class="label-text">{{ __('ui.collaboration.filter_search') }}</span>
                <input class="input" type="search" placeholder="{{ __('ui.collaboration.filter_search_placeholder') }}" data-collaboration-search>
            </label>
        </div>
    </section>

    <section class="section" style="margin-top: 18px;">
        <div class="list" data-collaboration-list>
            @php
                $hasCollaborationItems = !empty($collaboration_items ?? []);
            @endphp
            @foreach (($collaboration_items ?? []) as $item)
                @include('partials.feed-item', ['item' => $item])
            @endforeach
            <div class="list-item" data-collaboration-empty @if ($hasCollaborationItems) hidden @endif>
                {{ __('ui.collaboration.empty') }}
            </div>
        </div>
    </section>

        <section class="section" id="collaboration-form" style="margin-top: 28px;">
        <div class="card collaboration-form">
            <div class="section-title">{{ __('ui.collaboration.form_title') }}</div>
            <div class="helper">{{ __('ui.collaboration.form_helper') }}</div>

            @if (!Auth::check())
                <div class="callout">
                    <p>{{ __('ui.collaboration.auth_required') }}</p>
                    <a class="cta-btn" href="{{ route('login') }}">{{ __('ui.nav.login') }}</a>
                </div>
            @else
                @if ($errors->any())
                    <div class="form-error">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('collaboration.store') }}">
                    @csrf
                    <div class="honeypot-field" aria-hidden="true">
                        <label>
                            <span>Website</span>
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </label>
                    </div>
                    <label>
                        <span class="label-text">{{ __('ui.collaboration.form_title_label') }}</span>
                        <input class="input" type="text" name="title" value="{{ old('title') }}" maxlength="120" required>
                    </label>
                    <div class="collaboration-form__grid">
                        <label>
                            <span class="label-text">{{ __('ui.collaboration.form_role') }}</span>
                            <select class="input" name="role" required>
                                @foreach (($collaboration_roles ?? []) as $roleKey => $roleLabel)
                                    <option value="{{ $roleKey }}" @selected(old('role') === $roleKey)>{{ $roleLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="label-text">{{ __('ui.collaboration.form_availability') }}</span>
                            <select class="input" name="availability" required>
                                @foreach (($collaboration_availability ?? []) as $availabilityKey => $availabilityLabel)
                                    <option value="{{ $availabilityKey }}" @selected(old('availability') === $availabilityKey)>{{ $availabilityLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="label-text">{{ __('ui.collaboration.form_format') }}</span>
                            <select class="input" name="format" required>
                                @foreach (($collaboration_formats ?? []) as $formatKey => $formatLabel)
                                    <option value="{{ $formatKey }}" @selected(old('format') === $formatKey)>{{ $formatLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span class="label-text">{{ __('ui.collaboration.form_skills') }}</span>
                            <input class="input" type="text" name="skills" value="{{ old('skills') }}" placeholder="{{ __('ui.collaboration.form_skills_placeholder') }}">
                        </label>
                    </div>
                    <label>
                        <span class="label-text">{{ __('ui.collaboration.form_summary') }}</span>
                        <textarea class="input" name="summary" rows="6" required>{{ old('summary') }}</textarea>
                    </label>
                    <label>
                        <span class="label-text">{{ __('ui.collaboration.form_contact') }}</span>
                        <input class="input" type="text" name="contact" value="{{ old('contact') }}" placeholder="{{ __('ui.collaboration.form_contact_placeholder') }}">
                    </label>
                    <button class="cta-btn" type="submit">{{ __('ui.collaboration.form_submit') }}</button>
                </form>
            @endif
        </div>
        </section>
    </div>
@endsection
