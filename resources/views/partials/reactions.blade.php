<div class="reaction-bar">
    @foreach ($reactions as $reaction)
        <button type="button" class="reaction-btn" data-reaction="{{ $reaction }}">{{ $reaction }}</button>
    @endforeach
</div>
