@php
    $qaLimit = 5;
    $qaHasMore = count($threads) > $qaLimit;
    $qaAnchor = $qa_anchor ?? null;
@endphp
<div class="card qa-card" data-qa-block data-feed-type="qa" @if (!empty($qaAnchor)) data-qa-anchor="{{ $qaAnchor }}" @endif>
    <div class="qa-header">
        <div class="qa-title">{{ __('ui.qa.title') }}</div>
    <div class="tabs qa-tabs">
        <button type="button" class="tab qa-tab is-active" data-qa-tab="questions">{{ __('ui.qa.tab_questions') }}</button>
        <button type="button" class="tab qa-tab" data-qa-tab="hot">{{ __('ui.qa.tab_hot') }}</button>
        <button type="button" class="tab qa-tab" data-qa-tab="new">{{ __('ui.qa.tab_new') }}</button>
    </div>
    </div>
    <div class="qa-list" data-qa-list data-qa-limit="{{ $qaLimit }}">
        @foreach ($threads as $thread)
            @php
                $isHidden = $loop->index >= $qaLimit;
            @endphp
            <a class="qa-item {{ $isHidden ? 'is-hidden' : '' }}" href="{{ route('questions.show', $thread['slug']) }}" data-qa-item data-qa-order="{{ $loop->index }}" data-qa-replies="{{ $thread['replies'] ?? 0 }}" data-qa-minutes="{{ $thread['minutes'] ?? 0 }}" data-qa-delta="{{ ltrim($thread['delta'] ?? '+0', '+') }}" data-qa-slug="{{ $thread['slug'] }}">
                <div class="qa-item-title">{{ $thread['title'] }}</div>
                <div class="qa-item-meta">
                    <span>{{ $thread['time'] }}</span>
                    <span class="qa-item-replies">
                        <i data-lucide="message-circle" class="icon"></i>
                        {{ $thread['replies'] }}
                    </span>
                    <span class="qa-item-delta">{{ $thread['delta'] }}</span>
                </div>
            </a>
        @endforeach
    </div>
    <div class="qa-actions">
        <button type="button" class="qa-more" data-qa-more {{ $qaHasMore ? '' : 'hidden' }}>{{ __('ui.qa.more') }}</button>
        <button type="button" class="qa-less" data-qa-less hidden>{{ __('ui.qa.less') }}</button>
    </div>
</div>
