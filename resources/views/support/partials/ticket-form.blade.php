@if ($errors->any())
    <div class="support-alert">
        <div class="support-alert__title">{{ __('ui.support.ticket_error_title') }}</div>
        <ul class="support-alert__list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $supportArticles = collect($support_articles ?? []);
    $suggestedArticles = $supportArticles->take(3);
@endphp

<form class="support-form" method="POST" action="{{ route('support.ticket.store') }}">
    @csrf
    <label class="support-form__field">
        <span class="label-text">{{ __('ui.support.ticket_kind_label') }}</span>
        <select class="input" name="kind" required>
            <option value="question" @selected(old('kind') === 'question')>{{ __('ui.support.ticket_kind_question') }}</option>
            <option value="bug" @selected(old('kind') === 'bug')>{{ __('ui.support.ticket_kind_bug') }}</option>
            <option value="complaint" @selected(old('kind') === 'complaint')>{{ __('ui.support.ticket_kind_complaint') }}</option>
        </select>
    </label>

    <label class="support-form__field">
        <span class="label-text">{{ __('ui.support.ticket_subject_label') }}</span>
        <input
            class="input"
            type="text"
            name="subject"
            value="{{ old('subject') }}"
            placeholder="{{ __('ui.support.ticket_subject_placeholder') }}"
            maxlength="190"
            data-support-ticket-subject
            required
        >
    </label>

    <label class="support-form__field">
        <span class="label-text">{{ __('ui.support.ticket_body_label') }}</span>
        <textarea
            class="input support-form__textarea"
            name="body"
            rows="8"
            placeholder="{{ __('ui.support.ticket_body_placeholder') }}"
            maxlength="8000"
            data-support-ticket-body
            required
        >{{ old('body') }}</textarea>
        <div class="helper">{{ __('ui.support.ticket_body_helper') }}</div>
    </label>

    <div class="support-suggestions" data-support-suggestions>
        <div class="support-suggestions__header">
            <div class="support-suggestions__title">{{ __('ui.support.portal_suggestions_title') }}</div>
            <div class="support-suggestions__hint">{{ __('ui.support.portal_suggestions_hint') }}</div>
        </div>
        <div class="support-suggestions__list" data-support-suggestions-list>
            @foreach ($suggestedArticles as $article)
                <a class="support-suggestion" href="{{ $article['url'] ?? '#' }}">
                    <div class="support-suggestion__title">{{ $article['title'] ?? '' }}</div>
                    <div class="support-suggestion__text">{{ $article['summary'] ?? '' }}</div>
                </a>
            @endforeach
        </div>
        <div class="support-suggestions__empty" data-support-suggestions-empty @if ($suggestedArticles->isNotEmpty()) hidden @endif>
            {{ __('ui.support.portal_suggestions_empty') }}
        </div>
    </div>

    <div class="support-form__actions">
        <button type="submit" class="primary-cta">{{ __('ui.support.ticket_submit') }}</button>
    </div>
</form>
