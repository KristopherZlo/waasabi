@extends('layouts.app')

@section('title', $question['title'])
@section('page', 'question')

@section('content')
    @php
        $authorName = $question['author']['name'] ?? __('ui.project.anonymous');
        $authorAvatar = $question['author']['avatar'] ?? 'images/avatar-default.svg';
        $authorAvatarIsDefault = trim($authorAvatar, '/') === 'images/avatar-default.svg';
        $avatarUrl = \Illuminate\Support\Str::startsWith($authorAvatar, ['http://', 'https://'])
            ? $authorAvatar
            : asset(ltrim($authorAvatar, '/'));
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $authorRoleKey = strtolower($question['author']['role'] ?? 'user');
        $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
        $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
        $authorSlug = $question['author']['slug'] ?? \Illuminate\Support\Str::slug($authorName);
        $answers = $question['answers'] ?? [];
        $answerTotal = $question['answers_total'] ?? count($answers);
        $answersShown = count($answers);
        $answerCount = $answerTotal;
        $answersHasMore = $answersShown < $answerTotal;
        $body = $question['body'] ?? '';
        $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", $body))));
        $score = $question['score'] ?? 0;
        $edited = !empty($question['edited']);
        $editedAt = $question['edited_at'] ?? null;
        $editedBy = $question['edited_by'] ?? [];
        $editedByName = $editedBy['name'] ?? '';
        $showEdited = $edited && $editedAt && $editedByName !== '';
        $moderationStatus = strtolower((string) ($question['moderation_status'] ?? 'approved'));
        $isHidden = !empty($question['is_hidden']);
    @endphp

    <section class="question-page">
        <div class="question-page__card" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="post">
            <div class="question-page__top">
                <div class="question-page__meta">
                    <img class="avatar avatar--lg" src="{{ $avatarUrl }}" alt="{{ $authorName }}" @if ($authorAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $authorName }}" @endif>
                    <a class="post-author" href="{{ route('profile.show', $authorSlug) }}">{{ $authorName }}</a>
                    <span class="badge badge--{{ $authorRoleKey }}">{{ $authorRoleLabel }}</span>
                    <span class="dot">&bull;</span>
                    <span>{{ $question['time'] ?? __('ui.project.today') }}</span>
                    @if ($showEdited)
                        <span class="dot">&bull;</span>
                        <span class="post-edited">{{ __('ui.project.edited', ['time' => $editedAt, 'user' => $editedByName]) }}</span>
                    @endif
                    <span class="dot">&bull;</span>
                    <span>{{ __('ui.qa.answers_count', ['count' => $answerCount]) }}</span>
                </div>
                <div class="question-page__actions">
                    <button type="button" class="icon-action {{ !empty($question['is_upvoted']) ? 'is-active' : '' }}" data-action="upvote" data-project-slug="{{ $question['slug'] }}" data-upvoted="{{ !empty($question['is_upvoted']) ? '1' : '0' }}" data-base-count="{{ $score }}" aria-label="{{ __('ui.project.upvote') }}">
                        <i data-lucide="arrow-up" class="icon"></i>
                        <span class="action-count">{{ $score }}</span>
                    </button>
                    <button type="button" class="icon-action {{ !empty($question['is_saved']) ? 'is-active' : '' }}" data-action="save" data-project-slug="{{ $question['slug'] }}" data-saved="{{ !empty($question['is_saved']) ? '1' : '0' }}" aria-label="{{ __('ui.project.save') }}">
                        <i data-lucide="bookmark" class="icon"></i>
                    </button>
                    <button type="button" class="ghost-btn ghost-btn--compact" data-share data-share-url="{{ url()->current() }}">
                        <i data-lucide="share-2" class="icon"></i>
                        <span>{{ __('ui.project.share') }}</span>
                    </button>
                    @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                        @php
                            $currentUser = Auth::user();
                            $currentSlug = $currentUser?->slug ?? '';
                            $isAuthorPost = $currentUser
                                && (
                                    (!empty($question['author']['id']) && $currentUser->id === $question['author']['id'])
                                    || ($currentSlug !== '' && $currentSlug === $authorSlug)
                                );
                            $isAdmin = $currentUser?->isAdmin() ?? false;
                            $canEdit = $currentUser && ($isAuthorPost || $isAdmin);
                            $canDelete = $canEdit;
                        @endphp
                        <div class="action-menu" data-action-menu-container>
                            <button class="icon-btn action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                                <i data-lucide="more-horizontal" class="icon"></i>
                            </button>
                            <div class="action-menu__panel" role="menu" data-action-menu hidden>
                                @if ($canEdit && !empty($question['id']))
                                    <a class="action-menu__item" href="{{ route('posts.edit', $question['slug']) }}">
                                        <i data-lucide="pencil" class="icon"></i>
                                        <span>{{ __('ui.project.edit') }}</span>
                                    </a>
                                @endif
                                @if (!$isAuthorPost)
                                    <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="question" data-report-id="{{ $question['id'] ?? $question['slug'] }}" data-report-url="{{ url()->current() }}">
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
                                $isSelfPost = $currentModerator
                                    && (
                                        (!empty($question['author']['id']) && $currentModerator->id === $question['author']['id'])
                                        || (!empty($authorSlug) && ($currentModerator->slug ?? '') === $authorSlug)
                                    );
                                $showQueue = $moderationStatus !== 'pending';
                                $showHide = $moderationStatus !== 'hidden';
                                $showRestore = $moderationStatus !== 'approved';
                            @endphp
                            @if ($canModerateContent)
                                <div class="admin-controls" data-admin-controls>
                                    @if ($isAdminModerator)
                                        <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-delete data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('admin.posts.delete', $question['id']) }}">
                                            <i data-lucide="trash-2" class="icon"></i>
                                            <span>{{ __('ui.admin.delete') }}</span>
                                        </button>
                                    @endif
                                    @if ($showQueue)
                                        <button type="button" class="ghost-btn admin-btn" data-admin-queue data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.queue', $question['id']) }}">
                                            <i data-lucide="alert-circle" class="icon"></i>
                                            <span>{{ __('ui.moderation.queue') }}</span>
                                        </button>
                                    @endif
                                    @if ($showHide)
                                        <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-hide data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.hide', $question['id']) }}">
                                            <i data-lucide="eye-off" class="icon"></i>
                                            <span>{{ __('ui.moderation.hide') }}</span>
                                        </button>
                                    @endif
                                    @if ($showRestore)
                                        <button type="button" class="ghost-btn admin-btn ghost-btn--accent" data-admin-restore data-admin-type="post" data-admin-id="{{ $question['id'] }}" data-admin-url="{{ route('moderation.posts.restore', $question['id']) }}">
                                            <i data-lucide="eye" class="icon"></i>
                                            <span>{{ __('ui.moderation.restore') }}</span>
                                        </button>
                                    @endif
                                    @if (!$isSelfPost)
                                        <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-flag data-report-type="question" data-report-id="{{ $question['id'] }}" data-report-url="{{ url()->current() }}">
                                            <i data-lucide="flag" class="icon"></i>
                                            <span>{{ __('ui.report.flag') }}</span>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        @endif
                    @endcan
                </div>
            </div>
            <h1 class="question-page__title">{{ $question['title'] }}</h1>
            @if (!empty($question['tags']))
                <div class="question-page__tags">
                    @can('moderate')
                        @if ($moderationStatus !== 'approved' || $isHidden)
                            <span class="chip chip--moderation chip--{{ $moderationStatus }}">{{ __('ui.moderation.status_' . $moderationStatus) }}</span>
                        @endif
                    @endcan
                    @foreach ($question['tags'] as $tag)
                        <span class="chip chip--tag">{{ $tag }}</span>
                    @endforeach
                </div>
            @endif
            <div class="question-page__body reading-article reading-article--rich">
                @if (!empty($question['body_html']))
                    {!! $question['body_html'] !!}
                @else
                    @foreach ($paragraphs as $paragraph)
                        <p class="reading-paragraph">{{ $paragraph }}</p>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    <section class="question-page__answers" data-tab-panel="comments">
        <div class="question-page__panel">
            <div class="question-page__panel-head">
                <div class="section-title">{{ __('ui.qa.answers_title') }}</div>
                <div class="tabs comment-sort">
                    <button type="button" class="tab is-active" data-comment-sort="new">{{ __('ui.project.comments_new') }}</button>
                    <button type="button" class="tab" data-comment-sort="best">{{ __('ui.project.comments_best') }}</button>
                </div>
            </div>
            @if ($answerCount === 0)
                <div class="helper">{{ __('ui.qa.answers_empty') }}</div>
            @endif
            <div class="comments qa-thread" data-comment-list data-threaded="true" data-project-slug="{{ $question['slug'] }}" data-comments-endpoint="{{ route('questions.comments.chunk', $question['slug']) }}" data-comments-offset="{{ $answersShown }}" data-comments-total="{{ $answerTotal }}" data-comments-limit="15">
            @foreach ($answers as $answer)
                @include('partials.qa-answer', ['answer' => $answer, 'answerIndex' => $loop->index, 'questionSlug' => $question['slug'], 'roleKeys' => $roleKeys])
            @endforeach
            </div>
            <div class="comment-more-wrap">
                <button type="button" class="comment-more" data-comment-more @if (!$answersHasMore) hidden @endif>
                    <i data-lucide="plus" class="icon"></i>
                    <span>{{ __('ui.project.comments_more') }}</span>
                </button>
            </div>
            @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                <form class="comment-form qa-composer" data-comment-form data-threaded="true" data-project-slug="{{ $question['slug'] }}" data-current-slug="{{ $current_user['slug'] ?? '' }}">
                    <div class="comment-reply-preview" data-reply-preview hidden>
                        <div class="reply-preview__text">
                            <span class="reply-preview__label">{{ __('ui.qa.replying_to') }}</span>
                            <span class="reply-preview__author" data-reply-author></span>
                        </div>
                        <div class="reply-preview__snippet" data-reply-text></div>
                        <button type="button" class="icon-btn icon-btn--sm" data-reply-cancel aria-label="{{ __('ui.qa.reply_cancel') }}">
                            <i data-lucide="x" class="icon"></i>
                        </button>
                    </div>
                    <div class="qa-composer__row">
                        <textarea class="input" placeholder="{{ __('ui.qa.answer_placeholder') }}" data-comment-input></textarea>
                        <button type="button" class="ghost-btn qa-send-btn" data-comment-submit>
                            <i data-lucide="send" class="icon"></i>
                            <span>{{ __('ui.qa.answer_submit') }}</span>
                        </button>
                    </div>
                </form>
            @else
                <div class="list-item">
                    <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.auth.login_title') }}</a>
                </div>
            @endif
        </div>
    </section>
@endsection

