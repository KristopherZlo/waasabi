@php
    $savedType = ($item['type'] ?? 'project') === 'question' ? 'question' : 'project';
    $saved = $item['data'] ?? [];
    $savedSlug = $saved['slug'] ?? '';
    $savedUrl = $savedType === 'question'
        ? route('questions.show', $savedSlug)
        : route('project', $savedSlug);
    $savedAuthor = $saved['author']['name'] ?? __('ui.project.anonymous');
    $savedExcerptSource = $savedType === 'question'
        ? ($saved['body'] ?? '')
        : ($saved['subtitle'] ?? $saved['body_markdown'] ?? '');
    $savedExcerpt = \Illuminate\Support\Str::limit(trim(strip_tags((string) $savedExcerptSource)), 220);
    $savedCoverPath = $saved['cover'] ?? 'images/logo-black.svg';
    $savedCoverUrl = \Illuminate\Support\Str::startsWith($savedCoverPath, ['http://', 'https://'])
        ? $savedCoverPath
        : asset(ltrim($savedCoverPath, '/'));
@endphp

<article class="saved-item" data-feed-card data-feed-type="{{ $savedType === 'question' ? 'questions' : 'projects' }}" data-project-slug="{{ $savedSlug }}">
    <a class="saved-item__media {{ $savedType === 'question' ? 'saved-item__media--question' : '' }}" href="{{ $savedUrl }}" tabindex="-1" aria-hidden="true">
        @if ($savedType === 'question')
            <i data-lucide="message-circle" class="icon"></i>
        @else
            <img src="{{ $savedCoverUrl }}" alt="" loading="lazy" data-fallback="{{ asset('images/logo-black.svg') }}">
        @endif
    </a>
    <div class="saved-item__content">
        <div class="saved-item__meta">
            <span>{{ $savedType === 'question' ? __('ui.feed.tab_questions') : __('ui.feed.tab_projects') }}</span>
            <span aria-hidden="true">&middot;</span>
            <span>{{ $savedAuthor }}</span>
        </div>
        <a class="saved-item__title" href="{{ $savedUrl }}">{{ $saved['title'] ?? $savedSlug }}</a>
        @if ($savedExcerpt !== '')
            <p class="saved-item__excerpt">{{ $savedExcerpt }}</p>
        @endif
        <div class="saved-item__footer">
            <span class="saved-item__stat"><i data-lucide="arrow-up" class="icon"></i>{{ $saved['score'] ?? 0 }}</span>
            <span class="saved-item__stat"><i data-lucide="message-circle" class="icon"></i>{{ $saved['comments_count'] ?? $saved['replies'] ?? 0 }}</span>
            <button type="button" class="saved-item__remove is-active" data-action="save" data-project-slug="{{ $savedSlug }}" data-saved="1" data-saved-label="{{ __('ui.read_later.remove') }}">
                <i data-lucide="bookmark-x" class="icon"></i>
                <span class="action-label">{{ __('ui.read_later.remove') }}</span>
            </button>
        </div>
    </div>
</article>
