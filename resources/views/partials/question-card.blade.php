@php
    $questionTags = $question['tags'] ?? [];
    $visibleTags = array_slice($questionTags, 0, 4);
    $extraTags = max(count($questionTags) - count($visibleTags), 0);
    $authorName = $question['author']['name'] ?? __('ui.project.anonymous');
    $avatarPath = $question['author']['avatar'] ?? 'images/avatar-default.svg';
    $avatarIsDefault = trim($avatarPath, '/') === 'images/avatar-default.svg';
    $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
        ? $avatarPath
        : asset(ltrim($avatarPath, '/'));
    $authorSlug = $question['author']['slug'] ?? \Illuminate\Support\Str::slug($authorName);
    $viewer = Auth::user();
    $viewerId = $viewer?->id;
    $viewerSlug = $viewer?->slug ?? '';
    $isViewerAuthor = $viewer
        && (
            (!empty($question['author_id']) && $viewerId === $question['author_id'])
            || ($viewerSlug !== '' && $viewerSlug === $authorSlug)
        );
    $authorRoleKey = strtolower($question['author']['role'] ?? 'user');
    $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
    $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
    $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
    $publishedMinutes = (int) ($question['published_minutes'] ?? 0);
    $published = $question['time']
        ?? ($publishedMinutes ? now()->subMinutes($publishedMinutes)->diffForHumans() : __('ui.project.today'));
    $score = (int) ($question['score'] ?? 0);
    $replies = (int) ($question['replies'] ?? count($question['answers'] ?? []));
    $body = $question['body'] ?? $question['body_html'] ?? '';
    $preview = trim(strip_tags((string) $body));
    $preview = $preview !== '' ? \Illuminate\Support\Str::limit($preview, 600) : '';
    $reportCount = (int) ($question['report_count'] ?? 0);
    $reportPoints = (int) ($question['report_points'] ?? $reportCount);
    $edited = !empty($question['edited']);
    $editedAt = $question['edited_at'] ?? null;
    $editedBy = $question['edited_by'] ?? [];
    $editedByName = $editedBy['name'] ?? '';
    $showEdited = $edited && $editedAt && $editedByName !== '';
    $moderationStatus = strtolower((string) ($question['moderation_status'] ?? 'approved'));
    $isHidden = !empty($question['is_hidden']);
    $moderationNsfwPending = !empty($question['moderation_nsfw_pending']);
@endphp
<article class="card post-card question-card" data-feed-card data-feed-type="questions" data-feed-key="question:{{ $question['slug'] }}" data-project-slug="{{ $question['slug'] }}" data-score="{{ $score }}" data-published="{{ $publishedMinutes }}" data-read-min="0" data-tags="{{ collect($questionTags)->map(fn ($tag) => \Illuminate\Support\Str::slug($tag))->join(',') }}" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="post">
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
        <span>{{ __('ui.qa.answers_count', ['count' => $replies]) }}</span>
    </div>
    @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
        @php
            $currentUser = Auth::user();
            $currentSlug = $currentUser?->slug ?? '';
            $isAuthor = $currentUser
                && (
                    (!empty($question['author_id']) && $currentUser->id === $question['author_id'])
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
                @if ($canEdit && !empty($question['id']))
                    <a class="action-menu__item" href="{{ route('posts.edit', $question['slug']) }}">
                        <i data-lucide="pencil" class="icon"></i>
                        <span>{{ __('ui.project.edit') }}</span>
                    </a>
                @endif
                @if (!$isAuthor)
                    <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="question" data-report-id="{{ $question['id'] ?? $question['slug'] }}" data-report-url="{{ route('questions.show', $question['slug']) }}">
                        <i data-lucide="flag" class="icon"></i>
                        <span>{{ __('ui.report.title') }}</span>
                    </button>
                @endif
                @if ($canDelete && !empty($question['id']))
                    <button type="button" class="action-menu__item action-menu__item--danger" data-author-delete data-author-delete-url="{{ route('posts.delete', $question['id']) }}" data-author-delete-redirect="{{ route('feed') }}">
                        <i data-lucide="trash-2" class="icon"></i>
                        <span>{{ __('ui.project.delete') }}</span>
                    </button>
                @endif
            </div>
        </div>
    @endif
    @can('moderate')
        @if (!empty($question['id']))
            @php
                $currentModerator = Auth::user();
                $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                $canModerateContent = $isAdminModerator || $authorRoleKey !== 'admin';
                $showQueue = $moderationStatus !== 'pending';
                $showHide = $moderationStatus !== 'hidden';
                $showRestore = $moderationStatus !== 'approved';
            @endphp
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
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('admin.posts.delete', $question['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                            <i data-lucide="trash-2" class="icon"></i>
                        </button>
                    @endif
                    @if ($showQueue)
                        <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.queue', $question['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                            <i data-lucide="alert-circle" class="icon"></i>
                        </button>
                    @endif
                    @if ($showHide)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.hide', $question['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                            <i data-lucide="eye-off" class="icon"></i>
                        </button>
                    @endif
                    @if ($showRestore)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.restore', $question['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                            <i data-lucide="eye" class="icon"></i>
                        </button>
                    @endif
                    @if ($moderationNsfwPending)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger icon-btn--label" data-admin-nsfw data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.nsfw', $question['id']) }}" aria-label="{{ __('ui.moderation.nsfw') }}" title="{{ __('ui.moderation.nsfw') }}">
                            NSFW
                        </button>
                    @endif
                    @if (!$isViewerAuthor)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="question" data-report-id="{{ $question['id'] }}" data-report-url="{{ route('questions.show', $question['slug']) }}" aria-label="{{ __('ui.report.flag') }}">
                            <i data-lucide="flag" class="icon"></i>
                        </button>
                    @endif
                </div>
            @endif
        @endif
    @endcan
    <h3 class="post-title">
        <a href="{{ route('questions.show', $question['slug']) }}">{{ $question['title'] }}</a>
    </h3>
    @if (!empty($preview))
        <p class="post-context">{{ $preview }}</p>
    @endif
    @if (!empty($visibleTags))
        <div class="post-tags">
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
    @endif
    <div class="post-actions">
        <div class="action-icons">
            <button type="button" class="icon-action {{ !empty($question['is_upvoted']) ? 'is-active' : '' }}" data-action="upvote" data-project-slug="{{ $question['slug'] }}" data-upvoted="{{ !empty($question['is_upvoted']) ? '1' : '0' }}" aria-label="{{ __('ui.project.upvote') }}">
                <i data-lucide="arrow-up" class="icon"></i>
            </button>
            <a class="icon-action" href="{{ route('questions.show', $question['slug']) }}" aria-label="{{ __('ui.qa.answers_title') }}">
                <i data-lucide="message-circle" class="icon"></i>
                <span class="action-count">{{ $replies }}</span>
            </a>
            <button type="button" class="icon-action {{ !empty($question['is_saved']) ? 'is-active' : '' }}" data-action="save" data-project-slug="{{ $question['slug'] }}" data-saved="{{ !empty($question['is_saved']) ? '1' : '0' }}" aria-label="{{ __('ui.project.save') }}">
                <i data-lucide="bookmark" class="icon"></i>
            </button>
        </div>
        <a class="cta-btn" href="{{ route('questions.show', $question['slug']) }}" data-read-cta>{{ __('ui.card.read') }}</a>
    </div>
    <div class="read-mark" data-read-progress-label hidden>{{ __('ui.card.read_mark') }}</div>
    <div class="read-progress" data-read-progress hidden></div>
</article>

