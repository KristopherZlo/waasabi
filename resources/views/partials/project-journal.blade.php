<section class="project-journal" id="journal">
    <header class="community-heading"><h2>{{ __('waasabi.journal') }}</h2>
        @if ($canManageProject)<a class="cta-btn" href="{{ route('projects.updates.create', $project['slug']) }}">{{ __('waasabi.write_update') }}</a>@endif
        @can('moderate')<a href="{{ route('journal.moderation') }}">{{ __('ui.admin.title') }}</a>@endcan
    </header>
    @forelse ($project['updates'] as $update)
        <article class="journal-entry" id="update-{{ $update['id'] }}">
            <div class="journal-entry__meta">{{ $update['author'] }} · {{ $update['time'] }} @if($update['is_hidden']) · {{ __('ui.moderation.status_hidden') }} @endif</div>
            <h3>{{ $update['title'] }}</h3>
            <div class="reading-content">{!! app(\App\Services\MarkdownService::class)->render($update['body']) !!}</div>
            <div class="journal-entry__actions">
                @if ($canManageProject && ($isProjectOwner || Auth::id() === $update['user_id']))
                    <a href="{{ route('projects.updates.edit', [$project['slug'], $update['id']]) }}">{{ __('waasabi.edit_update') }}</a>
                @endif
                @if ($isProjectOwner || Auth::id() === $update['user_id'] || Auth::user()?->hasRole('moderator'))
                    <form method="POST" action="{{ route('projects.updates.destroy', [$project['slug'], $update['id']]) }}" data-confirm-submit data-confirm-message="{{ __('ui.project.delete_update') }}?">@csrf @method('DELETE')<button class="ghost-btn" type="submit">{{ __('ui.project.delete_update') }}</button></form>
                @endif
            </div>
        </article>
    @empty
        <p class="helper">{{ __('waasabi.update_empty') }}</p>
    @endforelse
</section>
