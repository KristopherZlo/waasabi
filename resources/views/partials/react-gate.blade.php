<div class="react-gate" data-react-gate>
    <div class="card gate-card">
        <div class="section">
            <h2 class="section-title">{{ __('ui.react.choose_title') }}</h2>
            <p class="helper">{{ __('ui.react.choose_subtitle') }}</p>
        </div>
        <div class="media {{ $project['media'] }}"></div>
        <div>
            <h3 class="card-title">{{ $project['title'] }}</h3>
            <p class="context">{{ $project['context'] }}</p>
        </div>
        @include('partials.reactions', ['reactions' => $reactions])
        <button type="button" class="ghost-btn" data-skip>{{ __('ui.react.skip') }}</button>
    </div>
</div>
