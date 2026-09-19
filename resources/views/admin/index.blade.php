@extends('layouts.app')

@section('title', __('ui.admin.title'))
@section('robots', 'noindex, nofollow')
@section('page', 'admin')

@section('content')
    @php
        $adminSearch = $admin_search ?? (string) request('q', '');
        $currentTab = $admin_section ?? (string) request('tab', 'overview');
        $isAdminUser = Auth::user()?->isAdmin() ?? false;
        $roleOptions = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $adminNavigation = [
            'overview' => ['label' => __('ui.admin.overview'), 'icon' => 'layout-dashboard', 'href' => route('admin.tools')],
            'users' => ['label' => __('ui.admin.users'), 'icon' => 'users', 'href' => route('admin.tools', ['tab' => 'users'])],
            'content' => ['label' => __('ui.admin.content'), 'icon' => 'files', 'href' => route('admin.tools', ['tab' => 'content'])],
            'safety' => ['label' => __('ui.admin.moderation_feed'), 'icon' => 'shield-check', 'href' => route('admin.tools', ['tab' => 'media'])],
            'support' => ['label' => __('ui.admin.support_tickets'), 'icon' => 'messages-square', 'href' => route('admin.tools', ['tab' => 'support'])],
            'system' => ['label' => __('ui.admin.system'), 'icon' => 'settings', 'href' => route('admin.tools', ['tab' => 'analytics'])],
        ];
        $availableTabs = ['overview', 'users', 'content', 'collaborations', 'moderation', 'support', 'media', 'comments', 'reviews', 'log', 'analytics', 'promos', 'system'];
        if (!in_array($currentTab, $availableTabs, true)) {
            $currentTab = 'overview';
        }
        $defaultTab = $currentTab;
        $activeWorkspace = match (true) {
            in_array($defaultTab, ['content', 'collaborations', 'comments', 'reviews'], true) => 'content',
            in_array($defaultTab, ['moderation', 'media', 'log'], true) => 'safety',
            in_array($defaultTab, ['analytics', 'promos', 'system'], true) => 'system',
            default => $defaultTab,
        };
        $subNavigation = match ($activeWorkspace) {
            'content' => [
                ['label' => __('ui.admin.content'), 'href' => route('admin.tools', ['tab' => 'content']), 'active' => $defaultTab === 'content'],
                ['label' => __('ui.admin.comments'), 'href' => route('admin.tools', ['tab' => 'comments']), 'active' => $defaultTab === 'comments'],
                ['label' => __('ui.admin.reviews'), 'href' => route('admin.tools', ['tab' => 'reviews']), 'active' => $defaultTab === 'reviews'],
                ['label' => __('ui.admin.collaborations'), 'href' => route('admin.tools', ['tab' => 'collaborations']), 'active' => $defaultTab === 'collaborations'],
                ['label' => __('waasabi.journal'), 'href' => route('journal.moderation'), 'active' => false],
            ],
            'safety' => [
                ['label' => __('ui.admin.moderation_feed'), 'href' => route('admin'), 'active' => false],
                ['label' => __('ui.admin.media'), 'href' => route('admin.tools', ['tab' => 'media']), 'active' => $defaultTab === 'media'],
                ['label' => __('ui.admin.moderation_log'), 'href' => route('admin.tools', ['tab' => 'log']), 'active' => $defaultTab === 'log'],
            ],
            'system' => [
                ['label' => __('ui.admin.analytics'), 'href' => route('admin.tools', ['tab' => 'analytics']), 'active' => $defaultTab === 'analytics'],
                ['label' => __('ui.admin.promos'), 'href' => route('admin.tools', ['tab' => 'promos']), 'active' => $defaultTab === 'promos'],
                ['label' => __('ui.admin.system'), 'href' => route('admin.tools', ['tab' => 'system']), 'active' => $defaultTab === 'system'],
            ],
            default => [],
        };
        $searchableTabs = ['users', 'content', 'collaborations', 'comments', 'reviews', 'moderation', 'support', 'media', 'log'];
        $moderationSort = $moderation_sort ?? 'reporters';
        $moderationSort = in_array($moderationSort, ['reporters', 'recent'], true) ? $moderationSort : 'reporters';
        $hasActiveFilters = $adminSearch !== '' || match ($defaultTab) {
            'content' => $content_type !== '' || $content_visibility !== '' || $content_moderation !== '',
            'collaborations' => $collaboration_status !== '',
            'moderation' => $moderationSort !== 'reporters',
            default => false,
        };
        $overview = $admin_overview ?? [];
    @endphp

    <div class="admin-app">
        <aside class="admin-app__sidebar">
            <a class="admin-app__brand" href="{{ route('admin.tools') }}">
                <img src="{{ asset('images/logo-black.svg') }}" alt="">
                <span>{{ __('ui.app.name') }}</span>
                <strong>{{ __('ui.admin.title') }}</strong>
            </a>
            <nav class="admin-app__nav" aria-label="{{ __('ui.admin.navigation') }}">
                @foreach ($adminNavigation as $workspace => $item)
                    <a class="admin-app__nav-item {{ $activeWorkspace === $workspace ? 'is-active' : '' }}" href="{{ $item['href'] }}" @if ($activeWorkspace === $workspace) aria-current="page" @endif>
                        <i data-lucide="{{ $item['icon'] }}" class="icon"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="admin-app__sidebar-footer">
                <a href="{{ route('feed') }}">
                    <i data-lucide="arrow-left" class="icon"></i>
                    <span>{{ __('ui.admin.back_to_site') }}</span>
                </a>
                <div class="admin-app__identity">
                    <img src="{{ Auth::user()?->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                    <div>
                        <strong>{{ Auth::user()?->name }}</strong>
                        <span>{{ __('ui.roles.'.(Auth::user()?->roleKey() ?? 'user')) }}</span>
                    </div>
                </div>
            </div>
        </aside>

        <div class="admin-app__workspace">
            <header class="admin-app__topbar">
                <div class="admin-app__topbar-title">
                    <a href="{{ route('admin.tools') }}">{{ __('ui.admin.title') }}</a>
                    <i data-lucide="chevron-right" class="icon" aria-hidden="true"></i>
                    <h1>{{ $adminNavigation[$activeWorkspace]['label'] }}</h1>
                </div>
                @if (in_array($defaultTab, $searchableTabs, true))
                <form class="admin-search__form" method="GET" action="{{ route('admin.tools') }}" role="search">
                    <input type="hidden" name="tab" value="{{ $defaultTab }}">
                    @if ($defaultTab === 'moderation')
                        <input type="hidden" name="sort" value="{{ $moderationSort }}">
                    @elseif ($defaultTab === 'content')
                        <input type="hidden" name="type" value="{{ $content_type }}">
                        <input type="hidden" name="visibility" value="{{ $content_visibility }}">
                        <input type="hidden" name="moderation" value="{{ $content_moderation }}">
                    @elseif ($defaultTab === 'collaborations')
                        <input type="hidden" name="status" value="{{ $collaboration_status }}">
                    @endif
                    <div class="admin-search__field">
                        <i data-lucide="search" class="icon"></i>
                        <input type="search" name="q" value="{{ $adminSearch }}" placeholder="{{ __('ui.admin.search_placeholder') }}" aria-label="{{ __('ui.admin.search_placeholder') }}" data-admin-search-input>
                        <kbd aria-hidden="true">/</kbd>
                    </div>
                    <button class="ghost-btn" type="submit">{{ __('ui.admin.search') }}</button>
                    @if ($hasActiveFilters)
                        <a class="ghost-btn" href="{{ route('admin.tools', ['tab' => $defaultTab]) }}">{{ __('ui.admin.reset_filters') }}</a>
                    @endif
                </form>
                @endif
            </header>

            @if ($subNavigation)
                <nav class="admin-app__subnav" aria-label="{{ $adminNavigation[$activeWorkspace]['label'] }}">
                    @foreach ($subNavigation as $item)
                        <a class="{{ $item['active'] ? 'is-active' : '' }}" href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="admin-app__content">
                @if ($defaultTab === 'overview')
                <div>
                    <section class="admin-overview">
                        <dl class="admin-overview__numbers">
                            <div>
                                <dt>{{ __('ui.admin.total_users') }}</dt>
                                <dd>{{ number_format((int) ($overview['users'] ?? 0)) }}</dd>
                                <span>{{ __('ui.admin.new_users_7d', ['count' => (int) ($overview['new_users'] ?? 0)]) }}</span>
                                @if ($isAdminUser)
                                    <a class="admin-metric-hitbox" href="{{ route('admin.tools', ['tab' => 'users']) }}" aria-label="{{ __('ui.admin.users') }}"></a>
                                @endif
                            </div>
                            <div>
                                <dt>{{ __('ui.admin.total_content') }}</dt>
                                <dd>{{ number_format((int) ($overview['content'] ?? 0)) }}</dd>
                                <a class="admin-metric-hitbox" href="{{ route('admin.tools', ['tab' => 'content']) }}" aria-label="{{ __('ui.admin.content') }}"></a>
                            </div>
                            <div class="admin-overview__metric--attention">
                                <dt>{{ __('ui.admin.pending_reports') }}</dt>
                                <dd>{{ number_format((int) ($overview['pending_reports'] ?? 0)) }}</dd>
                                <a class="admin-metric-hitbox" href="{{ route('admin') }}" aria-label="{{ __('ui.admin.moderation_feed') }}"></a>
                            </div>
                            <div>
                                <dt>{{ __('ui.admin.open_support') }}</dt>
                                <dd>{{ number_format((int) ($overview['open_tickets'] ?? 0)) }}</dd>
                                <a class="admin-metric-hitbox" href="{{ route('admin.tools', ['tab' => 'support']) }}" aria-label="{{ __('ui.admin.support_tickets') }}"></a>
                            </div>
                        </dl>

                        <div class="admin-overview__columns">
                            <section class="admin-overview__section">
                                <header>
                                    <h2>{{ __('ui.admin.work_queue') }}</h2>
                                </header>
                                <a class="admin-queue-row" href="{{ route('admin') }}">
                                    <span>{{ __('ui.admin.pending_reports') }}</span>
                                    <strong>{{ (int) ($overview['pending_reports'] ?? 0) }}</strong>
                                    <i data-lucide="chevron-right" class="icon"></i>
                                </a>
                                <a class="admin-queue-row" href="{{ route('admin.tools', ['tab' => 'support']) }}">
                                    <span>{{ __('ui.admin.open_support') }}</span>
                                    <strong>{{ (int) ($overview['open_tickets'] ?? 0) }}</strong>
                                    <i data-lucide="chevron-right" class="icon"></i>
                                </a>
                                <a class="admin-queue-row" href="{{ route('admin.tools', ['tab' => 'media']) }}">
                                    <span>{{ __('ui.admin.flagged_media') }}</span>
                                    <strong>{{ (int) ($overview['flagged_media'] ?? 0) }}</strong>
                                    <i data-lucide="chevron-right" class="icon"></i>
                                </a>
                                @if ($isAdminUser)
                                    <a class="admin-queue-row" href="{{ route('admin.tools', ['tab' => 'promos']) }}">
                                        <span>{{ __('ui.admin.active_promos') }}</span>
                                        <strong>{{ (int) ($overview['active_promos'] ?? 0) }}</strong>
                                        <i data-lucide="chevron-right" class="icon"></i>
                                    </a>
                                @endif
                            </section>

                            <section class="admin-overview__section">
                                <header>
                                    <h2>{{ __('ui.admin.recent_activity') }}</h2>
                                    <a href="{{ route('admin.tools', ['tab' => 'log']) }}">{{ __('ui.admin.view_all') }}</a>
                                </header>
                                <div class="admin-activity-list">
                                    @forelse ($overview_logs as $log)
                                        <div class="admin-activity-row">
                                            <div>
                                                <strong>{{ $log->moderator_name ?? __('ui.roles.moderator') }}</strong>
                                                <span>{{ $log->action ?? __('ui.admin.moderation_log_action') }}</span>
                                            </div>
                                            <time datetime="{{ $log->created_at?->toAtomString() }}">{{ $log->created_at?->diffForHumans() }}</time>
                                        </div>
                                    @empty
                                        <p class="muted">{{ __('ui.admin.no_recent_activity') }}</p>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </section>
                </div>
                @endif

                <div class="admin-tab-panels">
    @if ($defaultTab === 'content')
        <section class="section admin-section">
            <div class="admin-section-toolbar">
                <div class="section-title">{{ __('ui.admin.content') }}</div>
                <form class="admin-filter-form" method="GET" action="{{ route('admin.tools') }}">
                    <input type="hidden" name="tab" value="content">
                    <select class="input input--compact" name="type" aria-label="{{ __('ui.admin.content_type') }}">
                        <option value="">{{ __('ui.admin.filter_all_types') }}</option>
                        <option value="post" @selected($content_type === 'post')>{{ __('ui.publish.type_post') }}</option>
                        <option value="question" @selected($content_type === 'question')>{{ __('ui.publish.type_question') }}</option>
                    </select>
                    <select class="input input--compact" name="visibility" aria-label="{{ __('ui.publish.visibility_label') }}">
                        <option value="">{{ __('ui.admin.filter_all_visibility') }}</option>
                        <option value="public" @selected($content_visibility === 'public')>{{ __('ui.publish.visibility_public') }}</option>
                        <option value="unlisted" @selected($content_visibility === 'unlisted')>{{ __('ui.publish.visibility_unlisted') }}</option>
                        <option value="draft" @selected($content_visibility === 'draft')>{{ __('ui.admin.filter_draft') }}</option>
                    </select>
                    <select class="input input--compact" name="moderation" aria-label="{{ __('ui.admin.moderation_feed') }}">
                        <option value="">{{ __('ui.admin.filter_all_moderation') }}</option>
                        <option value="approved" @selected($content_moderation === 'approved')>{{ __('ui.moderation.status_approved') }}</option>
                        <option value="pending" @selected($content_moderation === 'pending')>{{ __('ui.moderation.status_pending') }}</option>
                        <option value="hidden" @selected($content_moderation === 'hidden')>{{ __('ui.moderation.status_hidden') }}</option>
                    </select>
                    <button class="ghost-btn ghost-btn--compact" type="submit">{{ __('ui.admin.apply_filters') }}</button>
                </form>
            </div>

            <form class="admin-bulk-bar" id="admin-content-bulk" method="POST" action="{{ route('admin.content.bulk') }}" data-admin-bulk data-confirm-submit data-confirm-message="{{ __('ui.admin.bulk_confirm') }}">
                @csrf
                <span class="admin-bulk-bar__selection" aria-live="polite"><strong data-admin-selected-count>0</strong> {{ __('ui.admin.selected') }}</span>
                <select class="input input--compact" name="action" required>
                    <option value="">{{ __('ui.admin.bulk_action') }}</option>
                    @if ($isAdminUser)
                        <option value="hide">{{ __('ui.moderation.hide') }}</option>
                        <option value="restore">{{ __('ui.moderation.restore') }}</option>
                        <option value="delete">{{ __('ui.admin.delete') }}</option>
                    @endif
                </select>
                <input class="input input--compact" type="text" name="reason" maxlength="500" placeholder="{{ __('ui.moderation.reason_placeholder') }}" required>
                <button class="ghost-btn ghost-btn--compact" type="submit">{{ __('ui.admin.apply') }}</button>
            </form>

            <div class="admin-content-select-all">
                <label><input type="checkbox" data-admin-select-all="admin-content-bulk"> {{ __('ui.admin.select_all') }}</label>
                <span>{{ method_exists($content_items, 'total') ? $content_items->total() : $content_items->count() }} {{ __('ui.admin.content_item') }}</span>
            </div>
            <div class="admin-content-list">
                @forelse ($content_items as $item)
                    @php
                        $itemUrl = $item->type === 'question' ? route('questions.show', $item->slug) : route('project', $item->slug);
                        $canSelect = $isAdminUser || ! $item->user?->isAdmin();
                        $visibilityLabel = match ($item->visibility) {
                            'public' => __('ui.publish.visibility_public'),
                            'unlisted' => __('ui.publish.visibility_unlisted'),
                            default => __('ui.admin.filter_draft'),
                        };
                        $preview = $item->subtitle ?: \Illuminate\Support\Str::limit(trim(strip_tags(app(\App\Services\MarkdownService::class)->render((string) $item->body_markdown))), 320);
                    @endphp
                    <article class="admin-content-item" data-admin-select-row data-feed-card data-moderation-scope data-moderation-status="{{ $item->moderation_status }}">
                        <div class="admin-content-item__select">
                            <input type="checkbox" name="post_ids[]" value="{{ $item->id }}" form="admin-content-bulk" data-admin-row-select aria-label="{{ __('ui.admin.select_content', ['title' => $item->title]) }}" @disabled(! $canSelect)>
                        </div>
                        <div class="admin-content-item__body">
                            <header>
                                <div>
                                    <a class="admin-content-item__title" href="{{ $itemUrl }}" target="_blank" rel="noopener">{{ $item->title }}</a>
                                    <div class="admin-content-item__meta admin-moderation__meta">
                                        <span>{{ $item->type === 'question' ? __('ui.publish.type_question') : __('ui.publish.type_post') }}</span>
                                        <span>{{ $visibilityLabel }}</span>
                                        @if ($item->moderation_status !== 'approved')
                                            <span class="chip chip--moderation chip--{{ $item->moderation_status }}">{{ __('ui.moderation.status_'.$item->moderation_status) }}</span>
                                        @endif
                                        @if ($item->nsfw)
                                            <span class="chip chip--nsfw">NSFW</span>
                                        @endif
                                        <time datetime="{{ $item->created_at?->toAtomString() }}">{{ $item->created_at?->diffForHumans() }}</time>
                                    </div>
                                </div>
                                <div class="admin-content-item__actions">
                                    <button type="button" class="ghost-btn ghost-btn--compact" data-admin-restore data-admin-url="{{ route('moderation.posts.restore', $item) }}" data-allow-label="{{ __('ui.moderation.allow') }}" data-restore-label="{{ __('ui.moderation.restore') }}" @if ($item->moderation_status === 'approved') hidden @endif>{{ $item->moderation_status === 'pending' ? __('ui.moderation.allow') : __('ui.moderation.restore') }}</button>
                                    <button type="button" class="ghost-btn ghost-btn--compact ghost-btn--danger" data-admin-hide data-admin-url="{{ route('moderation.posts.hide', $item) }}" @if ($item->moderation_status === 'hidden') hidden @endif><i data-lucide="eye-off" class="icon" aria-hidden="true"></i>{{ __('ui.moderation.hide') }}</button>
                                    <details class="admin-action-menu">
                                        <summary aria-label="{{ __('ui.admin.more_actions') }}" title="{{ __('ui.admin.more_actions') }}"><i data-lucide="more-horizontal" class="icon" aria-hidden="true"></i></summary>
                                        <div class="admin-action-menu__panel">
                                            <button type="button" data-admin-nsfw data-admin-nsfw-value="{{ $item->nsfw ? '0' : '1' }}" data-admin-url="{{ route('moderation.posts.nsfw', $item) }}">{{ $item->nsfw ? __('ui.moderation.remove_nsfw') : __('ui.moderation.nsfw') }}</button>
                                            <button type="button" class="admin-action-menu__danger" data-admin-delete data-admin-url="{{ route('admin.posts.delete', $item) }}">{{ __('ui.admin.delete') }}</button>
                                        </div>
                                    </details>
                                </div>
                            </header>
                            @if ($preview !== '')
                                <p class="admin-content-item__preview">{{ $preview }}</p>
                            @endif
                            @include('admin.partials.report-context', ['context' => $item->moderation_report_context ?? null])
                            <footer>
                                <a class="admin-content-author" href="{{ route('profile.show', $item->user->slug) }}">
                                    <img src="{{ $item->user->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                                    <span>{{ $item->user->name }}</span>
                                </a>
                                <span>{{ __('ui.admin.content_activity_value', ['comments' => $item->comments_count, 'reviews' => $item->reviews_count, 'collaborations' => $item->collaboration_requests_count]) }}</span>
                            </footer>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty">{{ __('ui.admin.content_empty') }}</p>
                @endforelse
            </div>
            @if (method_exists($content_items, 'links'))
                <div class="admin-pagination">
                    {{ $content_items->appends(['tab' => 'content', 'q' => $adminSearch, 'type' => $content_type, 'visibility' => $content_visibility, 'moderation' => $content_moderation])->links() }}
                </div>
            @endif
        </section>
    @endif

    @if ($defaultTab === 'collaborations')
        <section class="section admin-section">
            <div class="admin-section-toolbar">
                <div class="section-title">{{ __('ui.admin.collaborations') }}</div>
                <form class="admin-filter-form" method="GET" action="{{ route('admin.tools') }}">
                    <input type="hidden" name="tab" value="collaborations">
                    <select class="input input--compact" name="status" aria-label="{{ __('ui.collaboration.filter_status') }}">
                        <option value="">{{ __('ui.collaboration.status_all') }}</option>
                        <option value="open" @selected($collaboration_status === 'open')>{{ __('ui.collaboration.status_open') }}</option>
                        <option value="filled" @selected($collaboration_status === 'filled')>{{ __('ui.collaboration.status_filled') }}</option>
                        <option value="closed" @selected($collaboration_status === 'closed')>{{ __('ui.collaboration.status_closed') }}</option>
                    </select>
                    <button class="ghost-btn ghost-btn--compact" type="submit">{{ __('ui.admin.apply_filters') }}</button>
                </form>
            </div>

            @if ($isAdminUser)
            <form class="admin-bulk-bar" id="admin-collaboration-bulk" method="POST" action="{{ route('admin.collaborations.bulk') }}" data-admin-bulk data-confirm-submit data-confirm-message="{{ __('ui.admin.bulk_confirm') }}">
                @csrf
                <span class="admin-bulk-bar__selection" aria-live="polite"><strong data-admin-selected-count>0</strong> {{ __('ui.admin.selected') }}</span>
                <select class="input input--compact" name="action" required>
                    <option value="">{{ __('ui.admin.bulk_action') }}</option>
                    <option value="close">{{ __('ui.collaboration.close') }}</option>
                    <option value="reopen">{{ __('ui.collaboration.reopen') }}</option>
                    <option value="delete">{{ __('ui.admin.delete') }}</option>
                </select>
                <input class="input input--compact" type="text" name="reason" maxlength="500" placeholder="{{ __('ui.moderation.reason_placeholder') }}" required>
                <button class="ghost-btn ghost-btn--compact" type="submit">{{ __('ui.admin.apply') }}</button>
            </form>
            @endif

            @if ($isAdminUser && $collaborations->isNotEmpty())
                <div class="admin-content-select-all">
                    <label>
                        <input type="checkbox" data-admin-select-all="admin-collaboration-bulk" aria-label="{{ __('ui.admin.select_all') }}">
                        {{ __('ui.admin.select_all') }}
                    </label>
                    <span class="muted">{{ $collaborations->total() }}</span>
                </div>
            @endif

            <div class="admin-card admin-content-list">
                @forelse ($collaborations as $collaboration)
                    @php
                        $statusIcon = match ($collaboration->status) {
                            'open' => 'circle-dot',
                            'filled' => 'circle-check',
                            default => 'circle-slash-2',
                        };
                    @endphp
                    <article class="admin-content-item" data-admin-select-row>
                        @if ($isAdminUser)
                            <div class="admin-content-item__select">
                                <input type="checkbox" name="request_ids[]" value="{{ $collaboration->id }}" form="admin-collaboration-bulk" data-admin-row-select aria-label="{{ __('ui.admin.select_content', ['title' => $collaboration->title]) }}">
                            </div>
                        @endif
                        <div class="admin-content-item__body">
                            <header>
                                <div>
                                    <a class="admin-content-item__title" href="{{ route('collaboration.show', $collaboration) }}" target="_blank" rel="noopener">{{ $collaboration->title }}</a>
                                    <div class="admin-content-item__meta">
                                        <span class="admin-state" data-state="{{ $collaboration->status }}"><span><i data-lucide="{{ $statusIcon }}" class="icon"></i>{{ __('ui.collaboration.status_'.$collaboration->status) }}</span></span>
                                        <time datetime="{{ $collaboration->created_at?->toAtomString() }}">{{ $collaboration->created_at?->diffForHumans() }}</time>
                                    </div>
                                </div>
                                <div class="admin-content-item__actions">
                                    <a href="{{ route('admin.tools', ['tab' => 'collaborations', 'request' => $collaboration->id]) }}">{{ __('ui.admin.inspect') }}</a>
                                </div>
                            </header>
                            <p class="admin-content-item__preview">{{ $collaboration->summary }}</p>
                            <footer>
                                <div class="admin-content-item__author admin-user-cell">
                                    <img src="{{ $collaboration->user->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                                    <div>
                                        <a href="{{ route('profile.show', $collaboration->user->slug) }}">{{ $collaboration->user->name }}</a>
                                        <span class="muted">{{ __('ui.roles.'.$collaboration->user->roleKey()) }}</span>
                                    </div>
                                </div>
                                <span class="muted">{{ __('ui.admin.responses_and_comments') }}: {{ $collaboration->applications_count }} / {{ $collaboration->comments_count }}</span>
                                @if ($collaboration->post)
                                    <a class="muted" href="{{ route('project', $collaboration->post->slug) }}">{{ $collaboration->post->title }}</a>
                                @endif
                            </footer>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty">{{ __('ui.admin.collaborations_empty') }}</p>
                @endforelse
            </div>
            @if (method_exists($collaborations, 'links'))
                <div class="admin-pagination">
                    {{ $collaborations->appends(['tab' => 'collaborations', 'q' => $adminSearch, 'status' => $collaboration_status])->links() }}
                </div>
            @endif

            @if ($selected_collaboration)
                <section class="admin-collaboration-inspector">
                    <header>
                        <div>
                            <h2>{{ $selected_collaboration->title }}</h2>
                            <a href="{{ route('collaboration.show', $selected_collaboration) }}" target="_blank" rel="noopener">{{ __('ui.admin.open_public_page') }}</a>
                        </div>
                        <a class="icon-btn" href="{{ route('admin.tools', ['tab' => 'collaborations']) }}" aria-label="{{ __('ui.settings.close') }}"><i data-lucide="x" class="icon"></i></a>
                    </header>
                    <div class="admin-collaboration-inspector__columns">
                        <div>
                            <h3>{{ __('ui.collaboration.applications') }}</h3>
                            @forelse ($selected_collaboration->applications as $application)
                                <article class="admin-collaboration-entry">
                                    <header>
                                        <a href="{{ route('profile.show', $application->user->slug) }}">{{ $application->user->name }}</a>
                                        <span>{{ __('ui.collaboration.application_'.$application->status) }}</span>
                                    </header>
                                    <p>{{ $application->message }}</p>
                                    @if ($isAdminUser)
                                        <form method="POST" action="{{ route('admin.collaboration-applications.delete', $application) }}" data-confirm-submit data-confirm-message="{{ __('ui.admin.delete_confirm') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input class="input input--compact" type="text" name="reason" maxlength="500" placeholder="{{ __('ui.moderation.reason_placeholder') }}" required>
                                            <button class="ghost-btn ghost-btn--compact ghost-btn--danger" type="submit">{{ __('ui.admin.delete') }}</button>
                                        </form>
                                    @endif
                                </article>
                            @empty
                                <p class="admin-empty">{{ __('ui.collaboration.applications_empty') }}</p>
                            @endforelse
                        </div>
                        <div>
                            <h3>{{ __('ui.collaboration.comments') }}</h3>
                            @forelse ($selected_collaboration->comments as $comment)
                                <article class="admin-collaboration-entry">
                                    <header>
                                        <a href="{{ route('profile.show', $comment->user->slug) }}">{{ $comment->user->name }}</a>
                                        <time datetime="{{ $comment->created_at?->toAtomString() }}">{{ $comment->created_at?->diffForHumans() }}</time>
                                    </header>
                                    <p>{{ $comment->body }}</p>
                                    @if ($isAdminUser)
                                        <form method="POST" action="{{ route('admin.collaboration-comments.delete', $comment) }}" data-confirm-submit data-confirm-message="{{ __('ui.admin.delete_confirm') }}">
                                            @csrf
                                            @method('DELETE')
                                            <input class="input input--compact" type="text" name="reason" maxlength="500" placeholder="{{ __('ui.moderation.reason_placeholder') }}" required>
                                            <button class="ghost-btn ghost-btn--compact ghost-btn--danger" type="submit">{{ __('ui.admin.delete') }}</button>
                                        </form>
                                    @endif
                                </article>
                            @empty
                                <p class="admin-empty">{{ __('ui.collaboration.comments_empty') }}</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @endif
        </section>
    @endif

    @if ($isAdminUser && $defaultTab === 'users')
        <div>
            <section class="section admin-section">
                <div class="section-title">{{ __('ui.admin.users') }}</div>
                <div class="card admin-card">
                    <div class="admin-table">
                        <div class="admin-row admin-row--users admin-row--head">
                            <div>{{ __('ui.admin.user_name') }}</div>
                            <div>{{ __('ui.admin.user_email') }}</div>
                            <div>{{ __('ui.admin.user_role') }}</div>
                            <div>{{ __('ui.admin.actions') }}</div>
                        </div>
                        @foreach ($users as $user)
                            @php
                                $userSlug = $user->slug ?? \Illuminate\Support\Str::slug($user->name ?? '');
                                $isBanned = (bool) ($user->is_banned ?? false);
                            @endphp
                            <div class="admin-row admin-row--users">
                                <div class="admin-user-cell">
                                    <img src="{{ $user->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                                    <div>
                                        @if (!empty($userSlug))
                                            <a href="{{ route('admin.tools', ['tab' => 'users', 'user' => $user->id]) }}">{{ $user->name }}</a>
                                        @else
                                            <span>{{ $user->name }}</span>
                                        @endif
                                        @if ($isBanned)
                                            <span class="badge badge--banned">{{ __('ui.admin.banned') }}</span>
                                        @endif
                                        <span class="muted">{{ __('ui.admin.user_activity_value', ['posts' => $user->posts_count, 'comments' => $user->post_comments_count, 'applications' => $user->collaboration_applications_count]) }}</span>
                                    </div>
                                </div>
                                <div class="muted">{{ $user->email }}</div>
                                <div>
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}">
                                        @csrf
                                        <select class="input input--compact" name="role" data-confirm-select data-confirm-message="{{ __('ui.js.admin_role_confirm') }}">
                                            @foreach ($roleOptions as $roleOption)
                                                <option value="{{ $roleOption }}" {{ $user->role === $roleOption ? 'selected' : '' }}>
                                                    {{ __('ui.roles.' . $roleOption) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                                <div>
                                    @if (Auth::id() !== $user->id)
                                        <form method="POST" action="{{ route('admin.users.ban', $user) }}" data-moderation-reason-form data-moderation-action="{{ $isBanned ? 'unban' : 'ban' }}">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button class="ghost-btn ghost-btn--compact {{ $isBanned ? '' : 'ghost-btn--danger' }}" type="submit">
                                                {{ $isBanned ? __('ui.admin.unban') : __('ui.admin.ban') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @if (method_exists($users, 'links'))
                    <div class="admin-pagination">
                        {{ $users->appends(['tab' => 'users', 'q' => $adminSearch])->links() }}
                    </div>
                @endif
                @if ($selected_user)
                    <section class="admin-user-inspector">
                        <header class="admin-inspector-header">
                            <div>
                                <span class="admin-record-context">#{{ $selected_user->id }} · {{ __('ui.roles.'.$selected_user->roleKey()) }}</span>
                                <h2>{{ $selected_user->name }}</h2>
                                <p>{{ $selected_user->email }}</p>
                            </div>
                            <div class="admin-actions">
                                <a class="ghost-btn ghost-btn--compact" href="{{ route('profile.show', $selected_user->slug) }}" target="_blank" rel="noopener">{{ __('ui.admin.open_public_page') }}</a>
                                <a class="icon-btn" href="{{ route('admin.tools', ['tab' => 'users']) }}" aria-label="{{ __('ui.settings.close') }}"><i data-lucide="x" class="icon"></i></a>
                            </div>
                        </header>
                        <dl class="admin-user-facts">
                            <div><dt>{{ __('ui.admin.user_joined') }}</dt><dd>{{ $selected_user->created_at?->format('Y-m-d H:i') }}</dd></div>
                            <div><dt>{{ __('ui.admin.user_verified') }}</dt><dd>{{ $selected_user->email_verified_at?->format('Y-m-d H:i') ?? __('ui.admin.no') }}</dd></div>
                            <div><dt>{{ __('ui.admin.total_content') }}</dt><dd>{{ $selected_user->posts_count }}</dd></div>
                            <div><dt>{{ __('ui.admin.comments') }}</dt><dd>{{ $selected_user->post_comments_count }}</dd></div>
                            <div><dt>{{ __('ui.admin.reviews') }}</dt><dd>{{ $selected_user->post_reviews_count }}</dd></div>
                            <div><dt>{{ __('ui.admin.collaborations') }}</dt><dd>{{ $selected_user->collaboration_requests_count }} / {{ $selected_user->collaboration_applications_count }}</dd></div>
                            <div><dt>{{ __('ui.admin.support_tickets') }}</dt><dd>{{ $selected_user->support_tickets_count }}</dd></div>
                            <div><dt>{{ __('ui.admin.uploads') }}</dt><dd>{{ $selected_user->upload_assets_count }}</dd></div>
                        </dl>
                        <div class="admin-inspector-grid">
                            <section>
                                <h3>{{ __('ui.admin.recent_content') }}</h3>
                                <div class="admin-record-list">
                                    @forelse ($selected_user->posts as $post)
                                        <a href="{{ $post->type === 'question' ? route('questions.show', $post->slug) : route('project', $post->slug) }}" target="_blank" rel="noopener">
                                            <strong>{{ $post->title }}</strong><span>{{ $post->type }} · {{ $post->created_at?->format('Y-m-d') }}</span>
                                        </a>
                                    @empty
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endforelse
                                </div>
                            </section>
                            <section>
                                <h3>{{ __('ui.admin.recent_participation') }}</h3>
                                <div class="admin-record-list">
                                    @foreach ($selected_user->postComments as $comment)
                                        <a href="{{ app(\App\Services\ModerationService::class)->resolvePostUrl($comment->post_slug) }}#comment-{{ $comment->id }}" target="_blank" rel="noopener"><strong>{{ \Illuminate\Support\Str::limit($comment->body, 90) }}</strong><span>{{ __('ui.admin.comments') }} · {{ $comment->created_at?->format('Y-m-d') }}</span></a>
                                    @endforeach
                                    @foreach ($selected_user->postReviews as $review)
                                        <a href="{{ app(\App\Services\ModerationService::class)->resolvePostUrl($review->post_slug) }}#review-{{ $review->id }}" target="_blank" rel="noopener"><strong>{{ \Illuminate\Support\Str::limit($review->improve, 90) }}</strong><span>{{ __('ui.admin.reviews') }} · {{ $review->created_at?->format('Y-m-d') }}</span></a>
                                    @endforeach
                                    @if ($selected_user->postComments->isEmpty() && $selected_user->postReviews->isEmpty())
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endif
                                </div>
                            </section>
                            <section>
                                <h3>{{ __('ui.admin.collaboration_history') }}</h3>
                                <div class="admin-record-list">
                                    @foreach ($selected_user->collaborationRequests as $collaboration)
                                        <a href="{{ route('collaboration.show', $collaboration) }}" target="_blank" rel="noopener"><strong>{{ $collaboration->title }}</strong><span>{{ __('ui.admin.created') }} · {{ __('ui.collaboration.status_'.$collaboration->status) }}</span></a>
                                    @endforeach
                                    @foreach ($selected_user->collaborationApplications as $application)
                                        @if ($application->collaborationRequest)
                                            <a href="{{ route('collaboration.show', $application->collaborationRequest) }}" target="_blank" rel="noopener"><strong>{{ $application->collaborationRequest->title }}</strong><span>{{ __('ui.collaboration.application_'.$application->status) }}</span></a>
                                        @endif
                                    @endforeach
                                    @if ($selected_user->collaborationRequests->isEmpty() && $selected_user->collaborationApplications->isEmpty())
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endif
                                </div>
                            </section>
                            <section>
                                <h3>{{ __('ui.admin.reports_submitted') }}</h3>
                                <div class="admin-record-list">
                                    @forelse ($selected_user_reports as $report)
                                        <a href="{{ $report->content_url ?: route('admin') }}"><strong>{{ $report->content_type }} #{{ $report->content_id }}</strong><span>{{ $report->reason }} · {{ $report->resolved_status }}</span></a>
                                    @empty
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endforelse
                                </div>
                            </section>
                            <section>
                                <h3>{{ __('ui.admin.moderation_history') }}</h3>
                                <div class="admin-record-list">
                                    @forelse ($selected_user_moderation as $log)
                                        <div><strong>{{ $log->action }} · {{ $log->content_type }} #{{ $log->content_id }}</strong><span>{{ $log->notes ?: $log->created_at?->format('Y-m-d H:i') }}</span></div>
                                    @empty
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endforelse
                                </div>
                            </section>
                            <section>
                                <h3>{{ __('ui.admin.account_history') }}</h3>
                                <div class="admin-record-list">
                                    @forelse ($selected_user_audit as $event)
                                        <div><strong>{{ $event->event }}</strong><span>{{ $event->ip_address ?: __('ui.admin.no_ip') }} · {{ $event->created_at?->format('Y-m-d H:i') }}</span></div>
                                    @empty
                                        <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </section>
                @endif
            </section>
        </div>
    @endif

    @if ($defaultTab === 'moderation')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.moderation_feed') }}</div>
            <div class="tabs admin-tabs admin-sort" style="margin-top: 8px;">
                <a class="tab {{ $moderationSort === 'reporters' ? 'is-active' : '' }}" href="{{ route('admin.tools', ['tab' => 'moderation', 'q' => $adminSearch, 'sort' => 'reporters']) }}">
                    {{ __('ui.admin.moderation_sort_reporters') }}
                </a>
                <a class="tab {{ $moderationSort === 'recent' ? 'is-active' : '' }}" href="{{ route('admin.tools', ['tab' => 'moderation', 'q' => $adminSearch, 'sort' => 'recent']) }}">
                    {{ __('ui.admin.moderation_sort_recent') }}
                </a>
            </div>
            <div class="list admin-feed">
                @forelse ($moderation_feed as $item)
                    @include('partials.moderation-item', ['item' => $item])
                @empty
                    <div class="muted">{{ __('ui.admin.moderation_feed_empty') }}</div>
                @endforelse
            </div>
            @if (method_exists($moderation_feed, 'links'))
                <div class="admin-pagination">
                    {{ $moderation_feed->appends(['tab' => 'moderation', 'q' => $adminSearch, 'sort' => $moderationSort])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($defaultTab === 'support')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.support_tickets') }}</div>
            <div class="admin-support-list">
                @forelse ($support_tickets as $ticket)
                    @php
                        $ticketUser = $ticket->user;
                        $ticketUserSlug = $ticketUser?->slug ?? \Illuminate\Support\Str::slug($ticketUser?->name ?? '');
                        $ticketStatus = (string) ($ticket->status ?? 'open');
                        if ($ticketStatus === 'answered') {
                            $ticketStatus = 'waiting';
                        }
                        $ticketStatusKey = in_array($ticketStatus, ['open', 'waiting', 'closed'], true) ? $ticketStatus : 'open';
                        $kindKey = in_array($ticket->kind ?? '', ['question', 'bug', 'complaint'], true) ? $ticket->kind : 'question';
                        $respondedBy = $ticket->respondedBy;
                        $respondedLabelParts = array_filter([
                            $respondedBy?->name,
                            $ticket->responded_at ? $ticket->responded_at->format('Y-m-d H:i') : null,
                        ]);
                        $respondedLabel = $respondedLabelParts ? implode(' • ', $respondedLabelParts) : null;
                    @endphp
                    <details class="card admin-ticket" @if ($loop->first) open @endif>
                        <summary class="admin-ticket__summary">
                            <div>
                                <div class="admin-ticket__subject">{{ $ticket->subject }}</div>
                                <div class="muted admin-ticket__meta">
                                    <span>{{ __('ui.support.ticket_kind_' . $kindKey) }}</span>
                                    <span>•</span>
                                    @if ($ticketUser && $ticketUserSlug !== '')
                                        <a href="{{ route('profile.show', $ticketUserSlug) }}">{{ $ticketUser->name }}</a>
                                    @elseif ($ticketUser)
                                        <span>{{ $ticketUser->name }}</span>
                                    @else
                                        <span>{{ __('ui.admin.support_ticket_guest') }}</span>
                                    @endif
                                    <span>•</span>
                                    <span>{{ $ticket->created_at ? $ticket->created_at->format('Y-m-d H:i') : '' }}</span>
                                </div>
                            </div>
                            <div class="admin-ticket__status">
                                <span class="badge badge--support-{{ $ticketStatusKey }}">{{ __('ui.admin.support_ticket_status_' . $ticketStatusKey) }}</span>
                                <i data-lucide="chevron-down" class="icon details-chevron" aria-hidden="true"></i>
                            </div>
                        </summary>
                        <div class="admin-ticket__body">
                            <div class="admin-ticket__section">
                                <div class="label-text">{{ __('ui.admin.support_ticket_body') }}</div>
                                <div class="admin-ticket__content">{{ $ticket->body }}</div>
                            </div>
                            @if (!empty($ticket->response))
                                <div class="admin-ticket__section">
                                    <div class="label-text">{{ __('ui.admin.support_ticket_last_response') }}</div>
                                    <div class="admin-ticket__content">{{ $ticket->response }}</div>
                                    @if ($respondedLabel)
                                        <div class="muted">{{ $respondedLabel }}</div>
                                    @endif
                                </div>
                            @endif
                            <form class="admin-ticket__form" method="POST" action="{{ route('admin.support-tickets.respond', $ticket) }}">
                                @csrf
                                <label class="admin-ticket__field">
                                    <span class="label-text">{{ __('ui.admin.support_ticket_response') }}</span>
                                    <textarea class="input admin-ticket__textarea" name="response" rows="4" placeholder="{{ __('ui.admin.support_ticket_response_placeholder') }}">{{ $ticket->response }}</textarea>
                                </label>
                                <label class="admin-ticket__field admin-ticket__field--inline">
                                    <span class="label-text">{{ __('ui.admin.support_ticket_status') }}</span>
                                    <select class="input input--compact" name="status">
                                        <option value="open" {{ $ticketStatusKey === 'open' ? 'selected' : '' }}>{{ __('ui.admin.support_ticket_status_open') }}</option>
                                        <option value="waiting" {{ $ticketStatusKey === 'waiting' ? 'selected' : '' }}>{{ __('ui.admin.support_ticket_status_waiting') }}</option>
                                        <option value="closed" {{ $ticketStatusKey === 'closed' ? 'selected' : '' }}>{{ __('ui.admin.support_ticket_status_closed') }}</option>
                                    </select>
                                </label>
                                <div class="admin-ticket__actions">
                                    <a class="ghost-btn" href="{{ route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]) }}">{{ __('ui.support.portal_open_chat') }}</a>
                                    <button class="ghost-btn" type="submit">{{ __('ui.admin.support_ticket_save') }}</button>
                                </div>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="muted">{{ __('ui.admin.support_tickets_empty') }}</div>
                @endforelse
            </div>
            @if (method_exists($support_tickets, 'links'))
                <div class="admin-pagination">
                    {{ $support_tickets->appends(['tab' => 'support', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($defaultTab === 'media')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.media') }}</div>
            <div class="card admin-card">
                <div class="admin-table admin-table--reports">
                    <div class="admin-row admin-row--reports admin-row--head">
                        <div>{{ __('ui.admin.media_item') }}</div>
                        <div>{{ __('ui.admin.media_details') }}</div>
                        <div>{{ __('ui.admin.media_user') }}</div>
                        <div>{{ __('ui.admin.media_reported_at') }}</div>
                    </div>
                    @forelse ($media_reports as $report)
                        @php
                            $reportUrl = null;
                            if (!empty($report->content_url)) {
                                $reportUrl = \Illuminate\Support\Str::startsWith($report->content_url, ['http://', 'https://'])
                                    ? $report->content_url
                                    : asset(ltrim($report->content_url, '/'));
                            }
                            $reportUserSlug = $report->user?->slug ?? \Illuminate\Support\Str::slug($report->user?->name ?? '');
                        @endphp
                        <div class="admin-row admin-row--reports">
                            <div>
                                @if ($reportUrl)
                                    <a href="{{ $reportUrl }}" target="_blank" rel="noopener">{{ __('ui.admin.media_open') }}</a>
                                    <div class="muted">{{ $report->content_url }}</div>
                                @else
                                    <div class="muted">{{ __('ui.admin.media_missing') }}</div>
                                @endif
                            </div>
                            <div>
                                <div class="muted">{{ $report->details ?: __('ui.admin.media_details_empty') }}</div>
                                @if ($isAdminUser)
                                <div class="admin-actions">
                                    <form method="POST" action="{{ route('admin.media.resolve', $report) }}" data-confirm-submit data-confirm-message="{{ __('ui.admin.media_dismiss_confirm') }}">
                                        @csrf
                                        <button class="ghost-btn ghost-btn--compact" type="submit" name="action" value="dismiss">{{ __('ui.admin.media_dismiss') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.media.resolve', $report) }}" data-confirm-submit data-confirm-message="{{ __('ui.admin.media_remove_confirm') }}">
                                        @csrf
                                        <button class="ghost-btn ghost-btn--compact" type="submit" name="action" value="remove">{{ __('ui.admin.media_remove') }}</button>
                                    </form>
                                </div>
                                @endif
                            </div>
                            <div>
                                @if ($report->user && $reportUserSlug !== '')
                                    <a href="{{ route('profile.show', $reportUserSlug) }}">{{ $report->user->name }}</a>
                                @elseif ($report->user)
                                    {{ $report->user->name }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </div>
                            <div class="muted">{{ $report->created_at ? $report->created_at->format('Y-m-d H:i') : '' }}</div>
                        </div>
                    @empty
                        <div class="muted">{{ __('ui.admin.media_empty') }}</div>
                    @endforelse
                </div>
            </div>
            @if (method_exists($media_reports, 'links'))
                <div class="admin-pagination">
                    {{ $media_reports->appends(['tab' => 'media', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($defaultTab === 'comments')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.comments') }}</div>
            <div class="admin-content-list">
                @forelse ($comments as $comment)
                    @php
                        $commentUserSlug = $comment->user?->slug ?? \Illuminate\Support\Str::slug($comment->user?->name ?? '');
                        $canModerateComment = $isAdminUser || ! $comment->user?->isAdmin();
                        $commentUrl = $comment->post?->type === 'question'
                            ? route('questions.show', $comment->post_slug).'#comment-'.$comment->id
                            : route('project', $comment->post_slug).'#comment-'.$comment->id;
                    @endphp
                    <article class="admin-content-item admin-content-item--interaction" data-feed-card data-moderation-scope data-moderation-status="{{ $comment->moderation_status }}">
                        <div class="admin-content-item__body">
                            <header>
                                <div>
                                    <a class="admin-content-item__title" href="{{ $commentUrl }}" target="_blank" rel="noopener">{{ $comment->post?->title ?? $comment->post_slug }}</a>
                                    <div class="admin-content-item__meta admin-moderation__meta">
                                        <span>{{ __('ui.admin.comments') }}</span>
                                        @if ($comment->moderation_status !== 'approved')
                                            <span class="chip chip--moderation chip--{{ $comment->moderation_status }}">{{ __('ui.moderation.status_'.$comment->moderation_status) }}</span>
                                        @endif
                                        <time datetime="{{ $comment->created_at?->toAtomString() }}">{{ $comment->created_at?->diffForHumans() }}</time>
                                    </div>
                                </div>
                                @if ($canModerateComment)
                                    <div class="admin-content-item__actions">
                                        <button type="button" class="ghost-btn ghost-btn--compact" data-admin-restore data-admin-url="{{ route('moderation.comments.restore', $comment) }}" data-allow-label="{{ __('ui.moderation.allow') }}" data-restore-label="{{ __('ui.moderation.restore') }}" @if ($comment->moderation_status === 'approved') hidden @endif>{{ $comment->moderation_status === 'pending' ? __('ui.moderation.allow') : __('ui.moderation.restore') }}</button>
                                        <button type="button" class="ghost-btn ghost-btn--compact ghost-btn--danger" data-admin-hide data-admin-url="{{ route('moderation.comments.hide', $comment) }}" @if ($comment->moderation_status === 'hidden') hidden @endif><i data-lucide="eye-off" class="icon" aria-hidden="true"></i>{{ __('ui.moderation.hide') }}</button>
                                        <details class="admin-action-menu">
                                            <summary aria-label="{{ __('ui.admin.more_actions') }}" title="{{ __('ui.admin.more_actions') }}"><i data-lucide="more-horizontal" class="icon" aria-hidden="true"></i></summary>
                                            <div class="admin-action-menu__panel">
                                                <button type="button" class="admin-action-menu__danger" data-admin-delete data-admin-url="{{ route('admin.comments.delete', $comment) }}">{{ __('ui.admin.delete') }}</button>
                                            </div>
                                        </details>
                                    </div>
                                @endif
                            </header>
                            <p class="admin-content-item__preview">{{ $comment->body }}</p>
                            @include('admin.partials.report-context', ['context' => $comment->moderation_report_context ?? null])
                            <footer>
                                @if ($commentUserSlug !== '')
                                    <a class="admin-content-author" href="{{ route('profile.show', $commentUserSlug) }}">
                                        <img src="{{ $comment->user?->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                                        <span>{{ $comment->user?->name ?? __('ui.project.anonymous') }}</span>
                                    </a>
                                @else
                                    <span>{{ __('ui.project.anonymous') }}</span>
                                @endif
                            </footer>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                @endforelse
            </div>
            @if (method_exists($comments, 'links'))
                <div class="admin-pagination">
                    {{ $comments->appends(['tab' => 'comments', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($defaultTab === 'reviews')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.reviews') }}</div>
            <div class="admin-content-list">
                @forelse ($reviews as $review)
                    @php
                        $reviewUserSlug = $review->user?->slug ?? \Illuminate\Support\Str::slug($review->user?->name ?? '');
                        $canModerateReview = $isAdminUser || ! $review->user?->isAdmin();
                        $reviewUrl = $review->post?->type === 'question'
                            ? route('questions.show', $review->post_slug).'#review-'.$review->id
                            : route('project', $review->post_slug).'#review-'.$review->id;
                    @endphp
                    <article class="admin-content-item admin-content-item--interaction" data-feed-card data-moderation-scope data-moderation-status="{{ $review->moderation_status }}">
                        <div class="admin-content-item__body">
                            <header>
                                <div>
                                    <a class="admin-content-item__title" href="{{ $reviewUrl }}" target="_blank" rel="noopener">{{ $review->post?->title ?? $review->post_slug }}</a>
                                    <div class="admin-content-item__meta admin-moderation__meta">
                                        <span>{{ __('ui.admin.reviews') }}</span>
                                        @if ($review->moderation_status !== 'approved')
                                            <span class="chip chip--moderation chip--{{ $review->moderation_status }}">{{ __('ui.moderation.status_'.$review->moderation_status) }}</span>
                                        @endif
                                        <time datetime="{{ $review->created_at?->toAtomString() }}">{{ $review->created_at?->diffForHumans() }}</time>
                                    </div>
                                </div>
                                @if ($canModerateReview)
                                    <div class="admin-content-item__actions">
                                        <button type="button" class="ghost-btn ghost-btn--compact" data-admin-restore data-admin-url="{{ route('moderation.reviews.restore', $review) }}" data-allow-label="{{ __('ui.moderation.allow') }}" data-restore-label="{{ __('ui.moderation.restore') }}" @if ($review->moderation_status === 'approved') hidden @endif>{{ $review->moderation_status === 'pending' ? __('ui.moderation.allow') : __('ui.moderation.restore') }}</button>
                                        <button type="button" class="ghost-btn ghost-btn--compact ghost-btn--danger" data-admin-hide data-admin-url="{{ route('moderation.reviews.hide', $review) }}" @if ($review->moderation_status === 'hidden') hidden @endif><i data-lucide="eye-off" class="icon" aria-hidden="true"></i>{{ __('ui.moderation.hide') }}</button>
                                        <details class="admin-action-menu">
                                            <summary aria-label="{{ __('ui.admin.more_actions') }}" title="{{ __('ui.admin.more_actions') }}"><i data-lucide="more-horizontal" class="icon" aria-hidden="true"></i></summary>
                                            <div class="admin-action-menu__panel">
                                                <button type="button" class="admin-action-menu__danger" data-admin-delete data-admin-url="{{ route('admin.reviews.delete', $review) }}">{{ __('ui.admin.delete') }}</button>
                                            </div>
                                        </details>
                                    </div>
                                @endif
                            </header>
                            <div class="admin-review-preview">
                                <p>{{ $review->improve }}</p>
                                <p>{{ $review->why }}</p>
                                <p>{{ $review->how }}</p>
                            </div>
                            @include('admin.partials.report-context', ['context' => $review->moderation_report_context ?? null])
                            <footer>
                                @if ($reviewUserSlug !== '')
                                    <a class="admin-content-author" href="{{ route('profile.show', $reviewUserSlug) }}">
                                        <img src="{{ $review->user?->avatar ?: asset('images/avatar-default.svg') }}" alt="">
                                        <span>{{ $review->user?->name ?? __('ui.project.anonymous') }}</span>
                                    </a>
                                @else
                                    <span>{{ __('ui.project.anonymous') }}</span>
                                @endif
                            </footer>
                        </div>
                    </article>
                @empty
                    <p class="admin-empty">{{ __('ui.admin.no_records') }}</p>
                @endforelse
            </div>
            @if (method_exists($reviews, 'links'))
                <div class="admin-pagination">
                    {{ $reviews->appends(['tab' => 'reviews', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($defaultTab === 'log')
    <div>
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.moderation_log') }}</div>
            <div class="card admin-card">
                <div class="admin-table admin-table--logs">
                    <div class="admin-row admin-row--logs admin-row--head">
                        <div>{{ __('ui.admin.moderation_log_moderator') }}</div>
                        <div>{{ __('ui.admin.moderation_log_action') }}</div>
                        <div>{{ __('ui.admin.moderation_log_content') }}</div>
                        <div>{{ __('ui.admin.moderation_log_details') }}</div>
                        <div>{{ __('ui.admin.moderation_log_location') }}</div>
                        <div>{{ __('ui.admin.moderation_log_time') }}</div>
                    </div>
                    @forelse ($moderation_logs as $log)
                        @php
                            $moderatorName = $log->moderator_name ?? 'moderator';
                            $moderatorRole = strtolower($log->moderator_role ?? 'moderator');
                            $roleKeys = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
                            $moderatorRoleKey = in_array($moderatorRole, $roleKeys, true) ? $moderatorRole : 'moderator';
                            $contentLabel = trim(($log->content_type ?? '') . ' ' . ($log->content_id ?? ''));
                            $contentUrl = $log->content_url ?? null;
                            $meta = is_array($log->meta ?? null) ? $log->meta : [];
                            $metaBits = [];
                            if (!empty($meta['title'])) {
                                $metaBits[] = $meta['title'];
                            }
                            if (!empty($meta['slug'])) {
                                $metaBits[] = $meta['slug'];
                            }
                            if (!empty($meta['author_name'])) {
                                $metaBits[] = $meta['author_name'];
                            }
                            $metaSummaryParts = array_filter($metaBits);
                            if (!empty($log->notes)) {
                                $metaSummaryParts[] = $log->notes;
                            }
                            $metaSummary = $metaSummaryParts ? implode(' - ', $metaSummaryParts) : '';
                            $locationParts = array_filter([trim((string) ($log->location ?? '')), trim((string) ($log->ip_address ?? ''))]);
                            $locationLabel = $locationParts ? implode(' - ', $locationParts) : '-';
                        @endphp
                        <div class="admin-row admin-row--logs">
                            <div>
                                <div>{{ $moderatorName }}</div>
                                <span class="badge badge--{{ $moderatorRoleKey }}">{{ __('ui.roles.' . $moderatorRoleKey) }}</span>
                            </div>
                            <div>{{ $log->action ?? '' }}</div>
                            <div>
                                @if ($contentUrl)
                                    <a href="{{ $contentUrl }}" target="_blank" rel="noopener">{{ $contentLabel !== '' ? $contentLabel : __('ui.admin.moderation_log_content') }}</a>
                                    <div class="muted">{{ $contentUrl }}</div>
                                @else
                                    <div>{{ $contentLabel !== '' ? $contentLabel : '-' }}</div>
                                @endif
                            </div>
                            <div class="muted">{{ $metaSummary !== '' ? \Illuminate\Support\Str::limit($metaSummary, 160) : '-' }}</div>
                            <div class="muted">{{ $locationLabel }}</div>
                            <div class="muted">{{ $log->created_at ? $log->created_at->format('Y-m-d H:i') : '' }}</div>
                        </div>
                    @empty
                        <div class="muted">{{ __('ui.admin.moderation_log_empty') }}</div>
                    @endforelse
                </div>
            </div>
            @if (method_exists($moderation_logs, 'links'))
                <div class="admin-pagination">
                    {{ $moderation_logs->appends(['tab' => 'log', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>
    @endif

    @if ($isAdminUser && $defaultTab === 'analytics')
        @php
            $analyticsDays = collect($admin_analytics['days'] ?? []);
            $analyticsMax = max(1, (int) $analyticsDays->max(fn ($day) => (int) $day['users'] + (int) $day['content'] + (int) $day['comments'] + (int) $day['reports']));
        @endphp
        <section class="section admin-section">
            <div class="admin-section-toolbar">
                <div>
                    <div class="section-title">{{ __('ui.admin.analytics') }}</div>
                    <p class="muted">{{ __('ui.admin.analytics_period') }}</p>
                </div>
            </div>
            <dl class="admin-overview__numbers admin-analytics-totals">
                <div><dt>{{ __('ui.admin.new_users') }}</dt><dd>{{ number_format($admin_analytics['totals']['users'] ?? 0) }}</dd></div>
                <div><dt>{{ __('ui.admin.new_content') }}</dt><dd>{{ number_format($admin_analytics['totals']['content'] ?? 0) }}</dd></div>
                <div><dt>{{ __('ui.admin.new_comments') }}</dt><dd>{{ number_format($admin_analytics['totals']['comments'] ?? 0) }}</dd></div>
                <div><dt>{{ __('ui.admin.new_reports') }}</dt><dd>{{ number_format($admin_analytics['totals']['reports'] ?? 0) }}</dd></div>
                <div><dt>{{ __('ui.admin.active_users_30d') }}</dt><dd>{{ number_format($admin_analytics['active_users'] ?? 0) }}</dd></div>
                <div><dt>{{ __('ui.admin.open_collaborations') }}</dt><dd>{{ number_format($admin_analytics['open_collaborations'] ?? 0) }}</dd></div>
            </dl>
            <section class="admin-activity-chart" aria-labelledby="admin-activity-chart-title">
                <header>
                    <h2 id="admin-activity-chart-title">{{ __('ui.admin.daily_activity') }}</h2>
                    <span>{{ __('ui.admin.analytics_period') }}</span>
                </header>
                <div class="admin-activity-chart__bars">
                    @foreach ($analyticsDays as $day)
                        @php $activityTotal = (int) $day['users'] + (int) $day['content'] + (int) $day['comments'] + (int) $day['reports']; @endphp
                        <div class="admin-activity-chart__day" role="img" aria-label="{{ __('ui.admin.daily_activity_value', ['date' => $day['date'], 'count' => $activityTotal]) }}" title="{{ $day['date'] }} · {{ $activityTotal }}">
                            <span style="--activity-height: {{ $activityTotal > 0 ? max(4, round(($activityTotal / $analyticsMax) * 100)) : 2 }}%"></span>
                        </div>
                    @endforeach
                </div>
                @if ($analyticsDays->isNotEmpty())
                    <div class="admin-activity-chart__range"><time>{{ $analyticsDays->first()['date'] }}</time><time>{{ $analyticsDays->last()['date'] }}</time></div>
                @endif
            </section>
            <div class="admin-card admin-card--scroll">
                <div class="admin-table admin-analytics-table">
                    <div class="admin-row admin-analytics-row admin-row--head">
                        <div>{{ __('ui.admin.date') }}</div><div>{{ __('ui.admin.users') }}</div><div>{{ __('ui.admin.content') }}</div><div>{{ __('ui.admin.comments') }}</div><div>{{ __('ui.admin.report_count') }}</div>
                    </div>
                    @foreach (($admin_analytics['days'] ?? collect())->reverse() as $day)
                        <div class="admin-row admin-analytics-row">
                            <time datetime="{{ $day['date'] }}">{{ $day['date'] }}</time>
                            <div>{{ $day['users'] }}</div><div>{{ $day['content'] }}</div><div>{{ $day['comments'] }}</div><div>{{ $day['reports'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($isAdminUser && $defaultTab === 'system')
        <section class="section admin-section">
            <div class="admin-section-toolbar">
                <div>
                    <div class="section-title">{{ __('ui.admin.system') }}</div>
                    <p class="muted">{{ __('ui.admin.system_helper') }}</p>
                </div>
            </div>
            <div class="admin-system-grid">
                @foreach ([
                    ['label' => __('ui.admin.system_database'), 'healthy' => $system_status['database'] ?? false, 'value' => __('ui.admin.system_database_value', ['batch' => $system_status['migration_batch'] ?? '—'])],
                    ['label' => __('ui.admin.system_storage'), 'healthy' => ($system_status['storage'] ?? false) && ($system_status['public_storage'] ?? false), 'value' => ($system_status['public_storage'] ?? false) ? __('ui.admin.system_link_ready') : __('ui.admin.system_link_missing')],
                    ['label' => __('ui.admin.system_scheduler'), 'healthy' => $system_status['scheduler'] ?? false, 'value' => $system_status['scheduler_at']?->diffForHumans() ?? __('ui.admin.system_never_seen')],
                    ['label' => __('ui.admin.system_queue'), 'healthy' => ($system_status['failed_jobs'] ?? 0) === 0, 'value' => __('ui.admin.system_queue_value', ['driver' => $system_status['queue'] ?? '—', 'jobs' => $system_status['queue_jobs'] ?? '—', 'failed' => $system_status['failed_jobs'] ?? '—'])],
                    ['label' => __('ui.admin.system_mail'), 'healthy' => ($system_status['mail'] ?? '') !== '', 'value' => $system_status['mail'] ?? '—'],
                    ['label' => __('ui.admin.system_moderation'), 'healthy' => $system_status['moderation_text'] ?? false, 'value' => __('ui.admin.system_moderation_value', ['images' => ($system_status['moderation_images'] ?? false) ? __('ui.admin.on') : __('ui.admin.off'), 'text' => ($system_status['moderation_text'] ?? false) ? __('ui.admin.on') : __('ui.admin.off')])],
                ] as $check)
                    <article class="admin-system-check {{ $check['healthy'] ? 'is-healthy' : 'needs-attention' }}">
                        <header><i data-lucide="{{ $check['healthy'] ? 'circle-check' : 'triangle-alert' }}" class="icon" aria-hidden="true"></i><h2>{{ $check['label'] }}</h2><strong>{{ $check['healthy'] ? __('ui.admin.healthy') : __('ui.admin.attention') }}</strong></header>
                        <p>{{ $check['value'] }}</p>
                    </article>
                @endforeach
            </div>
            <dl class="admin-runtime-facts">
                <div><dt>{{ __('ui.admin.environment') }}</dt><dd>{{ $system_status['environment'] ?? '—' }}</dd></div>
                <div><dt>Debug</dt><dd>{{ ($system_status['debug'] ?? false) ? __('ui.admin.on') : __('ui.admin.off') }}</dd></div>
                <div><dt>PHP</dt><dd>{{ $system_status['php'] ?? '—' }}</dd></div>
                <div><dt>Laravel</dt><dd>{{ $system_status['laravel'] ?? '—' }}</dd></div>
            </dl>
            @if (! ($system_status['scheduler'] ?? false))
                <div class="admin-system-note">
                    <strong>{{ __('ui.admin.system_scheduler_action') }}</strong>
                    <code>E:\xampp\php\php.exe artisan schedule:work</code>
                </div>
            @endif
        </section>
    @endif

    @if ($isAdminUser && $defaultTab === 'promos')
        <div>
            <section class="section admin-section">
                <div class="section-title">{{ __('ui.admin.promos') }}</div>
                <div class="card admin-card">
                <div class="admin-table admin-table--promos">
                    <div class="admin-row admin-row--promos admin-row--head">
                        <div>{{ __('ui.admin.promo_label') }}</div>
                        <div>{{ __('ui.admin.promo_url') }}</div>
                        <div>{{ __('ui.admin.promo_order') }}</div>
                        <div>{{ __('ui.admin.promo_schedule') }}</div>
                        <div>{{ __('ui.admin.promo_limit') }}</div>
                        <div>{{ __('ui.admin.promo_status') }}</div>
                        <div>{{ __('ui.admin.promo_shown') }}</div>
                        <div>{{ __('ui.admin.promo_clicks') }}</div>
                        <div>{{ __('ui.admin.actions') }}</div>
                    </div>
                    <form class="admin-promo-create" method="POST" action="{{ route('admin.promos.store') }}">
                        @csrf
                        <div class="admin-row admin-row--promos admin-row--create">
                            <input class="input input--compact" type="text" name="label" placeholder="{{ __('ui.admin.promo_label_placeholder') }}" required>
                            <input class="input input--compact" type="url" name="url" placeholder="https://..." required>
                            <input class="input input--compact" type="number" name="sort_order" min="0" max="9999" value="0">
                            <div class="promo-schedule">
                                <input class="input input--compact" type="datetime-local" name="starts_at" placeholder="{{ __('ui.admin.promo_start') }}">
                                <input class="input input--compact" type="datetime-local" name="ends_at" placeholder="{{ __('ui.admin.promo_end') }}">
                            </div>
                            <div class="promo-limit">
                                <input class="input input--compact" type="number" name="max_impressions" min="1" max="1000000000" placeholder="{{ __('ui.admin.promo_limit_placeholder') }}">
                                <label class="promo-unlimited">
                                    <input type="checkbox" name="unlimited" value="1">
                                    <span>{{ __('ui.admin.promo_unlimited') }}</span>
                                </label>
                            </div>
                            <select class="input input--compact" name="is_active">
                                <option value="1">{{ __('ui.admin.promo_active') }}</option>
                                <option value="0">{{ __('ui.admin.promo_inactive') }}</option>
                            </select>
                            <div class="muted">0 / ∞</div>
                            <div class="muted">0</div>
                            <button class="ghost-btn" type="submit">{{ __('ui.admin.promo_add') }}</button>
                        </div>
                    </form>
                    @forelse ($topbar_promos as $promo)
                        @php
                            $promoId = $promo->id ?? null;
                        @endphp
                        <form id="promo-update-{{ $promoId }}" method="POST" action="{{ route('admin.promos.update', $promo) }}">
                            @csrf
                            @method('PUT')
                        </form>
                        <div class="admin-row admin-row--promos">
                            <input class="input input--compact" form="promo-update-{{ $promoId }}" type="text" name="label" value="{{ $promo->label }}" required>
                            <input class="input input--compact" form="promo-update-{{ $promoId }}" type="url" name="url" value="{{ $promo->url }}" required>
                            <input class="input input--compact" form="promo-update-{{ $promoId }}" type="number" name="sort_order" min="0" max="9999" value="{{ (int) $promo->sort_order }}">
                            <div class="promo-schedule">
                                <input class="input input--compact" form="promo-update-{{ $promoId }}" type="datetime-local" name="starts_at" value="{{ $promo->starts_at ? $promo->starts_at->format('Y-m-d\\TH:i') : '' }}">
                                <input class="input input--compact" form="promo-update-{{ $promoId }}" type="datetime-local" name="ends_at" value="{{ $promo->ends_at ? $promo->ends_at->format('Y-m-d\\TH:i') : '' }}">
                            </div>
                            <div class="promo-limit">
                                <input class="input input--compact" form="promo-update-{{ $promoId }}" type="number" name="max_impressions" min="1" max="1000000000" value="{{ $promo->max_impressions ?? '' }}" placeholder="{{ __('ui.admin.promo_limit_placeholder') }}">
                                <label class="promo-unlimited">
                                    <input type="checkbox" form="promo-update-{{ $promoId }}" name="unlimited" value="1" {{ $promo->max_impressions === null ? 'checked' : '' }}>
                                    <span>{{ __('ui.admin.promo_unlimited') }}</span>
                                </label>
                            </div>
                            <select class="input input--compact" form="promo-update-{{ $promoId }}" name="is_active">
                                <option value="1" {{ $promo->is_active ? 'selected' : '' }}>{{ __('ui.admin.promo_active') }}</option>
                                <option value="0" {{ !$promo->is_active ? 'selected' : '' }}>{{ __('ui.admin.promo_inactive') }}</option>
                            </select>
                            <div class="muted">
                                {{ (int) ($promo->impressions_count ?? 0) }} / {{ $promo->max_impressions === null ? '∞' : (int) $promo->max_impressions }}
                            </div>
                            <div class="muted">{{ (int) ($promo->clicks_count ?? 0) }}</div>
                            <div class="admin-row__actions">
                                <button class="ghost-btn" type="submit" form="promo-update-{{ $promoId }}">{{ __('ui.admin.save') }}</button>
                                <form method="POST" action="{{ route('admin.promos.delete', $promo) }}" data-confirm-submit data-confirm-message="{{ __('ui.js.admin_delete_confirm') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ghost-btn ghost-btn--danger" type="submit">{{ __('ui.admin.delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="muted">{{ __('ui.admin.promos_empty') }}</div>
                    @endforelse
                </div>
            </div>
        </section>
        </div>
    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
