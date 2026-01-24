@php
    $commentRoleKey = strtolower($comment['role'] ?? 'user');
    $commentRoleKey = in_array($commentRoleKey, $roleKeys, true) ? $commentRoleKey : 'user';
    $commentRoleLabel = __('ui.roles.' . $commentRoleKey);
    $commentAuthor = $comment['author'] ?? __('ui.project.anonymous');
    $commentAuthorSlug = $comment['author_slug'] ?? \Illuminate\Support\Str::slug($commentAuthor);
    $postAuthorSlug = $postAuthorSlug ?? '';
    $isAuthorComment = $postAuthorSlug && $commentAuthorSlug === $postAuthorSlug;
    $commentAnchor = !empty($comment['id']) ? 'comment-' . $comment['id'] : 'comment-' . ($commentIndex ?? 0);
    $commentPreview = \Illuminate\Support\Str::limit((string) ($comment['text'] ?? ''), 140);
    $commentReplies = $comment['replies'] ?? [];
    $commentAvatarPath = $comment['avatar'] ?? 'images/avatar-default.svg';
    $commentAvatarIsDefault = trim($commentAvatarPath, '/') === 'images/avatar-default.svg';
    $commentAvatarUrl = \Illuminate\Support\Str::startsWith($commentAvatarPath, ['http://', 'https://'])
        ? $commentAvatarPath
        : asset(ltrim($commentAvatarPath, '/'));
    $commentScore = (int) ($comment['useful'] ?? 0);
    $commentModerationStatus = strtolower((string) ($comment['moderation_status'] ?? 'approved'));
    $commentIsHidden = !empty($comment['is_hidden']);
    $viewer = Auth::user();
    $viewerId = $viewer?->id;
    $viewerSlug = $viewer?->slug ?? '';
    $isCommentOwner = $viewer
        && (
            (!empty($comment['user_id']) && $viewerId === $comment['user_id'])
            || ($viewerSlug !== '' && $viewerSlug === $commentAuthorSlug)
        );
@endphp

<div id="{{ $commentAnchor }}" class="comment comment--threaded" data-comment-item data-comment-order="{{ $commentIndex ?? 0 }}" data-comment-useful="{{ $commentScore }}" data-comment-created="{{ $comment['created_at'] ?? '' }}" data-comment-id="{{ $comment['id'] ?? '' }}" data-comment-anchor="{{ $commentAnchor }}" data-comment-author="{{ $commentAuthor }}" data-comment-preview="{{ $commentPreview }}" data-moderation-scope data-moderation-status="{{ $commentModerationStatus }}" data-moderation-type="comment">
    <div class="comment-vote">
        <button type="button" class="vote-btn" data-comment-vote="up" aria-label="{{ __('ui.js.comment_upvote') }}" aria-pressed="false">
            <i data-lucide="arrow-up" class="icon"></i>
        </button>
        <span class="vote-count">{{ $commentScore }}</span>
        <button type="button" class="vote-btn" data-comment-vote="down" aria-label="{{ __('ui.js.comment_downvote') }}" aria-pressed="false">
            <i data-lucide="arrow-down" class="icon"></i>
        </button>
    </div>
    <div class="comment-content">
        <div class="comment-meta">
            <img class="avatar" src="{{ $commentAvatarUrl }}" alt="{{ $commentAuthor }}" @if ($commentAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $commentAuthor }}" @endif>
            @if (!empty($commentAuthorSlug))
                <a class="post-author" href="{{ route('profile.show', $commentAuthorSlug) }}">{{ $commentAuthor }}</a>
            @else
                <span class="post-author">{{ $commentAuthor }}</span>
            @endif
            <span class="badge badge--{{ $commentRoleKey }}">{{ $commentRoleLabel }}</span>
            @if ($isAuthorComment)
                <span class="badge badge--author">{{ __('ui.project.author_badge') }}</span>
            @endif
            <span class="comment-time">{{ $comment['time'] ?? '' }}</span>
            @if (!empty($comment['section']))
                <span class="chip chip--comment">{{ $comment['section'] }}</span>
            @endif
            @can('moderate')
                @if ($commentModerationStatus !== 'approved' || $commentIsHidden)
                    <span class="chip chip--moderation chip--{{ $commentModerationStatus }}">{{ __('ui.moderation.status_' . $commentModerationStatus) }}</span>
                @endif
            @endcan
            @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                @if (!$isCommentOwner)
                    <div class="action-menu action-menu--inline" data-action-menu-container>
                        <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                            <i data-lucide="more-horizontal" class="icon"></i>
                        </button>
                        <div class="action-menu__panel" role="menu" data-action-menu hidden>
                            <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="comment" data-report-id="{{ $comment['id'] ?? $commentIndex ?? 0 }}" data-report-url="{{ url()->current() }}">
                                <i data-lucide="flag" class="icon"></i>
                                <span>{{ __('ui.report.title') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
            @endif
            @can('moderate')
                @if (!empty($comment['id']))
                    @php
                        $currentModerator = Auth::user();
                        $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                        $canModerateComment = $isAdminModerator || $commentRoleKey !== 'admin';
                        $showQueue = $commentModerationStatus !== 'pending';
                        $showHide = $commentModerationStatus !== 'hidden';
                        $showRestore = $commentModerationStatus !== 'approved';
                    @endphp
                    @if ($canModerateComment)
                        <span class="comment-admin">
                            @if ($isAdminModerator)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="comment" data-admin-id="{{ $comment['id'] }}" data-admin-url="{{ route('admin.comments.delete', $comment['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                                    <i data-lucide="trash-2" class="icon"></i>
                                </button>
                            @endif
                            @if ($showQueue)
                                <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="comment" data-admin-id="{{ $comment['id'] }}" data-admin-url="{{ route('moderation.comments.queue', $comment['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                                    <i data-lucide="alert-circle" class="icon"></i>
                                </button>
                            @endif
                            @if ($showHide)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="comment" data-admin-id="{{ $comment['id'] }}" data-admin-url="{{ route('moderation.comments.hide', $comment['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                                    <i data-lucide="eye-off" class="icon"></i>
                                </button>
                            @endif
                            @if ($showRestore)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="comment" data-admin-id="{{ $comment['id'] }}" data-admin-url="{{ route('moderation.comments.restore', $comment['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                                    <i data-lucide="eye" class="icon"></i>
                                </button>
                            @endif
                            @if (!$isCommentOwner)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="comment" data-report-id="{{ $comment['id'] }}" data-report-url="{{ url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                                    <i data-lucide="flag" class="icon"></i>
                                </button>
                            @endif
                        </span>
                    @endif
                @endif
            @endcan
        </div>
        <p class="comment-body">{{ $comment['text'] ?? '' }}</p>
        <div class="comment-actions">
            <button type="button" class="comment-action" data-comment-reply aria-label="{{ __('ui.qa.reply') }}">
                <i data-lucide="corner-up-left" class="icon"></i>
            </button>
            <button type="button" class="comment-action" data-comment-share aria-label="{{ __('ui.project.share') }}">
                <i data-lucide="share-2" class="icon"></i>
            </button>
        </div>
        @if (!empty($commentReplies))
            <div class="comment-replies" data-comment-replies>
                @foreach ($commentReplies as $reply)
                    @php
                        $replyName = $reply['author']['name'] ?? __('ui.project.anonymous');
                        $replySlug = $reply['author']['slug'] ?? \Illuminate\Support\Str::slug($replyName);
                        $replyAvatar = $reply['author']['avatar'] ?? 'images/avatar-default.svg';
                        $replyAvatarIsDefault = trim($replyAvatar, '/') === 'images/avatar-default.svg';
                        $replyAvatarUrl = \Illuminate\Support\Str::startsWith($replyAvatar, ['http://', 'https://'])
                            ? $replyAvatar
                            : asset(ltrim($replyAvatar, '/'));
                        $replyRoleKey = strtolower($reply['author']['role'] ?? 'user');
                        $replyRoleKey = in_array($replyRoleKey, $roleKeys, true) ? $replyRoleKey : 'user';
                        $replyRoleLabel = __('ui.roles.' . $replyRoleKey);
                        $replyScore = (int) ($reply['useful'] ?? 0);
                        $replyAnchor = !empty($reply['id'])
                            ? 'comment-' . $reply['id']
                            : $commentAnchor . '-reply-' . $loop->index;
                        $replyPreview = \Illuminate\Support\Str::limit((string) ($reply['text'] ?? ''), 140);
                        $replyIsAuthor = $postAuthorSlug && $replySlug === $postAuthorSlug;
                        $replyModerationStatus = strtolower((string) ($reply['moderation_status'] ?? 'approved'));
                        $replyIsHidden = !empty($reply['is_hidden']);
                        $isReplyOwner = $viewer
                            && (
                                (!empty($reply['author']['id']) && $viewerId === $reply['author']['id'])
                                || ($viewerSlug !== '' && $viewerSlug === $replySlug)
                            );
                    @endphp
                    <div id="{{ $replyAnchor }}" class="comment comment--reply" data-comment-useful="{{ $replyScore }}" data-comment-id="{{ $reply['id'] ?? '' }}" data-comment-anchor="{{ $replyAnchor }}" data-comment-author="{{ $replyName }}" data-comment-preview="{{ $replyPreview }}" data-moderation-scope data-moderation-status="{{ $replyModerationStatus }}" data-moderation-type="comment">
                        <div class="comment-vote">
                            <button type="button" class="vote-btn" data-comment-vote="up" aria-label="{{ __('ui.js.comment_upvote') }}" aria-pressed="false">
                                <i data-lucide="arrow-up" class="icon"></i>
                            </button>
                            <span class="vote-count">{{ $replyScore }}</span>
                            <button type="button" class="vote-btn" data-comment-vote="down" aria-label="{{ __('ui.js.comment_downvote') }}" aria-pressed="false">
                                <i data-lucide="arrow-down" class="icon"></i>
                            </button>
                        </div>
                        <div class="comment-content">
                            <div class="comment-meta">
                                <img class="avatar" src="{{ $replyAvatarUrl }}" alt="{{ $replyName }}" @if ($replyAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $replyName }}" @endif>
                                @if (!empty($replySlug))
                                    <a class="post-author" href="{{ route('profile.show', $replySlug) }}">{{ $replyName }}</a>
                                @else
                                    <span class="post-author">{{ $replyName }}</span>
                                @endif
                                <span class="badge badge--{{ $replyRoleKey }}">{{ $replyRoleLabel }}</span>
                                @if ($replyIsAuthor)
                                    <span class="badge badge--author">{{ __('ui.project.author_badge') }}</span>
                                @endif
                                <span class="comment-time">{{ $reply['time'] ?? '' }}</span>
                                @can('moderate')
                                    @if ($replyModerationStatus !== 'approved' || $replyIsHidden)
                                        <span class="chip chip--moderation chip--{{ $replyModerationStatus }}">{{ __('ui.moderation.status_' . $replyModerationStatus) }}</span>
                                    @endif
                                @endcan
                                @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                                    @if (!$isReplyOwner)
                                        <div class="action-menu action-menu--inline" data-action-menu-container>
                                            <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                                                <i data-lucide="more-horizontal" class="icon"></i>
                                            </button>
                                            <div class="action-menu__panel" role="menu" data-action-menu hidden>
                                                <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="comment" data-report-id="{{ $reply['id'] ?? $replyAnchor }}" data-report-url="{{ url()->current() }}">
                                                    <i data-lucide="flag" class="icon"></i>
                                                    <span>{{ __('ui.report.title') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                                @can('moderate')
                                    @if (!empty($reply['id']))
                                        @php
                                            $currentModerator = Auth::user();
                                            $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                                            $canModerateReply = $isAdminModerator || $replyRoleKey !== 'admin';
                                            $showQueue = $replyModerationStatus !== 'pending';
                                            $showHide = $replyModerationStatus !== 'hidden';
                                            $showRestore = $replyModerationStatus !== 'approved';
                                        @endphp
                                        @if ($canModerateReply)
                                            <span class="comment-admin">
                                                @if ($isAdminModerator)
                                                    <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="comment" data-admin-id="{{ $reply['id'] }}" data-admin-url="{{ route('admin.comments.delete', $reply['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                                                        <i data-lucide="trash-2" class="icon"></i>
                                                    </button>
                                                @endif
                                                @if ($showQueue)
                                                    <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="comment" data-admin-id="{{ $reply['id'] }}" data-admin-url="{{ route('moderation.comments.queue', $reply['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                                                        <i data-lucide="alert-circle" class="icon"></i>
                                                    </button>
                                                @endif
                                                @if ($showHide)
                                                    <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="comment" data-admin-id="{{ $reply['id'] }}" data-admin-url="{{ route('moderation.comments.hide', $reply['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                                                        <i data-lucide="eye-off" class="icon"></i>
                                                    </button>
                                                @endif
                                                @if ($showRestore)
                                                    <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="comment" data-admin-id="{{ $reply['id'] }}" data-admin-url="{{ route('moderation.comments.restore', $reply['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                                                        <i data-lucide="eye" class="icon"></i>
                                                    </button>
                                                @endif
                                                @if (!$isReplyOwner)
                                                    <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="comment" data-report-id="{{ $reply['id'] }}" data-report-url="{{ url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                                                        <i data-lucide="flag" class="icon"></i>
                                                    </button>
                                                @endif
                                            </span>
                                        @endif
                                    @endif
                                @endcan
                            </div>
                            <p class="comment-body">{{ $reply['text'] ?? '' }}</p>
                            <div class="comment-actions">
                                <button type="button" class="comment-action" data-comment-reply aria-label="{{ __('ui.qa.reply') }}">
                                    <i data-lucide="corner-up-left" class="icon"></i>
                                </button>
                                <button type="button" class="comment-action" data-comment-share aria-label="{{ __('ui.project.share') }}">
                                    <i data-lucide="share-2" class="icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

