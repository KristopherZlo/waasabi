@extends('layouts.app')

@section('title', $project['title'])
@section('page', 'project')

@section('content')
    @php
        $projectStatusKey = $project['status_key'] ?? 'in-progress';
        $projectTags = $project['tags'] ?? [];
        $authorName = $project['author']['name'] ?? __('ui.project.anonymous');
        $authorAvatarPath = $project['author']['avatar'] ?? 'images/avatar-default.svg';
        $authorAvatarIsDefault = trim($authorAvatarPath, '/') === 'images/avatar-default.svg';
        $authorAvatarUrl = \Illuminate\Support\Str::startsWith($authorAvatarPath, ['http://', 'https://'])
            ? $authorAvatarPath
            : asset(ltrim($authorAvatarPath, '/'));
        $authorRoleKey = strtolower($project['author']['role'] ?? 'user');
        $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $authorRoleKey = in_array($authorRoleKey, $roleKeys, true) ? $authorRoleKey : 'user';
        $authorRoleLabel = __('ui.roles.' . $authorRoleKey);
        $authorSlug = $project['author']['slug'] ?? \Illuminate\Support\Str::slug($authorName);
        $published = $project['published'] ?? __('ui.project.today');
        $readTimeValue = $project['read_time'] ?? null;
        $readTimeLabel = $readTimeValue ? __('ui.project.read_time', ['time' => $readTimeValue]) : '-';
        $subtitle = $project['subtitle'] ?? '';
        $score = $project['score'] ?? 0;
        $edited = !empty($project['edited']);
        $editedAt = $project['edited_at'] ?? null;
        $editedBy = $project['edited_by'] ?? [];
        $editedByName = $editedBy['name'] ?? '';
        $showEdited = $edited && $editedAt && $editedByName !== '';
        $userRole = strtolower($current_user['role'] ?? 'user');
        $userRole = in_array($userRole, $roleKeys, true) ? $userRole : 'user';
        $comments = $project['comments'] ?? [];
        $commentsTotal = $project['comments_total'] ?? count($comments);
        $commentsShown = count($comments);
        $commentsHasMore = $commentsShown < $commentsTotal;
        $placeholderPath = 'images/logo-black.svg';
        $coverPath = $project['cover'] ?? $placeholderPath;
        $isCoverPlaceholder = trim($coverPath, '/') === trim($placeholderPath, '/');
        $coverUrl = \Illuminate\Support\Str::startsWith($coverPath, ['http://', 'https://'])
            ? $coverPath
            : asset(ltrim($coverPath, '/'));
        $coverSources = collect([$coverPath])->merge($project['album'] ?? []);
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
        $coverFallback = $coverImages->first() ?? $coverUrl;
        $statusLabel = $project['status'] ?? __('ui.project.status_in_progress');
        $isNsfw = !empty($project['nsfw']);
        $moderationStatus = strtolower((string) ($project['moderation_status'] ?? 'approved'));
        $isHidden = !empty($project['is_hidden']);
    @endphp

    <section class="article-hero" data-moderation-scope data-moderation-status="{{ $moderationStatus }}" data-moderation-type="post">
        <div class="article-main">
            <h1>{{ $project['title'] }}</h1>
            @if (!empty($subtitle))
                <p class="article-subtitle">{{ $subtitle }}</p>
            @endif
            <div class="article-meta">
                <img class="avatar avatar--lg" src="{{ $authorAvatarUrl }}" alt="{{ $authorName }}" @if ($authorAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $authorName }}" @endif>
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
                <span class="dot">&bull;</span>
                <span class="chip status status--{{ $projectStatusKey }}">{{ $statusLabel }}</span>
            </div>
            <div class="article-tags">
                @if ($isNsfw)
                    <span class="chip chip--nsfw">NSFW</span>
                @endif
                @can('moderate')
                    @if ($moderationStatus !== 'approved' || $isHidden)
                        <span class="chip chip--moderation chip--{{ $moderationStatus }}">{{ __('ui.moderation.status_' . $moderationStatus) }}</span>
                    @endif
                @endcan
                @foreach ($projectTags as $tag)
                    <span class="chip chip--tag">{{ $tag }}</span>
                @endforeach
            </div>
        </div>
        <div class="article-actions">
            <button type="button" class="icon-action {{ !empty($project['is_upvoted']) ? 'is-active' : '' }}" data-action="upvote" data-project-slug="{{ $project['slug'] }}" data-upvoted="{{ !empty($project['is_upvoted']) ? '1' : '0' }}" data-base-count="{{ $score }}" aria-label="{{ __('ui.project.upvote') }}">
                <i data-lucide="arrow-up" class="icon"></i>
                <span class="action-count">{{ $score }}</span>
            </button>
            <button type="button" class="icon-action {{ !empty($project['is_saved']) ? 'is-active' : '' }}" data-action="save" data-project-slug="{{ $project['slug'] }}" data-saved="{{ !empty($project['is_saved']) ? '1' : '0' }}" aria-label="{{ __('ui.project.save') }}">
                <i data-lucide="bookmark" class="icon"></i>
                <span class="action-label">{{ __('ui.project.save') }}</span>
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
                            (!empty($project['author']['id']) && $currentUser->id === $project['author']['id'])
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
                        @if ($canEdit && !empty($project['id']))
                            <a class="action-menu__item" href="{{ route('posts.edit', $project['slug']) }}">
                                <i data-lucide="pencil" class="icon"></i>
                                <span>{{ __('ui.project.edit') }}</span>
                            </a>
                        @endif
                        @if (!$isAuthorPost)
                            <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="post" data-report-id="{{ $project['id'] ?? $project['slug'] }}" data-report-url="{{ url()->current() }}">
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
                    @php
                        $currentModerator = Auth::user();
                        $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                        $canModerateContent = $isAdminModerator || $authorRoleKey !== 'admin';
                        $isSelfPost = $currentModerator
                            && (
                                (!empty($project['author']['id']) && $currentModerator->id === $project['author']['id'])
                                || (!empty($authorSlug) && ($currentModerator->slug ?? '') === $authorSlug)
                            );
                        $showQueue = $moderationStatus !== 'pending';
                        $showHide = $moderationStatus !== 'hidden';
                        $showRestore = $moderationStatus !== 'approved';
                    @endphp
                    @if ($canModerateContent)
                        <div class="admin-controls" data-admin-controls>
                            @if ($isAdminModerator)
                                <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-delete data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('admin.posts.delete', $project['id']) }}">
                                    <i data-lucide="trash-2" class="icon"></i>
                                    <span>{{ __('ui.admin.delete') }}</span>
                                </button>
                            @endif
                            @if ($showQueue)
                                <button type="button" class="ghost-btn admin-btn" data-admin-queue data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.queue', $project['id']) }}">
                                    <i data-lucide="alert-circle" class="icon"></i>
                                    <span>{{ __('ui.moderation.queue') }}</span>
                                </button>
                            @endif
                            @if ($showHide)
                                <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-hide data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.hide', $project['id']) }}">
                                    <i data-lucide="eye-off" class="icon"></i>
                                    <span>{{ __('ui.moderation.hide') }}</span>
                                </button>
                            @endif
                            @if ($showRestore)
                                <button type="button" class="ghost-btn admin-btn ghost-btn--accent" data-admin-restore data-admin-type="post" data-admin-id="{{ $project['id'] }}" data-admin-url="{{ route('moderation.posts.restore', $project['id']) }}">
                                    <i data-lucide="eye" class="icon"></i>
                                    <span>{{ __('ui.moderation.restore') }}</span>
                                </button>
                            @endif
                            @if (!$isSelfPost)
                                <button type="button" class="ghost-btn admin-btn ghost-btn--danger" data-admin-flag data-report-type="post" data-report-id="{{ $project['id'] }}" data-report-url="{{ url()->current() }}">
                                    <i data-lucide="flag" class="icon"></i>
                                    <span>{{ __('ui.report.flag') }}</span>
                                </button>
                            @endif
                        </div>
                    @endif
                @endif
            @endcan
        </div>
    </section>

    <div class="reading-banner" data-reading-banner hidden>
        <div class="reading-banner__text">{{ __('ui.project.continue_prompt') }}</div>
        <div class="reading-banner__actions">
            <button type="button" class="ghost-btn ghost-btn--accent" data-reading-continue>{{ __('ui.project.continue') }}</button>
            <button type="button" class="ghost-btn" data-reading-restart>{{ __('ui.project.restart') }}</button>
        </div>
    </div>

    @if ($coverImages->count() > 1)
        <div class="post-cover post-cover--large post-carousel post-carousel--cover" data-carousel aria-label="{{ __('ui.publish.cover_label') }}">
            <button type="button" class="icon-btn post-carousel__control post-carousel__control--prev" data-carousel-prev aria-label="{{ __('ui.js.carousel_prev') }}">
                <i data-lucide="chevron-left" class="icon"></i>
            </button>
            <div class="post-carousel__track" data-carousel-track>
                @foreach ($coverImages as $index => $coverImage)
                    <div class="post-carousel__slide" style="--carousel-bg: url('{{ $coverImage }}');">
                        <img src="{{ $coverImage }}" alt="{{ $project['title'] }} #{{ $index + 1 }}" data-fallback="{{ asset('images/logo-black.svg') }}">
                    </div>
                @endforeach
            </div>
            <button type="button" class="icon-btn post-carousel__control post-carousel__control--next" data-carousel-next aria-label="{{ __('ui.js.carousel_next') }}">
                <i data-lucide="chevron-right" class="icon"></i>
            </button>
            <div class="post-carousel__dots" data-carousel-dots></div>
        </div>
    @else
        <div class="post-cover post-cover--large">
            <img class="{{ $isCoverPlaceholder ? 'is-placeholder' : '' }}" src="{{ $coverFallback }}" alt="{{ $project['title'] }}" data-fallback="{{ asset('images/logo-black.svg') }}">
        </div>
    @endif

    <div class="reading-progress">
        <div class="reading-progress__bar" data-reading-progress></div>
    </div>

    <div class="reading-layout">
        <aside class="toc" data-toc>
            <button class="toc-toggle" type="button" data-toc-toggle>{{ __('ui.project.toc') }}</button>
            <nav class="toc-list" data-toc-list></nav>
        </aside>

        <div class="reading-main">
            <div class="tabs" data-tabs>
                <button class="tab is-active" type="button" data-tab="article">{{ __('ui.project.tab_article') }}</button>
                <button class="tab" type="button" data-tab="comments">{{ __('ui.project.tab_comments') }}</button>
                <button class="tab" type="button" data-tab="review">{{ __('ui.project.tab_review') }}</button>
            </div>

            <div class="tab-panel is-active" data-tab-panel="article">
                <article class="reading-article reading-article--rich" data-reading-article data-project-slug="{{ $project['slug'] }}">
                    @if (!empty($project['body_html']))
                        {!! $project['body_html'] !!}
                    @else
                        @foreach ($project['sections'] as $section)
                            <section class="reading-section">
                                <h2 id="{{ \Illuminate\Support\Str::slug($section['title']) }}">{{ $section['title'] }}</h2>
                                @foreach ($section['blocks'] as $block)
                                    @if ($block['type'] === 'p')
                                        <p class="reading-paragraph">{{ $block['text'] }}</p>
                                    @elseif ($block['type'] === 'h3')
                                        <h3 id="{{ \Illuminate\Support\Str::slug($block['text']) }}-{{ $loop->index }}">{{ $block['text'] }}</h3>
                                    @elseif ($block['type'] === 'quote')
                                        <blockquote class="reading-quote">{{ $block['text'] }}</blockquote>
                                    @elseif ($block['type'] === 'note')
                                        <div class="reading-note">{{ $block['text'] }}</div>
                                    @elseif ($block['type'] === 'image')
                                        @php
                                            $blockPath = $block['src'] ?? 'images/placeholder.svg';
                                            $blockUrl = \Illuminate\Support\Str::startsWith($blockPath, ['http://', 'https://'])
                                                ? $blockPath
                                                : asset(ltrim($blockPath, '/'));
                                        @endphp
                                        <figure class="reading-figure">
                                            <img class="reading-image" src="{{ $blockUrl }}" alt="{{ $block['caption'] }}" data-fallback="{{ asset('images/placeholder.svg') }}">
                                            <figcaption>{{ $block['caption'] }}</figcaption>
                                        </figure>
                                    @endif
                                @endforeach
                            </section>
                        @endforeach
                    @endif
                </article>
            </div>

            <div class="tab-panel discussion-panel" data-tab-panel="comments">
                <div class="comment-toolbar">
                    <div class="tabs comment-sort">
                        <button type="button" class="tab is-active" data-comment-sort="new">{{ __('ui.project.comments_new') }}</button>
                        <button type="button" class="tab" data-comment-sort="best">{{ __('ui.project.comments_best') }}</button>
                    </div>
                    <div class="helper">{{ __('ui.project.comments_best_hint') }}</div>
                </div>
                <div class="comments" data-comment-list data-threaded="true" data-project-slug="{{ $project['slug'] }}" data-comments-endpoint="{{ route('project.comments.chunk', $project['slug']) }}" data-comments-offset="{{ $commentsShown }}" data-comments-total="{{ $commentsTotal }}" data-comments-limit="15">
                    @forelse ($comments as $comment)
                        @include('partials.project-comment', [
                            'comment' => $comment,
                            'commentIndex' => $loop->index,
                            'roleKeys' => $roleKeys,
                            'postAuthorSlug' => $authorSlug,
                        ])
                    @empty
                        <div class="list-item" data-comment-empty>{{ __('ui.project.comments_empty') }}</div>
                    @endforelse
                </div>
                <div class="comment-more-wrap">
                    <button type="button" class="comment-more" data-comment-more @if (!$commentsHasMore) hidden @endif>
                        <i data-lucide="plus" class="icon"></i>
                        <span>{{ __('ui.project.comments_more') }}</span>
                    </button>
                </div>
                @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                    <form class="comment-form" data-comment-form data-project-slug="{{ $project['slug'] }}" data-current-user="{{ $current_user['name'] ?? __('ui.project.anonymous') }}" data-current-role="{{ $userRole }}" data-current-role-label="{{ __('ui.roles.' . $userRole) }}" data-current-slug="{{ $current_user['slug'] ?? '' }}" data-post-author-slug="{{ $authorSlug }}">
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
                        <label class="comment-section">
                            {{ __('ui.project.comment_section_label') }}
                            <input class="input input--compact" type="text" name="section" placeholder="{{ __('ui.project.comment_section_placeholder') }}" data-comment-section>
                        </label>
                        <textarea class="input" placeholder="{{ __('ui.project.comment_placeholder') }}"></textarea>
                        <button type="button" class="ghost-btn" data-comment-submit>{{ __('ui.project.comment_submit') }}</button>
                    </form>
                @else
                    <div class="list-item">
                        <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.auth.login_title') }}</a>
                    </div>
                @endif
            </div>

            <div class="tab-panel review-panel" data-tab-panel="review">
                <div class="section-title">{{ __('ui.project.review_title') }}</div>
                <div class="comments review-list" data-review-list data-project-slug="{{ $project['slug'] }}">
                    @forelse ($project['reviews'] ?? [] as $review)
                        @php
                            $reviewAuthor = $review['author']['name'] ?? __('ui.project.anonymous');
                            $reviewAvatarPath = $review['author']['avatar'] ?? 'images/avatar-default.svg';
                            $reviewAvatarIsDefault = trim($reviewAvatarPath, '/') === 'images/avatar-default.svg';
                            $reviewAvatarUrl = \Illuminate\Support\Str::startsWith($reviewAvatarPath, ['http://', 'https://'])
                                ? $reviewAvatarPath
                                : asset(ltrim($reviewAvatarPath, '/'));
                            $reviewRoleKey = strtolower($review['author']['role'] ?? 'maker');
                            $reviewRoleKey = in_array($reviewRoleKey, $roleKeys, true) ? $reviewRoleKey : 'maker';
                            $reviewRoleLabel = __('ui.roles.' . $reviewRoleKey);
                            $reviewAuthorSlug = $review['author']['slug'] ?? \Illuminate\Support\Str::slug($reviewAuthor);
                            $reviewModerationStatus = strtolower((string) ($review['moderation_status'] ?? 'approved'));
                            $reviewIsHidden = !empty($review['is_hidden']);
                        @endphp
                        @php
                            $reviewScore = (int) ($review['useful'] ?? $review['score'] ?? 0);
                            $reviewAnchor = !empty($review['id']) ? 'review-' . $review['id'] : 'review-' . $loop->index;
                            $isAuthorReview = $reviewAuthorSlug && $reviewAuthorSlug === $authorSlug;
                            $viewer = Auth::user();
                            $viewerId = $viewer?->id;
                            $viewerSlug = $viewer?->slug ?? '';
                            $isReviewOwner = $viewer
                                && (
                                    (!empty($review['author']['id']) && $viewerId === $review['author']['id'])
                                    || ($viewerSlug !== '' && $viewerSlug === $reviewAuthorSlug)
                                );
                        @endphp
                        <div class="comment review-card" data-review-item data-review-order="{{ $loop->index }}" data-review-created="{{ $review['created_at'] ?? '' }}" data-review-id="{{ $review['id'] ?? '' }}" data-review-anchor="{{ $reviewAnchor }}" data-review-useful="{{ $reviewScore }}" data-moderation-scope data-moderation-status="{{ $reviewModerationStatus }}" data-moderation-type="review">
                            <div class="comment-vote">
                                <button type="button" class="vote-btn" data-review-vote="up" aria-label="{{ __('ui.js.comment_upvote') }}" aria-pressed="false">
                                    <i data-lucide="arrow-up" class="icon"></i>
                                </button>
                                <span class="vote-count">{{ $reviewScore }}</span>
                                <button type="button" class="vote-btn" data-review-vote="down" aria-label="{{ __('ui.js.comment_downvote') }}" aria-pressed="false">
                                    <i data-lucide="arrow-down" class="icon"></i>
                                </button>
                            </div>
                            <div class="review-content">
                                <div class="comment-meta">
                                    <img class="avatar" src="{{ $reviewAvatarUrl }}" alt="{{ $reviewAuthor }}" @if ($reviewAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $reviewAuthor }}" @endif>
                                    @if (!empty($reviewAuthorSlug))
                                        <a class="post-author" href="{{ route('profile.show', $reviewAuthorSlug) }}">{{ $reviewAuthor }}</a>
                                    @else
                                        <span class="post-author">{{ $reviewAuthor }}</span>
                                    @endif
                                    <span class="badge badge--{{ $reviewRoleKey }}">{{ $reviewRoleLabel }}</span>
                                    @if ($isAuthorReview)
                                        <span class="badge badge--author">{{ __('ui.project.author_badge') }}</span>
                                    @endif
                                    <span class="dot">&bull;</span>
                                    <span>{{ $review['time'] ?? '' }}</span>
                                    @if (!empty($review['author']['note']))
                                        <span class="helper">{{ $review['author']['note'] }}</span>
                                    @endif
                                    @can('moderate')
                                        @if ($reviewModerationStatus !== 'approved' || $reviewIsHidden)
                                            <span class="chip chip--moderation chip--{{ $reviewModerationStatus }}">{{ __('ui.moderation.status_' . $reviewModerationStatus) }}</span>
                                        @endif
                                    @endcan
                                    @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                                        @if (!$isReviewOwner)
                                            <div class="action-menu action-menu--inline" data-action-menu-container>
                                                <button class="icon-btn icon-btn--sm action-menu__trigger" type="button" aria-label="{{ __('ui.report.title') }}" aria-haspopup="menu" aria-expanded="false" data-action-menu-toggle>
                                                    <i data-lucide="more-horizontal" class="icon"></i>
                                                </button>
                                                <div class="action-menu__panel" role="menu" data-action-menu hidden>
                                                    <button type="button" class="action-menu__item action-menu__item--danger" data-report-open data-report-type="review" data-report-id="{{ $review['id'] ?? $loop->index }}" data-report-url="{{ url()->current() }}">
                                                        <i data-lucide="flag" class="icon"></i>
                                                        <span>{{ __('ui.report.title') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                    @can('moderate')
                                        @if (!empty($review['id']))
                                            @php
                                                $currentModerator = Auth::user();
                                                $isAdminModerator = $currentModerator?->isAdmin() ?? false;
                                                $canModerateReview = $isAdminModerator || $reviewRoleKey !== 'admin';
                                                $showQueue = $reviewModerationStatus !== 'pending';
                                                $showHide = $reviewModerationStatus !== 'hidden';
                                                $showRestore = $reviewModerationStatus !== 'approved';
                                            @endphp
                                            @if ($canModerateReview)
                                                <span class="comment-admin">
                                                    @if ($isAdminModerator)
                                                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-delete data-admin-type="review" data-admin-id="{{ $review['id'] }}" data-admin-url="{{ route('admin.reviews.delete', $review['id']) }}" aria-label="{{ __('ui.admin.delete') }}">
                                                            <i data-lucide="trash-2" class="icon"></i>
                                                        </button>
                                                    @endif
                                                    @if ($showQueue)
                                                        <button type="button" class="icon-btn icon-btn--sm" data-admin-queue data-admin-type="review" data-admin-id="{{ $review['id'] }}" data-admin-url="{{ route('moderation.reviews.queue', $review['id']) }}" aria-label="{{ __('ui.moderation.queue') }}">
                                                            <i data-lucide="alert-circle" class="icon"></i>
                                                        </button>
                                                    @endif
                                                    @if ($showHide)
                                                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-hide data-admin-type="review" data-admin-id="{{ $review['id'] }}" data-admin-url="{{ route('moderation.reviews.hide', $review['id']) }}" aria-label="{{ __('ui.moderation.hide') }}">
                                                            <i data-lucide="eye-off" class="icon"></i>
                                                        </button>
                                                    @endif
                                                    @if ($showRestore)
                                                        <button type="button" class="icon-btn icon-btn--sm icon-btn--accent" data-admin-restore data-admin-type="review" data-admin-id="{{ $review['id'] }}" data-admin-url="{{ route('moderation.reviews.restore', $review['id']) }}" aria-label="{{ __('ui.moderation.restore') }}">
                                                            <i data-lucide="eye" class="icon"></i>
                                                        </button>
                                                    @endif
                                                    @if (!$isReviewOwner)
                                                        <button type="button" class="icon-btn icon-btn--sm icon-btn--danger" data-admin-flag data-report-type="review" data-report-id="{{ $review['id'] }}" data-report-url="{{ url()->current() }}" aria-label="{{ __('ui.report.flag') }}">
                                                            <i data-lucide="flag" class="icon"></i>
                                                        </button>
                                                    @endif
                                                </span>
                                            @endif
                                        @endif
                                    @endcan
                                </div>
                                <div class="review-block">
                                    <div class="review-label">{{ __('ui.project.review_improve') }}</div>
                                    <div class="review-text">{{ $review['improve'] ?? '' }}</div>
                                </div>
                                <div class="review-block">
                                    <div class="review-label">{{ __('ui.project.review_why') }}</div>
                                    <div class="review-text">{{ $review['why'] ?? '' }}</div>
                                </div>
                                <div class="review-block">
                                    <div class="review-label">{{ __('ui.project.review_how') }}</div>
                                    <div class="review-text">{{ $review['how'] ?? '' }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-item" data-review-empty>{{ __('ui.project.reviews_empty') }}</div>
                    @endforelse
                </div>
                @if (Auth::check() && !(Auth::user()?->is_banned ?? false))
                    @if (in_array($userRole, ['maker', 'admin'], true))
                        <div class="card card--tight review-form">
                        @php
                            $reviewer = $project['reviewer'] ?? [
                                'name' => __('ui.project.reviewer_default_name'),
                                'role' => 'maker',
                                'note' => __('ui.project.reviewer_default_note'),
                                'avatar' => 'images/avatar-default.svg',
                            ];
                            $reviewerAvatarPath = $reviewer['avatar'] ?? 'images/avatar-default.svg';
                            $reviewerAvatarIsDefault = trim($reviewerAvatarPath, '/') === 'images/avatar-default.svg';
                            $reviewerAvatarUrl = \Illuminate\Support\Str::startsWith($reviewerAvatarPath, ['http://', 'https://'])
                                ? $reviewerAvatarPath
                                : asset(ltrim($reviewerAvatarPath, '/'));
                        @endphp
                        <div class="meta">
                            <img class="avatar" src="{{ $reviewerAvatarUrl }}" alt="{{ $reviewer['name'] ?? 'Maker' }}" @if ($reviewerAvatarIsDefault) data-avatar-auto="1" data-avatar-name="{{ $reviewer['name'] ?? 'Maker' }}" @endif>
                            @php
                                $reviewerRoleKey = strtolower($reviewer['role'] ?? 'maker');
                                $reviewerRoleKey = in_array($reviewerRoleKey, $roleKeys, true) ? $reviewerRoleKey : 'maker';
                                $reviewerSlug = $reviewer['slug'] ?? \Illuminate\Support\Str::slug($reviewer['name'] ?? '');
                            @endphp
                            <span class="badge badge--{{ $reviewerRoleKey }}">{{ __('ui.roles.' . $reviewerRoleKey) }}</span>
                            @if (!empty($reviewerSlug))
                                <a class="post-author" href="{{ route('profile.show', $reviewerSlug) }}">{{ $reviewer['name'] }}</a>
                            @else
                                <span>{{ $reviewer['name'] }}</span>
                            @endif
                            <span class="helper">{{ $reviewer['note'] }}</span>
                        </div>
                        <form class="form-grid" data-review-form data-project-slug="{{ $project['slug'] }}" data-current-user="{{ $current_user['name'] ?? __('ui.project.anonymous') }}" data-current-role="{{ $userRole }}" data-current-role-label="{{ __('ui.roles.' . $userRole) }}" data-current-slug="{{ $current_user['slug'] ?? '' }}" data-post-author-slug="{{ $authorSlug }}" data-review-label-improve="{{ __('ui.project.review_improve') }}" data-review-label-why="{{ __('ui.project.review_why') }}" data-review-label-how="{{ __('ui.project.review_how') }}">
                            <label>
                                {{ __('ui.project.review_improve') }}
                                <textarea class="input" placeholder="{{ __('ui.project.review_improve_placeholder') }}" rows="3" data-review-field="improve"></textarea>
                            </label>
                            <label>
                                {{ __('ui.project.review_why') }}
                                <textarea class="input" placeholder="{{ __('ui.project.review_why_placeholder') }}" rows="3" data-review-field="why"></textarea>
                            </label>
                            <label>
                                {{ __('ui.project.review_how') }}
                                <textarea class="input" placeholder="{{ __('ui.project.review_how_placeholder') }}" rows="3" data-review-field="how"></textarea>
                            </label>
                            <button type="button" class="ghost-btn ghost-btn--accent" data-review-submit>{{ __('ui.project.review_submit') }}</button>
                        </form>
                        </div>
                    @else
                        <div class="list-item">{{ __('ui.project.review_lock') }}</div>
                    @endif
                @else
                    <div class="list-item">
                        <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.auth.login_title') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

