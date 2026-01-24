@php
    $roleKeys = $roleKeys ?? config('roles.order', ['user', 'maker', 'moderator', 'admin']);
    $answerName = $answer['author']['name'] ?? __('ui.project.anonymous');
    $answerSlug = $answer['author']['slug'] ?? \Illuminate\Support\Str::slug($answerName);
    $answerAvatar = $answer['author']['avatar'] ?? 'images/avatar-default.svg';
    $answerAvatarIsDefault = trim($answerAvatar, '/') === 'images/avatar-default.svg';
    $answerAvatarUrl = \Illuminate\Support\Str::startsWith($answerAvatar, ['http://', 'https://'])
        ? $answerAvatar
        : asset(ltrim($answerAvatar, '/'));
    $answerRoleKey = strtolower($answer['author']['role'] ?? 'user');
    $answerRoleKey = in_array($answerRoleKey, $roleKeys, true) ? $answerRoleKey : 'user';
    $answerRoleLabel = __('ui.roles.' . $answerRoleKey);
    $answerScore = (int) ($answer['score'] ?? $answer['useful'] ?? 0);
    $answerReplies = $answer['replies'] ?? [];
    $answerAnchor = !empty($answer['id'])
        ? 'comment-' . $answer['id']
        : 'comment-' . $questionSlug . '-' . ($answerIndex ?? 0);
    $answerPreview = \Illuminate\Support\Str::limit($answer['text'] ?? '', 140);
    $answerModerationStatus = strtolower((string) ($answer['moderation_status'] ?? 'approved'));
    $answerIsHidden = !empty($answer['is_hidden']);
    $viewer = Auth::user();
    $viewerId = $viewer?->id;
    $viewerSlug = $viewer?->slug ?? '';
    $isAnswerOwner = $viewer
        && (
            (!empty($answer['author']['id']) && $viewerId === $answer['author']['id'])
            || ($viewerSlug !== '' && $viewerSlug === $answerSlug)
        );
@endphp

<div id="{{ $answerAnchor }}" class="comment comment--threaded" data-comment-item data-comment-order="{{ $answerIndex ?? 0 }}" data-comment-useful="{{ $answerScore }}" data-comment-id="{{ $answer['id'] ?? '' }}" data-comment-anchor="{{ $answerAnchor }}" data-comment-author="{{ $answerName }}" data-comment-preview="{{ $answerPreview }}" data-moderation-scope data-moderation-status="{{ $answerModerationStatus }}" data-moderation-type="comment" @if (!empty($answer['created_at'])) data-comment-created="{{ $answer['created_at'] }}" @endif>
    <div class="comment-vote">
        <button type="button" class="vote-btn" data-comment-vote="up" aria-label="{{ __('ui.project.upvote') }}" aria-pressed="false">
            <i data-lucide="arrow-up" class="icon"></i>
        </button>
        <span class="vote-count">{{ $answerScore }}</span>
        <button type="button" class="vote-btn" data-comment-vote="down" aria-label="{{ __('ui.qa.downvote') }}" aria-pressed="false">
            <i data-lucide="arrow-down" class="icon"></i>
        </button>
    </div>
    <div class="comment-content">
        <div class="comment-meta">
            <img class="avatar" src="{{ $answerAvatarUrl }}" alt="{{ $answerName }}" @if ($answerAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $answerName }}" @endif>
            @if (!empty($answerSlug))
                <a class="post-author" href="{{ route('profile.show', $answerSlug) }}">{{ $answerName }}</a>
            @else
                <span class="post-author">{{ $answerName }}</span>
            @endif
            <span class="badge badge--{{ $answerRoleKey }}">{{ $answerRoleLabel }}</span>
            <span class="dot">&bull;</span>
            <span class="comment-time">{{ $answer['time'] ?? '' }}</span>
            @can('moderate')
                @if ($answerModerationStatus !== 'approved' || $answerIsHidden)
                    <span class="chip chip--moderation chip--{{ $answerModerationStatus }}">{{ __('ui.moderation.status_' . $answerModerationStatus) }}</span>
                @endif
            @endcan
            @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                @if (!$isAnswerOwner)
                    <div class="action-menu action-menu--inline" data-action-menu-container>
                        <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                            <i data-lucide="more-horizontal" class="icon"></i>
                        </button>
                        <div class="action-menu__panel" role="menu" data-action-menu hidden>
                            <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="comment" data-report-id="{{ $answer['id'] ?? $answerAnchor }}" data-report-url="{{ url()->current() }}">
                                <i data-lucide="flag" class="icon"></i>
                                <span>{{ __('ui.report.title') }}</span>
                            </button>
                        </div>
                    </div>
                @endif
            @endif
            @can('moderate')
                @if (!empty($answer['id']))
                    @php
                        $currentModerator = Auth::user();
                        $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                        $canModerateAnswer = $isAdminModerator || $answerRoleKey !== 'admin';
                        $showQueue = $answerModerationStatus !== 'pending';
                        $showHide = $answerModerationStatus !== 'hidden';
                        $showRestore = $answerModerationStatus !== 'approved';
                    @endphp
                    @if ($canModerateAnswer)
                        <span class="comment-admin">
                            @if ($isAdminModerator)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="comment" data-admin-id="{{ $answer['id'] }}" data-admin-url="{{ route('admin.comments.delete', $answer['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                                    <i data-lucide="trash-2" class="icon"></i>
                                </button>
                            @endif
                            @if ($showQueue)
                                <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="comment" data-admin-id="{{ $answer['id'] }}" data-admin-url="{{ route('moderation.comments.queue', $answer['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                                    <i data-lucide="alert-circle" class="icon"></i>
                                </button>
                            @endif
                            @if ($showHide)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="comment" data-admin-id="{{ $answer['id'] }}" data-admin-url="{{ route('moderation.comments.hide', $answer['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                                    <i data-lucide="eye-off" class="icon"></i>
                                </button>
                            @endif
                            @if ($showRestore)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="comment" data-admin-id="{{ $answer['id'] }}" data-admin-url="{{ route('moderation.comments.restore', $answer['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                                    <i data-lucide="eye" class="icon"></i>
                                </button>
                            @endif
                            @if (!$isAnswerOwner)
                                <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="comment" data-report-id="{{ $answer['id'] }}" data-report-url="{{ url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                                    <i data-lucide="flag" class="icon"></i>
                                </button>
                            @endif
                        </span>
                    @endif
                @endif
            @endcan
        </div>
        <p class="comment-body">{{ $answer['text'] ?? '' }}</p>
        <div class="comment-actions">
            <button type="button" class="comment-action" data-comment-reply aria-label="{{ __('ui.qa.reply') }}">
                <i data-lucide="corner-up-left" class="icon"></i>
            </button>
            <button type="button" class="comment-action" data-comment-share aria-label="{{ __('ui.project.share') }}">
                <i data-lucide="share-2" class="icon"></i>
            </button>
        </div>
        @if (!empty($answerReplies))
            <div class="comment-replies" data-comment-replies>
                @foreach ($answerReplies as $reply)
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
                        $replyScore = (int) ($reply['score'] ?? $reply['useful'] ?? 0);
                        $replyAnchor = !empty($reply['id'])
                            ? 'comment-' . $reply['id']
                            : 'comment-' . $questionSlug . '-' . ($answerIndex ?? 0) . '-' . $loop->index;
                        $replyPreview = \Illuminate\Support\Str::limit($reply['text'] ?? '', 140);
                        $replyModerationStatus = strtolower((string) ($reply['moderation_status'] ?? 'approved'));
                        $replyIsHidden = !empty($reply['is_hidden']);
                        $isReplyOwner = $viewer
                            && (
                                (!empty($reply['author']['id']) && $viewerId === $reply['author']['id'])
                                || ($viewerSlug !== '' && $viewerSlug === $replySlug)
                            );
                    @endphp
                    <div id="{{ $replyAnchor }}" class="comment comment--reply" data-comment-id="{{ $reply['id'] ?? '' }}" data-comment-anchor="{{ $replyAnchor }}" data-comment-author="{{ $replyName }}" data-comment-preview="{{ $replyPreview }}" data-moderation-scope data-moderation-status="{{ $replyModerationStatus }}" data-moderation-type="comment">
                        <div class="comment-vote">
                            <button type="button" class="vote-btn" data-comment-vote="up" aria-label="{{ __('ui.project.upvote') }}" aria-pressed="false">
                                <i data-lucide="arrow-up" class="icon"></i>
                            </button>
                            <span class="vote-count">{{ $replyScore }}</span>
                            <button type="button" class="vote-btn" data-comment-vote="down" aria-label="{{ __('ui.qa.downvote') }}" aria-pressed="false">
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
                                <span class="dot">&bull;</span>
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

