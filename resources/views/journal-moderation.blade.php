@extends('layouts.app')
@section('title', __('waasabi.journal'))
@section('content')
    <a href="{{ route('admin') }}">← {{ __('ui.admin.title') }}</a>
    <h1>{{ __('waasabi.journal') }}</h1>
    @foreach ($updates as $update)
        <article class="journal-entry">
            <h2><a href="{{ route('project', $update->post->slug) }}#update-{{ $update->id }}">{{ $update->title }}</a></h2>
            <p>{{ $update->post->title }} · {{ $update->user->name }}</p>
            <div class="reading-content">{!! app(\App\Services\MarkdownService::class)->render($update->body) !!}</div>
            <form class="community-search" method="POST" action="{{ route('journal.moderate', $update) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="is_hidden" value="{{ $update->is_hidden ? '0' : '1' }}">
                <label for="reason-{{ $update->id }}">{{ __('ui.moderation.reason_placeholder') }}</label>
                <input class="input" id="reason-{{ $update->id }}" name="reason" required maxlength="500">
                <button class="ghost-btn">{{ $update->is_hidden ? __('ui.moderation.restore') : __('ui.moderation.hide') }}</button>
            </form>
        </article>
    @endforeach
    {{ $updates->links() }}
@endsection
