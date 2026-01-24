@php
    $type = $item['type'] ?? '';
    $data = $item['data'] ?? [];
@endphp

@if (in_array($type, ['project', 'question'], true))
    @include('partials.feed-item', ['item' => $item])
@elseif ($type === 'comment')
    @php
        $author = $data['author'] ?? [];
        $authorName = $author['name'] ?? __('ui.project.anonymous');
        $authorSlug = $author['slug'] ?? \Illuminate\Support\Str::slug($authorName);
        $authorRoleKey = strtolower($author['role'] ?? 'user');
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
        $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
        $avatarPath = $author['avatar'] ?? 'images/avatar-default.svg';
        $avatarIsDefault = trim($avatarPath, '/') === 'images/avatar-default.svg';
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : asset(ltrim($avatarPath, '/'));
        $postTitle = $data['post_title'] ?? $data['post_slug'] ?? '';
        $postUrl = $data['post_url'] ?? null;
        $commentText = $data['text'] ?? '';
        $commentSection = $data['section'] ?? null;
        $commentTime = $data['time'] ?? '';
        $reportCount = (int) ($data['report_count'] ?? 0);
        $reportPoints = (int) ($data['report_points'] ?? $reportCount);
        $moderationStatus = strtolower((string) ($data['moderation_status'] ?? 'approved'));
        $isHidden = !empty($data['is_hidden']);
        $currentModerator = Auth::user();
        $isAdminModerator = $currentModerator?->isAdmin() ?? false;
        $viewerId = $currentModerator?->id;
        $viewerSlug = $currentModerator?->slug ?? '';
        $isSelfContent = $currentModerator
            && (
                (!empty($author['id']) && $viewerId === $author['id'])
                || ($viewerSlug !== '' && $viewerSlug === $authorSlug)
            );
        $canModerateContent = $isAdminModerator || $authorRoleKey !== 'admin';
        $showQueue = $moderationStatus !== 'pending';
        $showHide = $moderationStatus !== 'hidden';
        $showRestore = $moderationStatus !== 'approved';
    @endphp
    <article class="card admin-moderation-card" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="comment">
        <div class="admin-moderation__meta">
            <img class="avatar" src="{{ $avatarUrl }}" alt="{{ $authorName }}" @if ($avatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $authorName }}" @endif>
            @if (!empty($authorSlug))
                <a class="post-author" href="{{ route('profile.show', $authorSlug) }}">{{ $authorName }}</a>
            @else
                <span class="post-author">{{ $authorName }}</span>
            @endif
            <span class="badge badge--{{ $authorRoleKey }}">{{ $authorRoleLabel }}</span>
            @if (!empty($postTitle))
                <span class="dot">&bull;</span>
                @if ($postUrl)
                    <a class="muted" href="{{ $postUrl }}" target="_blank" rel="noopener">{{ $postTitle }}</a>
                @else
                    <span class="muted">{{ $postTitle }}</span>
                @endif
            @endif
            @if ($commentTime !== '')
                <span class="dot">&bull;</span>
                <span class="muted">{{ $commentTime }}</span>
            @endif
            @if ($commentSection)
                <span class="chip chip--comment">{{ $commentSection }}</span>
            @endif
            @if ($moderationStatus !== 'approved' || $isHidden)
                <span class="chip chip--moderation chip--{{ $moderationStatus }}">{{ __('ui.moderation.status_' . $moderationStatus) }}</span>
            @endif
        </div>
        <div class="admin-moderation__body">{{ $commentText }}</div>
        <div class="admin-moderation__footer">
            <div class="admin-report-stats">
                <span class="admin-report-count {{ $reportCount > 0 ? '' : 'is-empty' }}" title="{{ __('ui.admin.report_count') }}">
                    <i data-lucide="flag" class="icon"></i>
                    <span>{{ $reportCount }}</span>
                </span>
                <span class="admin-report-count admin-report-count--points {{ $reportPoints > 0 ? '' : 'is-empty' }}" title="{{ __('ui.admin.report_points') }}">
                    <i data-lucide="zap" class="icon"></i>
                    <span>{{ $reportPoints }}</span>
                </span>
            </div>
            @if ($canModerateContent && !empty($data['id']))
                <div class="admin-controls" data-admin-controls>
                    @if ($isAdminModerator)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="comment" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('admin.comments.delete', $data['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                            <i data-lucide="trash-2" class="icon"></i>
                        </button>
                    @endif
                    @if ($showQueue)
                        <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="comment" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.comments.queue', $data['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                            <i data-lucide="alert-circle" class="icon"></i>
                        </button>
                    @endif
                    @if ($showHide)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="comment" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.comments.hide', $data['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                            <i data-lucide="eye-off" class="icon"></i>
                        </button>
                    @endif
                    @if ($showRestore)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="comment" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.comments.restore', $data['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                            <i data-lucide="eye" class="icon"></i>
                        </button>
                    @endif
                    @if (!$isSelfContent)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="comment" data-report-id="{{ $data['id'] }}" data-report-url="{{ $postUrl ?? url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                            <i data-lucide="flag" class="icon"></i>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </article>
@elseif ($type === 'review')
    @php
        $author = $data['author'] ?? [];
        $authorName = $author['name'] ?? __('ui.project.anonymous');
        $authorSlug = $author['slug'] ?? \Illuminate\Support\Str::slug($authorName);
        $authorRoleKey = strtolower($author['role'] ?? 'user');
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
        $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
        $avatarPath = $author['avatar'] ?? 'images/avatar-default.svg';
        $avatarIsDefault = trim($avatarPath, '/') === 'images/avatar-default.svg';
        $avatarUrl = \Illuminate\Support\Str::startsWith($avatarPath, ['http://', 'https://'])
            ? $avatarPath
            : asset(ltrim($avatarPath, '/'));
        $postTitle = $data['post_title'] ?? $data['post_slug'] ?? '';
        $postUrl = $data['post_url'] ?? null;
        $reviewTime = $data['time'] ?? '';
        $reportCount = (int) ($data['report_count'] ?? 0);
        $reportPoints = (int) ($data['report_points'] ?? $reportCount);
        $moderationStatus = strtolower((string) ($data['moderation_status'] ?? 'approved'));
        $isHidden = !empty($data['is_hidden']);
        $currentModerator = Auth::user();
        $isAdminModerator = $currentModerator?->isAdmin() ?? false;
        $viewerId = $currentModerator?->id;
        $viewerSlug = $currentModerator?->slug ?? '';
        $isSelfContent = $currentModerator
            && (
                (!empty($author['id']) && $viewerId === $author['id'])
                || ($viewerSlug !== '' && $viewerSlug === $authorSlug)
            );
        $canModerateContent = $isAdminModerator || $authorRoleKey !== 'admin';
        $showQueue = $moderationStatus !== 'pending';
        $showHide = $moderationStatus !== 'hidden';
        $showRestore = $moderationStatus !== 'approved';
    @endphp
    <article class="card admin-moderation-card" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="review">
        <div class="admin-moderation__meta">
            <img class="avatar" src="{{ $avatarUrl }}" alt="{{ $authorName }}" @if ($avatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $authorName }}" @endif>
            @if (!empty($authorSlug))
                <a class="post-author" href="{{ route('profile.show', $authorSlug) }}">{{ $authorName }}</a>
            @else
                <span class="post-author">{{ $authorName }}</span>
            @endif
            <span class="badge badge--{{ $authorRoleKey }}">{{ $authorRoleLabel }}</span>
            @if (!empty($postTitle))
                <span class="dot">&bull;</span>
                @if ($postUrl)
                    <a class="muted" href="{{ $postUrl }}" target="_blank" rel="noopener">{{ $postTitle }}</a>
                @else
                    <span class="muted">{{ $postTitle }}</span>
                @endif
            @endif
            @if ($reviewTime !== '')
                <span class="dot">&bull;</span>
                <span class="muted">{{ $reviewTime }}</span>
            @endif
            @if ($moderationStatus !== 'approved' || $isHidden)
                <span class="chip chip--moderation chip--{{ $moderationStatus }}">{{ __('ui.moderation.status_' . $moderationStatus) }}</span>
            @endif
        </div>
        <div class="admin-moderation__body">
            <div class="admin-moderation__review">
                <div class="admin-moderation__label">{{ __('ui.project.review_improve') }}</div>
                <div>{{ $data['improve'] ?? '' }}</div>
            </div>
            <div class="admin-moderation__review">
                <div class="admin-moderation__label">{{ __('ui.project.review_why') }}</div>
                <div>{{ $data['why'] ?? '' }}</div>
            </div>
            <div class="admin-moderation__review">
                <div class="admin-moderation__label">{{ __('ui.project.review_how') }}</div>
                <div>{{ $data['how'] ?? '' }}</div>
            </div>
        </div>
        <div class="admin-moderation__footer">
            <div class="admin-report-stats">
                <span class="admin-report-count {{ $reportCount > 0 ? '' : 'is-empty' }}" title="{{ __('ui.admin.report_count') }}">
                    <i data-lucide="flag" class="icon"></i>
                    <span>{{ $reportCount }}</span>
                </span>
                <span class="admin-report-count admin-report-count--points {{ $reportPoints > 0 ? '' : 'is-empty' }}" title="{{ __('ui.admin.report_points') }}">
                    <i data-lucide="zap" class="icon"></i>
                    <span>{{ $reportPoints }}</span>
                </span>
            </div>
            @if ($canModerateContent && !empty($data['id']))
                <div class="admin-controls" data-admin-controls>
                    @if ($isAdminModerator)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="review" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('admin.reviews.delete', $data['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                            <i data-lucide="trash-2" class="icon"></i>
                        </button>
                    @endif
                    @if ($showQueue)
                        <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="review" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.reviews.queue', $data['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                            <i data-lucide="alert-circle" class="icon"></i>
                        </button>
                    @endif
                    @if ($showHide)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="review" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.reviews.hide', $data['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                            <i data-lucide="eye-off" class="icon"></i>
                        </button>
                    @endif
                    @if ($showRestore)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="review" data-admin-id="{{ $data['id'] }}" data-admin-url="{{ route('moderation.reviews.restore', $data['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                            <i data-lucide="eye" class="icon"></i>
                        </button>
                    @endif
                    @if (!$isSelfContent)
                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="review" data-report-id="{{ $data['id'] }}" data-report-url="{{ $postUrl ?? url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                            <i data-lucide="flag" class="icon"></i>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </article>
@endif
