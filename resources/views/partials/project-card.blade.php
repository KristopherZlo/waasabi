@php
    $projectTags = $project['tags'] ?? [];
    $visibleTags = array_slice($projectTags, 0, 5);
    $extraTags = max(count($projectTags) - count($visibleTags), 0);
    $previewSource = (string) ($project['body_html'] ?? $project['body_markdown'] ?? '');
    if ($previewSource === '' && !empty($project['sections'])) {
        $sectionText = [];
        foreach ($project['sections'] as $section) {
            foreach (($section['blocks'] ?? []) as $block) {
                $text = $block['text'] ?? '';
                if (is_string($text) && trim($text) !== '') {
                    $sectionText[] = $text;
                }
            }
        }
        $previewSource = implode(' ', $sectionText);
    }
    $previewText = trim(strip_tags($previewSource));
    $previewText = trim((string) preg_replace('/\s+/', ' ', $previewText));
    if ($previewText === '') {
        $previewText = $project['subtitle'] ?? $project['context'] ?? '';
    }
    $previewText = $previewText !== '' ? \Illuminate\Support\Str::limit($previewText, 600) : '';
    $commentCount = $project['comments_count'] ?? count($project['comments'] ?? []);
    $authorName = $project['author']['name'] ?? __('ui.project.anonymous');
    $avatarPath = $project['author']['avatar'] ?? 'images/avatar-default.svg';
    $avatarIsDefault = trim($avatarPath, '/') === 'images/avatar-default.svg';
    $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
        ? $avatarPath
        : asset(ltrim($avatarPath, '/'));
    $authorSlug = $project['author']['slug'] ?? \Illuminate\Support\Str::slug($authorName);
    $viewer = Auth::user();
    $viewerId = $viewer?->id;
    $viewerSlug = $viewer?->slug ?? '';
    $isViewerAuthor = $viewer
        && (
            (!empty($project['author_id']) && $viewerId === $project['author_id'])
            || ($viewerSlug !== '' && $viewerSlug === $authorSlug)
        );
    $placeholderPath = 'images/logo-black.svg';
    $coverPath = $project['cover'] ?? $placeholderPath;
    $isCoverPlaceholder = trim($coverPath, '/') === trim($placeholderPath, '/');
    $coverUrl = \Illuminate\Support\Str::startsWith($coverPath, ['http://', 'https://'])
        ? $coverPath
        : asset(ltrim($coverPath, '/'));
    $albumImages = collect($project['album'] ?? []);
    $coverSources = collect([$coverPath])->merge($albumImages);
    $coverImages = $coverSources
        ->map(function ($item) {
            $value = trim((string) $item);
            if ($value === '') {
                return null;
            }
            return \Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])
                ? $value
                : asset(ltrim($value, '/'));
        })
        ->filter()
        ->unique()
        ->values();
    $hasCarousel = $coverImages->count() > 1;
    $authorRoleKey = strtolower($project['author']['role'] ?? 'user');
    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
    $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
    $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
    $published = $project['published'] ?? __('ui.project.today');
    $readTimeValue = $project['read_time'] ?? null;
    $readTimeLabel = $readTimeValue ? __('ui.project.read_time', ['time' => $readTimeValue]) : '-';
    $reportCount = (int) ($project['report_count'] ?? 0);
    $reportPoints = (int) ($project['report_points'] ?? $reportCount);
    $edited = !empty($project['edited']);
    $editedAt = $project['edited_at'] ?? null;
    $editedBy = $project['edited_by'] ?? [];
    $editedByName = $editedBy['name'] ?? '';
    $showEdited = $edited && $editedAt && $editedByName !== '';
    $isNsfw = !empty($project['nsfw']);
    $moderationStatus = strtolower((string) ($project['moderation_status'] ?? 'approved'));
    $isHidden = !empty($project['is_hidden']);
    $moderationNsfwPending = !empty($project['moderation_nsfw_pending']);
    $currentModerator = Auth::user();
    $isAdminModerator = $currentModerator?->isAdmin() ?? false;
    $canModerateContent = $currentModerator && ($isAdminModerator || $authorRoleKey !== 'admin');
    $showQueue = $moderationStatus !== 'pending';
    $showHide = $moderationStatus !== 'hidden';
    $showRestore = $moderationStatus !== 'approved';
@endphp
<article class="card post-card" data-feed-card data-feed-type="projects" data-feed-key="project:{{ $project['slug'] }}" data-project-slug="{{ $project['slug'] }}" data-post-id="{{ $project['id'] ?? '' }}" data-score="{{ $project['score'] ?? 0 }}" data-published="{{ $project['published_minutes'] ?? 0 }}" data-read-min="{{ $project['read_time_minutes'] ?? 0 }}" data-tags="{{ collect($projectTags)->map(fn ($tag) => \Illuminate\Support\Str::slug($tag))->join(',') }}" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="post">
    <button class="post-jump" type="button" data-post-jump aria-label="{{ __('ui.card.jump_next') }}">
        <i data-lucide="arrow-down" class="icon"></i>
    </button>
    <div class="post-meta">
        <img class="avatar" src="{{ $avatarUrl }}" alt="{{ $authorName }}" @if ($avatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $authorName }}" @endif>
        <a class="post-author" href="{{ route('profile.show', $authorSlug) }}">{{ $authorName }}</a>
        <span class="badge badge--{{ $authorRoleKey }}">{{ $authorRoleLabel }}</span>
        <span class="dot">&bull;</span>
        <span>{{ $published }}</span>
        @if ($showEdited)
            <span class="dot">&bull;</span>
            <span class="post-edited">{{ __('ui.project.edited', ['time' => $editedAt, 'user' => $editedByName]) }}</span>
        @endif
        <span class="dot">&bull;</span>
        <span>{{ $readTimeLabel }}</span>
    </div>
    @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
        @php
            $currentUser = Auth::user();
            $currentSlug = $currentUser?->slug ?? '';
            $isAuthor = $currentUser
                && (
                    (!empty($project['author_id']) && $currentUser->id === $project['author_id'])
                    || ($currentSlug !== '' && $currentSlug === $authorSlug)
                );
            $isAdmin = $currentUser?->isAdmin() ?? false;
            $canEdit = $currentUser && ($isAuthor || $isAdmin);
            $canDelete = $canEdit;
        @endphp
        <div class="action-menu action-menu--card" data-action-menu-container>
            <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                <i data-lucide="more-horizontal" class="icon"></i>
            </button>
            <div class="action-menu__panel" role="menu" data-action-menu hidden>
                @if ($canEdit && !empty($project['id']))
                    <a class="action-menu__item" href="{{ route('posts.edit', $project['slug']) }}">
                        <i data-lucide="pencil" class="icon"></i>
                        <span>{{ __('ui.project.edit') }}</span>
                    </a>
                @endif
                @can('moderate')
                    @if ($canModerateContent && !empty($project['id']) && $showQueue)
                        <button type="button" class="action-menu__item" data-admin-queue data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.queue', $project['id']) }}">
                            <i data-lucide="alert-circle" class="icon"></i>
                            <span>{{ __('ui.moderation.queue') }}</span>
                        </button>
                    @endif
                @endcan
                @if (!$isViewerAuthor)
                    <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="post" data-report-id="{{ $project['id'] ?? $project['slug'] }}" data-report-url="{{ route('project', $project['slug']) }}">
                        <i data-lucide="flag" class="icon"></i>
                        <span>{{ __('ui.report.title') }}</span>
                    </button>
                @endif
                @if ($canDelete && !empty($project['id']))
                    <button type="button" class="action-menu__item action-menu__item--danger" data-author-delete data-author-delete-url="{{ route('posts.delete', $project['id']) }}" data-author-delete-redirect="{{ route('feed') }}">
                        <i data-lucide="trash-2" class="icon"></i>
                        <span>{{ __('ui.project.delete') }}</span>
                    </button>
                @endif
            </div>
        </div>
    @endif
    @can('moderate')
        @if (!empty($project['id']))
            @if ($canModerateContent)
                <div class="admin-controls admin-controls--card" data-admin-controls>
                <span class="admin-report-count {{ $reportCount > 0 ? '' : 'is-empty' }}">
                    <i data-lucide="flag" class="icon"></i>
                    <span>{{ $reportCount }}</span>
                </span>
                <span class="admin-report-count admin-report-count--points {{ $reportPoints > 0 ? '' : 'is-empty' }}" title="{{ __('ui.admin.report_points') }}">
                    <i data-lucide="zap" class="icon"></i>
                    <span>{{ $reportPoints }}</span>
                </span>
                    @if ($isAdminModerator)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('admin.posts.delete', $project['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                            <i data-lucide="trash-2" class="icon"></i>
                        </button>
                    @endif
                    @if ($showHide)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.hide', $project['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                            <i data-lucide="eye-off" class="icon"></i>
                        </button>
                    @endif
                    @if ($showRestore)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.restore', $project['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                            <i data-lucide="eye" class="icon"></i>
                        </button>
                    @endif
                    @if ($moderationNsfwPending)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger icon-btn--label" data-admin-nsfw data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.nsfw', $project['id']) }}" aria-label="{{ __('ui.moderation.nsfw') }}" title="{{ __('ui.moderation.nsfw') }}">
                            NSFW
                        </button>
                    @endif
                    @if (!$isViewerAuthor)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="post" data-report-id="{{ $project['id'] }}" data-report-url="{{ route('project', $project['slug']) }}" aria-label="{{ __('ui.report.flag') }}">
                            <i data-lucide="flag" class="icon"></i>
                        </button>
                    @endif
                </div>
            @endif
        @endif
    @endcan
    <h3 class="post-title"><a href="{{ route('project', $project['slug']) }}">{{ $project['title'] }}</a></h3>
    @if ($hasCarousel)
        <div class="post-cover post-carousel {{ $isNsfw ? 'is-nsfw' : '' }}" data-carousel data-nsfw-cover aria-label="{{ __('ui.js.carousel_label') }}">
            <button type="button" class="icon-btn post-carousel__control post-carousel__control--prev" data-carousel-prev aria-label="{{ __('ui.js.carousel_prev') }}">
                <i data-lucide="chevron-left" class="icon"></i>
            </button>
            <div class="post-carousel__track" data-carousel-track>
                @foreach ($coverImages as $image)
                    <div class="post-carousel__slide" style="--carousel-bg: url('{{ $image }}');">
                        <img src="{{ $image }}" alt="{{ $project['title'] }}" data-fallback="{{ asset('images/logo-black.svg') }}" draggable="false">
                    </div>
                @endforeach
            </div>
            <button type="button" class="icon-btn post-carousel__control post-carousel__control--next" data-carousel-next aria-label="{{ __('ui.js.carousel_next') }}">
                <i data-lucide="chevron-right" class="icon"></i>
            </button>
            <div class="post-carousel__dots" data-carousel-dots></div>
            @if ($isNsfw)
                <button type="button" class="nsfw-reveal" data-nsfw-reveal>{{ __('ui.project.nsfw_reveal') }}</button>
            @endif
        </div>
    @else
        <div class="post-cover {{ $isNsfw ? 'is-nsfw' : '' }}" data-nsfw-cover>
            <img class="{{ $isCoverPlaceholder ? 'is-placeholder' : '' }}" src="{{ $coverUrl }}" alt="{{ $project['title'] }}" data-fallback="{{ asset('images/logo-black.svg') }}" draggable="false">
            @if ($isNsfw)
                <button type="button" class="nsfw-reveal" data-nsfw-reveal>{{ __('ui.project.nsfw_reveal') }}</button>
            @endif
        </div>
    @endif
    @if (!empty($previewText))
        <p class="post-context">{{ $previewText }}</p>
    @endif
    <div class="post-tags">
        @if ($isNsfw)
            <span class="chip chip--nsfw">NSFW</span>
        @endif
        @can('moderate')
            @if ($moderationStatus !== 'approved' || $isHidden)
                <span class="chip chip--moderation chip--{{ $moderationStatus }}">{{ __('ui.moderation.status_' . $moderationStatus) }}</span>
            @endif
        @endcan
        @foreach ($visibleTags as $tag)
            <span class="chip chip--tag">{{ $tag }}</span>
        @endforeach
        @if ($extraTags > 0)
            <span class="chip chip--count">+{{ $extraTags }}</span>
        @endif
    </div>
    <div class="post-actions">
        <div class="action-icons">
            <button type="button" class="icon-action {{ !empty($project['is_upvoted']) ? 'is-active' : '' }}" data-action="upvote" data-project-slug="{{ $project['slug'] }}" data-upvoted="{{ !empty($project['is_upvoted']) ? '1' : '0' }}" aria-label="{{ __('ui.project.upvote') }}">
                <i data-lucide="arrow-up" class="icon"></i>
            </button>
            <a class="icon-action" href="{{ route('project', $project['slug']) }}?tab=comments" aria-label="{{ __('ui.project.tab_comments') }}">
                <i data-lucide="message-circle" class="icon"></i>
                <span class="action-count">{{ $commentCount }}</span>
            </a>
            <button type="button" class="icon-action {{ !empty($project['is_saved']) ? 'is-active' : '' }}" data-action="save" data-project-slug="{{ $project['slug'] }}" data-saved="{{ !empty($project['is_saved']) ? '1' : '0' }}" aria-label="{{ __('ui.project.save') }}">
                <i data-lucide="bookmark" class="icon"></i>
            </button>
        </div>
        <a class="cta-btn" href="{{ route('project', $project['slug']) }}" data-read-cta>{{ __('ui.card.read') }}</a>
    </div>
    <div class="read-mark" data-read-progress-label hidden>{{ __('ui.card.read_mark') }}</div>
    <div class="read-progress" data-read-progress hidden></div>
</article>

