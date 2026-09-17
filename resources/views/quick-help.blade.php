@extends('layouts.app')
@section('title', __('waasabi.help'))
@section('robots', 'noindex, nofollow')
@section('page', 'collaboration-create')
@section('content')
    <section class="collaboration-create">
        <a href="{{ route('collaboration') }}">← {{ __('ui.collaboration.back_to_openings') }}</a>
        <h1>{{ __('waasabi.quick_help') }}</h1>
        <p>{{ __('waasabi.quick_help_hint') }}</p>
        @if ($errors->any()) <div class="form-error" role="alert">{{ $errors->first() }}</div> @endif
        <form class="collaboration-form quick-help-form" method="POST" action="{{ route('collaboration.store') }}">
            @csrf
            <div class="honeypot-field" aria-hidden="true"><label>{{ __('ui.auth.website') }}<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="collaboration-form__grid">
                <label><span class="label-text">{{ __('ui.collaboration.form_role') }}</span>
                    <select class="input" name="role" required>@foreach ($collaboration_roles as $key => $label)<option value="{{ $key }}" @selected(old('role', '3d-artist') === $key)>{{ $label }}</option>@endforeach</select>
                </label>
                <label><span class="label-text">{{ __('ui.collaboration.form_availability') }}</span>
                    <select class="input" name="availability">@foreach ($collaboration_availability as $key => $label)<option value="{{ $key }}" @selected(old('availability', 'one-time') === $key)>{{ $label }}</option>@endforeach</select>
                </label>
            </div>
            <label><span class="label-text">{{ __('waasabi.task') }}</span>
                <textarea class="input" name="summary" rows="4" minlength="2" maxlength="2000" placeholder="{{ __('waasabi.task_placeholder') }}" required>{{ old('summary') }}</textarea>
            </label>
            <label><span class="label-text">{{ __('waasabi.project_optional') }}</span>
                <select class="input" name="post_id"><option value="">{{ __('waasabi.no_project') }}</option>@foreach ($manageable_projects as $project)<option value="{{ $project->id }}" @selected((int) old('post_id', request('project')) === $project->id)>{{ $project->title }}</option>@endforeach</select>
            </label>
            <details class="form-details" @if(old('title') || old('skills')) open @endif>
                <summary><span>{{ __('waasabi.more_details') }}</span><i data-lucide="chevron-down" class="icon details-chevron" aria-hidden="true"></i></summary>
                <label><span class="label-text">{{ __('ui.collaboration.form_title_label') }} · {{ __('waasabi.optional') }}</span><input class="input" name="title" maxlength="120" value="{{ old('title') }}"></label>
                <label><span class="label-text">{{ __('ui.collaboration.form_skills') }}</span><input class="input" name="skills" maxlength="400" value="{{ old('skills') }}" placeholder="Blender, low-poly"></label>
                <label><span class="label-text">{{ __('ui.collaboration.form_format') }}</span><select class="input" name="format">@foreach ($collaboration_formats as $key => $label)<option value="{{ $key }}" @selected(old('format', 'remote') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="label-text">{{ __('ui.collaboration.form_duration') }}</span><input class="input" type="number" name="expires_in_days" min="7" max="180" value="{{ old('expires_in_days', 30) }}"></label>
            </details>
            <button class="cta-btn" type="submit">{{ __('ui.collaboration.form_submit') }}</button>
        </form>
    </section>
@endsection
