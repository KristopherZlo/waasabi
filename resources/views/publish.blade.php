@extends('layouts.app')

@php
    $editPost = $edit_post ?? null;
    $isEditing = !empty($editPost);
    $editType = $editPost['type'] ?? 'post';
    $pageTitle = $isEditing ? __('ui.publish.edit_title') : __('ui.publish.title');
    $pageSubtitle = $isEditing ? __('ui.publish.edit_subtitle') : __('ui.publish.subtitle');
    $submitLabel = $isEditing ? __('ui.publish.edit_cta') : __('ui.publish.publish_cta');
    $coauthorSuggestions = $coauthor_suggestions ?? [];
@endphp

@section('title', $pageTitle)
@section('page', $isEditing ? 'edit' : 'publish')

@section('content')
    <section class="hero">
        <h1>{{ $pageTitle }}</h1>
        <p>{{ $pageSubtitle }}</p>
    </section>

    <section class="section" style="margin-top: 24px;">
        <form class="editor-shell" method="POST" action="{{ route('publish.store') }}" enctype="multipart/form-data" data-publish-form data-editing="{{ $isEditing ? '1' : '0' }}" data-publish-type="{{ $editType }}">
            @csrf
            <input type="hidden" name="post_id" value="{{ $editPost['id'] ?? '' }}">
            <div class="publish-type" data-publish-type-tabs>
                <button type="button" class="publish-type__tab {{ $editType === 'post' ? 'is-active' : '' }}" data-publish-type-tab="post" @if ($isEditing) disabled @endif>
                    {{ __('ui.publish.type_post') }}
                </button>
                <button type="button" class="publish-type__tab {{ $editType === 'question' ? 'is-active' : '' }}" data-publish-type-tab="question" @if ($isEditing) disabled @endif>
                    {{ __('ui.publish.type_question') }}
                </button>
            </div>
            <input type="hidden" name="publish_type" value="{{ $editType }}" data-publish-type-input data-draft-field="publish_type">
            <div class="editor-grid">
                <div class="editor-meta">
                    <div class="editor-panel">
                        <label>
                            <span class="label-text" data-publish-label data-post-text="{{ __('ui.publish.title_label') }}" data-question-text="{{ __('ui.publish.title_label_question') }}">
                                {{ __('ui.publish.title_label') }}
                            </span>
                            <input class="input" type="text" name="title" placeholder="{{ __('ui.publish.title_placeholder') }}" value="{{ old('title', $editPost['title'] ?? '') }}" data-required data-draft-field="title" data-publish-placeholder data-post-placeholder="{{ __('ui.publish.title_placeholder') }}" data-question-placeholder="{{ __('ui.publish.title_placeholder_question') }}">
                        </label>
                        <label data-publish-section="post">
                            <span class="label-text" data-publish-label data-post-text="{{ __('ui.publish.subtitle_label') }}" data-question-text="{{ __('ui.publish.subtitle_label_question') }}">
                                {{ __('ui.publish.subtitle_label') }}
                            </span>
                            <input class="input" type="text" name="subtitle" placeholder="{{ __('ui.publish.subtitle_placeholder') }}" value="{{ old('subtitle', $editPost['subtitle'] ?? '') }}" data-required data-required-type="post" data-draft-field="subtitle" data-publish-placeholder data-post-placeholder="{{ __('ui.publish.subtitle_placeholder') }}" data-question-placeholder="{{ __('ui.publish.subtitle_placeholder_question') }}">
                            <span class="helper" data-publish-helper data-post-text="{{ __('ui.publish.subtitle_helper') }}" data-question-text="{{ __('ui.publish.subtitle_helper_question') }}">
                                {{ __('ui.publish.subtitle_helper') }}
                            </span>
                        </label>
                        <label data-publish-section="post">
                            {{ __('ui.publish.status_label') }}
                            <select class="input" name="status" data-draft-field="status">
                                <option value="in_progress" {{ ($editPost['status'] ?? 'in_progress') === 'in_progress' ? 'selected' : '' }}>{{ __('ui.publish.status_in_progress') }}</option>
                                <option value="done" {{ ($editPost['status'] ?? '') === 'done' ? 'selected' : '' }}>{{ __('ui.publish.status_done') }}</option>
                                <option value="paused" {{ ($editPost['status'] ?? '') === 'paused' ? 'selected' : '' }}>{{ __('ui.publish.status_paused') }}</option>
                            </select>
                        </label>
                        <div class="nsfw-field" data-publish-section="post">
                            <div class="nsfw-field__row">
                                <span class="label-text">{{ __('ui.publish.nsfw_label') }}</span>
                                <label class="switch">
                                    <input type="checkbox" name="nsfw" value="1" {{ old('nsfw', $editPost['nsfw'] ?? false) ? 'checked' : '' }}>
                                    <span class="switch__track"></span>
                                </label>
                            </div>
                            <span class="helper">{{ __('ui.publish.nsfw_helper') }}</span>
                        </div>
                        <label>
                            {{ __('ui.publish.tags_label') }}
                            <input class="input" type="text" name="tags" placeholder="{{ __('ui.publish.tags_placeholder') }}" value="{{ old('tags', $editPost['tags'] ?? '') }}" data-draft-field="tags">
                        </label>
                        <label>
                            {{ __('ui.publish.coauthors_label') }}
                            <input
                                class="input"
                                type="text"
                                name="coauthors"
                                placeholder="{{ __('ui.publish.coauthors_placeholder') }}"
                                value="{{ old('coauthors', $editPost['coauthors'] ?? '') }}"
                                data-draft-field="coauthors"
                                @if (!empty($coauthorSuggestions)) list="publish-coauthors-suggestions" @endif
                            >
                            <span class="helper">{{ __('ui.publish.coauthors_helper') }}</span>
                        </label>
                        @if (!empty($coauthorSuggestions))
                            <datalist id="publish-coauthors-suggestions">
                                @foreach ($coauthorSuggestions as $suggestion)
                                    @php
                                        $slug = (string) ($suggestion['slug'] ?? '');
                                        $name = (string) ($suggestion['name'] ?? '');
                                    @endphp
                                    @if ($slug !== '')
                                        <option value="@{{ $slug }}" label="{{ $name !== '' ? $name . ' (@' . $slug . ')' : '@' . $slug }}"></option>
                                    @endif
                                @endforeach
                            </datalist>
                        @endif
                        <label data-publish-section="post">
                            {{ __('ui.publish.cover_label') }}
                            <input class="input" type="file" name="cover_images[]" accept="image/jpeg,image/png,image/webp" multiple>
                            <span class="helper">{{ __('ui.publish.cover_helper') }}</span>
                        </label>
                    </div>
                </div>

                <div class="editor-panel editor-panel--editor" data-publish-section="post">
                    <div class="editor-toolbar" data-editor-toolbar>
                        <button type="button" class="toolbar-btn" data-editor-action="bold" aria-label="{{ __('ui.publish.toolbar.bold') }}">
                            <i data-lucide="bold" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="italic" aria-label="{{ __('ui.publish.toolbar.italic') }}">
                            <i data-lucide="italic" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="h2" aria-label="{{ __('ui.publish.toolbar.h2') }}">H2</button>
                        <button type="button" class="toolbar-btn" data-editor-action="h3" aria-label="{{ __('ui.publish.toolbar.h3') }}">H3</button>
                        <button type="button" class="toolbar-btn" data-editor-action="bullet" aria-label="{{ __('ui.publish.toolbar.bullet') }}">
                            <i data-lucide="list" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="ordered" aria-label="{{ __('ui.publish.toolbar.ordered') }}">
                            <i data-lucide="list-ordered" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="table" aria-label="{{ __('ui.publish.toolbar.table') }}">
                            <i data-lucide="table" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="quote" aria-label="{{ __('ui.publish.toolbar.quote') }}">
                            <i data-lucide="quote" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="code" aria-label="{{ __('ui.publish.toolbar.code') }}">
                            <i data-lucide="code-2" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="spoiler" aria-label="{{ __('ui.publish.toolbar.spoiler') }}">
                            <i data-lucide="eye-off" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="link" aria-label="{{ __('ui.publish.toolbar.link') }}">
                            <i data-lucide="link" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="image" aria-label="{{ __('ui.publish.toolbar.image') }}">
                            <i data-lucide="image" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="undo" aria-label="{{ __('ui.publish.toolbar.undo') }}">
                            <i data-lucide="undo-2" class="icon"></i>
                        </button>
                        <button type="button" class="toolbar-btn" data-editor-action="redo" aria-label="{{ __('ui.publish.toolbar.redo') }}">
                            <i data-lucide="redo-2" class="icon"></i>
                        </button>
                    </div>
                    <div class="editor-table-panel" data-editor-table-panel hidden>
                        <div class="editor-table-header">
                            <div class="editor-table-title">{{ __('ui.publish.table.title') }}</div>
                        </div>
                        <div class="editor-table-actions">
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="add-row-before" aria-label="{{ __('ui.publish.table.row_above') }}">
                                <i data-lucide="arrow-up" class="icon"></i>{{ __('ui.publish.table.row_above') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="add-row-after" aria-label="{{ __('ui.publish.table.row_below') }}">
                                <i data-lucide="arrow-down" class="icon"></i>{{ __('ui.publish.table.row_below') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="delete-row" aria-label="{{ __('ui.publish.table.delete_row') }}">
                                <i data-lucide="minus" class="icon"></i>{{ __('ui.publish.table.delete_row') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="add-column-before" aria-label="{{ __('ui.publish.table.col_left') }}">
                                <i data-lucide="arrow-left" class="icon"></i>{{ __('ui.publish.table.col_left') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="add-column-after" aria-label="{{ __('ui.publish.table.col_right') }}">
                                <i data-lucide="arrow-right" class="icon"></i>{{ __('ui.publish.table.col_right') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="delete-column" aria-label="{{ __('ui.publish.table.delete_col') }}">
                                <i data-lucide="minus" class="icon"></i>{{ __('ui.publish.table.delete_col') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="toggle-header-row" aria-label="{{ __('ui.publish.table.header_row') }}">
                                <i data-lucide="heading" class="icon"></i>{{ __('ui.publish.table.header_row') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="toggle-header-column" aria-label="{{ __('ui.publish.table.header_col') }}">
                                <i data-lucide="heading" class="icon"></i>{{ __('ui.publish.table.header_col') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="toggle-header-cell" aria-label="{{ __('ui.publish.table.header_cell') }}">
                                <i data-lucide="heading" class="icon"></i>{{ __('ui.publish.table.header_cell') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="merge-cells" aria-label="{{ __('ui.publish.table.merge') }}">
                                <i data-lucide="table-cells-merge" class="icon"></i>{{ __('ui.publish.table.merge') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="split-cell" aria-label="{{ __('ui.publish.table.split') }}">
                                <i data-lucide="table-cells-split" class="icon"></i>{{ __('ui.publish.table.split') }}
                            </button>
                            <button type="button" class="ghost-btn editor-table-btn" data-table-action="delete-table" aria-label="{{ __('ui.publish.table.delete_table') }}">
                                <i data-lucide="trash-2" class="icon"></i>{{ __('ui.publish.table.delete_table') }}
                            </button>
                        </div>
                        <div class="helper">{{ __('ui.publish.table.hint') }}</div>
                    </div>
                    <div class="editor-image-panel" data-editor-image-panel hidden>
                        <div class="editor-image-header">
                            <div class="editor-image-title">{{ __('ui.publish.image.title') }}</div>
                            <button type="button" class="icon-btn editor-image-close" data-editor-image-close aria-label="{{ __('ui.publish.image.close') }}">
                                <i data-lucide="x" class="icon"></i>
                            </button>
                        </div>
                        <div class="editor-image-row">
                            <input class="input" type="url" placeholder="{{ __('ui.publish.image.url_placeholder') }}" data-editor-image-url>
                            <button type="button" class="ghost-btn editor-image-btn" data-editor-image-upload>{{ __('ui.publish.image.upload') }}</button>
                            <button type="button" class="primary-cta editor-image-btn" data-editor-image-insert>{{ __('ui.publish.image.insert') }}</button>
                        </div>
                        <div class="helper">{{ __('ui.publish.image.helper') }}</div>
                    </div>
                    <input class="input" type="file" accept="image/*" multiple data-editor-image-input hidden>
                    <div class="editor-surface" data-editor data-editor-placeholder="{{ __('ui.publish.editor_placeholder') }}"></div>
                    <textarea class="editor-output" name="body" data-editor-output data-required data-required-type="post">{{ old('body', $editPost['body'] ?? '') }}</textarea>
                    <div class="editor-footer">
                        <div class="helper" data-editor-count>{{ __('ui.publish.word_count', ['count' => 0]) }}</div>
                        <div class="helper">{{ __('ui.publish.markdown_hint') }}</div>
                    </div>
                </div>
                <div class="editor-panel editor-panel--question" data-publish-section="question">
                    <label>
                        {{ __('ui.publish.question_label') }}
                        <textarea class="input editor-question" name="question_body" placeholder="{{ __('ui.publish.question_placeholder') }}" data-required data-required-type="question" data-draft-field="question_body">{{ old('question_body', $editPost['question_body'] ?? '') }}</textarea>
                        <span class="helper">{{ __('ui.publish.question_helper') }}</span>
                    </label>
                </div>
            </div>
            <div class="editor-actions">
                <button type="submit" class="submit-btn" data-publish-submit disabled>{{ $submitLabel }}</button>
            </div>
            <div class="publish-loader" data-publish-loader hidden>
                <div class="publish-loader__panel" role="status" aria-live="polite">
                    <div class="publish-loader__spinner" aria-hidden="true"></div>
                    <div class="publish-loader__title">{{ __('ui.publish.publishing_title') }}</div>
                    <div class="publish-loader__subtitle">{{ __('ui.publish.publishing_subtitle') }}</div>
                </div>
            </div>
        </form>
    </section>
@endsection
