@php
    $interactionType = $type ?? 'comment';
    $interactionId = $id ?? null;
    $interactionValues = $values ?? [];
@endphp

@if ($interactionId)
    <details class="interaction-editor">
        <summary class="comment-action"><span>{{ __('ui.project.edit') }}</span><i data-lucide="chevron-down" class="icon details-chevron" aria-hidden="true"></i></summary>
        <form method="POST" action="{{ $interactionType === 'review' ? route('reviews.update', $interactionId) : route('comments.update', $interactionId) }}" class="interaction-editor__form">
            @csrf
            @method('PATCH')
            @if ($interactionType === 'review')
                <label>{{ __('ui.project.review_improve') }}<textarea class="input" name="improve" rows="3" required maxlength="2000">{{ $interactionValues['improve'] ?? '' }}</textarea></label>
                <label>{{ __('ui.project.review_why') }}<textarea class="input" name="why" rows="3" required maxlength="2000">{{ $interactionValues['why'] ?? '' }}</textarea></label>
                <label>{{ __('ui.project.review_how') }}<textarea class="input" name="how" rows="3" required maxlength="2000">{{ $interactionValues['how'] ?? '' }}</textarea></label>
            @else
                <textarea class="input" name="body" rows="3" required maxlength="2000">{{ $interactionValues['body'] ?? '' }}</textarea>
                @if (!empty($interactionValues['section']))
                    <input class="input" name="section" value="{{ $interactionValues['section'] }}" maxlength="80">
                @endif
            @endif
            <button class="btn btn--accent btn--sm" type="submit">{{ __('ui.project.save_changes') }}</button>
        </form>
    </details>
    <form method="POST" action="{{ $interactionType === 'review' ? route('reviews.destroy', $interactionId) : route('comments.destroy', $interactionId) }}" data-confirm-submit data-confirm-message="{{ __('ui.project.delete_interaction_confirm') }}">
        @csrf
        @method('DELETE')
        <button class="comment-action comment-action--danger" type="submit">{{ __('ui.project.delete') }}</button>
    </form>
@endif
