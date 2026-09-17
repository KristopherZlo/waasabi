@extends('layouts.app')
@section('title', __('waasabi.write_update'))
@section('page', 'journal-edit')
@section('content')
    <a href="{{ route('project', $post->slug) }}">← {{ $post->title }}</a>
    <h1>{{ $update ? __('waasabi.edit_update') : __('waasabi.write_update') }}</h1>
    <p class="writing-prompt">{{ __('waasabi.update_hint') }}</p>
    <form class="journal-editor editor-shell" method="POST" data-publish-form data-publish-type="post" data-editing="{{ $update ? '1' : '0' }}" data-draft-key="journal-{{ $post->id }}-{{ $update?->id ?? 'new' }}" data-restore-draft="{{ session()->hasOldInput() ? '0' : '1' }}" data-saved-at="{{ $update?->updated_at?->getTimestampMs() ?? 0 }}"
        action="{{ $update ? route('projects.updates.update', [$post->slug, $update]) : route('projects.updates.store', $post->slug) }}">
        @csrf
        @if ($update) @method('PUT') @endif
        @if ($errors->any()) <p class="form-error" role="alert">{{ $errors->first() }}</p> @endif
        <label><span class="label-text">{{ __('waasabi.update_title') }}</span>
            <input class="input editor-title-input" name="title" maxlength="120" value="{{ old('title', $update?->title) }}" required data-required data-draft-field="title">
        </label>
        <div class="editor-toolbar" data-editor-toolbar>
            @foreach (['bold', 'italic', 'h2', 'h3', 'bullet', 'ordered', 'quote', 'link', 'image', 'undo', 'redo'] as $action)
                <button type="button" class="toolbar-btn" data-editor-action="{{ $action }}" aria-label="{{ __('ui.publish.toolbar.'.$action) }}">{{ in_array($action, ['h2','h3']) ? strtoupper($action) : '' }}@unless(in_array($action, ['h2','h3']))<i data-lucide="{{ ['bullet' => 'list', 'ordered' => 'list-ordered', 'quote' => 'quote', 'undo' => 'undo-2', 'redo' => 'redo-2'][$action] ?? $action }}" class="icon"></i>@endunless</button>
            @endforeach
        </div>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-editor-image-input hidden>
        <div class="editor-surface" data-editor data-editor-placeholder="{{ __('waasabi.update_body') }}"></div>
        <textarea class="editor-output" name="body" data-editor-output data-required>{{ old('body', $update?->body) }}</textarea>
        <div data-editor-count class="helper"></div>
        <div class="journal-editor__actions"><a class="ghost-btn" href="{{ route('project', $post->slug) }}">{{ __('waasabi.back') }}</a><button class="cta-btn" type="submit" data-publish-submit>{{ __('waasabi.save') }}</button></div>
    </form>
@endsection
