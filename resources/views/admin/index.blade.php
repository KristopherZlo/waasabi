@extends('layouts.app')

@section('title', __('ui.admin.title'))
@section('page', 'admin')

@section('content')
    @php
        $adminSearch = $admin_search ?? (string) request('q', '');
        $currentTab = (string) request('tab', '');
        $isAdminUser = Auth::user()?->isAdmin() ?? false;
        $roleOptions = config('roles.order', ['user', 'maker', 'moderator', 'admin']);
        $availableTabs = $isAdminUser
            ? ['users', 'moderation', 'reports', 'support', 'media', 'comments', 'reviews', 'log', 'promos']
            : ['moderation', 'reports', 'support', 'media', 'comments', 'reviews', 'log'];
        if (!in_array($currentTab, $availableTabs, true)) {
            $currentTab = '';
        }
        $defaultTab = $currentTab !== '' ? $currentTab : ($isAdminUser ? 'users' : 'moderation');
        $moderationSort = $moderation_sort ?? 'reporters';
        $moderationSort = in_array($moderationSort, ['reporters', 'recent'], true) ? $moderationSort : 'reporters';
    @endphp

    <section class="hero">
        <h1>{{ __('ui.admin.title') }}</h1>
        <p>{{ __('ui.admin.subtitle') }}</p>
    </section>

    <div class="admin-search">
        <form class="admin-search__form" method="GET" action="{{ route('admin') }}">
            <input class="input" type="search" name="q" value="{{ $adminSearch }}" placeholder="{{ __('ui.admin.search_placeholder') }}" aria-label="{{ __('ui.admin.search_placeholder') }}">
            @if ($defaultTab !== '')
                <input type="hidden" name="tab" value="{{ $defaultTab }}">
            @endif
            @if ($defaultTab === 'moderation')
                <input type="hidden" name="sort" value="{{ $moderationSort }}">
            @endif
            <button class="ghost-btn" type="submit">{{ __('ui.admin.search') }}</button>
        </form>
    </div>

    <div class="tabs admin-tabs" style="margin-top: 24px;">
        @if ($isAdminUser)
            <button class="tab {{ $defaultTab === 'users' ? 'is-active' : '' }}" type="button" data-tab="users">{{ __('ui.admin.users') }}</button>
        @endif
        <button class="tab {{ $defaultTab === 'moderation' ? 'is-active' : '' }}" type="button" data-tab="moderation">{{ __('ui.admin.moderation_feed') }}</button>
        <button class="tab {{ $defaultTab === 'reports' ? 'is-active' : '' }}" type="button" data-tab="reports">{{ __('ui.admin.reported_posts') }}</button>
        <button class="tab {{ $defaultTab === 'support' ? 'is-active' : '' }}" type="button" data-tab="support">{{ __('ui.admin.support_tickets') }}</button>
        <button class="tab {{ $defaultTab === 'media' ? 'is-active' : '' }}" type="button" data-tab="media">{{ __('ui.admin.media') }}</button>
        <button class="tab {{ $defaultTab === 'comments' ? 'is-active' : '' }}" type="button" data-tab="comments">{{ __('ui.admin.comments') }}</button>
        <button class="tab {{ $defaultTab === 'reviews' ? 'is-active' : '' }}" type="button" data-tab="reviews">{{ __('ui.admin.reviews') }}</button>
        <button class="tab {{ $defaultTab === 'log' ? 'is-active' : '' }}" type="button" data-tab="log">{{ __('ui.admin.moderation_log') }}</button>
        @if ($isAdminUser)
            <button class="tab {{ $defaultTab === 'promos' ? 'is-active' : '' }}" type="button" data-tab="promos">{{ __('ui.admin.promos') }}</button>
        @endif
    </div>

    <div class="admin-tab-panels" data-tab-autoheight="1">
    @if ($isAdminUser)
        <div class="tab-panel {{ $defaultTab === 'users' ? 'is-active' : '' }}" data-tab-panel="users">
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
                                <div>
                                    @if (!empty($userSlug))
                                        <a href="{{ route('profile.show', $userSlug) }}">{{ $user->name }}</a>
                                    @else
                                        <div>{{ $user->name }}</div>
                                    @endif
                                    @if ($isBanned)
                                        <span class="badge badge--banned">{{ __('ui.admin.banned') }}</span>
                                    @endif
                                </div>
                                <div class="muted">{{ $user->email }}</div>
                                <div>
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}">
                                        @csrf
                                        <select class="input input--compact" name="role" data-confirm-select data-confirm-message="{{ __('ui.js.admin_role_confirm') }}">
                                            @if (!empty($user->is_banned))
                                                <option value="BANNED" selected disabled>{{ __('ui.admin.banned') }}</option>
                                            @endif
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
                                            <button class="ghost-btn {{ $isBanned ? '' : 'ghost-btn--danger' }}" type="submit">
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
            </section>
        </div>
    @endif

    <div class="tab-panel {{ $defaultTab === 'moderation' ? 'is-active' : '' }}" data-tab-panel="moderation">
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.moderation_feed') }}</div>
            <div class="tabs admin-tabs admin-sort" style="margin-top: 8px;">
                <a class="tab {{ $moderationSort === 'reporters' ? 'is-active' : '' }}" href="{{ route('admin', ['tab' => 'moderation', 'q' => $adminSearch, 'sort' => 'reporters']) }}">
                    {{ __('ui.admin.moderation_sort_reporters') }}
                </a>
                <a class="tab {{ $moderationSort === 'recent' ? 'is-active' : '' }}" href="{{ route('admin', ['tab' => 'moderation', 'q' => $adminSearch, 'sort' => 'recent']) }}">
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

    <div class="tab-panel {{ $defaultTab === 'reports' ? 'is-active' : '' }}" data-tab-panel="reports">
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.reported_posts') }}</div>
            <div class="card admin-card">
                <div class="admin-table admin-table--reports">
                    <div class="admin-row admin-row--reports admin-row--head">
                        <div>{{ __('ui.admin.report_post') }}</div>
                        <div>{{ __('ui.admin.report_comment') }}</div>
                        <div>{{ __('ui.admin.report_points') }}</div>
                        <div>{{ __('ui.admin.report_count') }}</div>
                    </div>
                    @forelse ($reported_posts as $entry)
                        @php
                            $post = $entry['post'];
                        @endphp
                        <div class="admin-row admin-row--reports">
                            <div>
                                <a href="{{ route('project', $post->slug) }}">{{ $post->title }}</a>
                                <div class="muted">{{ $post->slug }}</div>
                            </div>
                            <div class="muted">{{ $entry['details'] ?: __('ui.admin.report_comment_empty') }}</div>
                            <div>{{ $entry['points'] }}</div>
                            <div>{{ $entry['count'] }}</div>
                        </div>
                    @empty
                        <div class="muted">{{ __('ui.admin.reported_posts_empty') }}</div>
                    @endforelse
                </div>
            </div>
            @if (method_exists($reported_posts, 'links'))
                <div class="admin-pagination">
                    {{ $reported_posts->appends(['tab' => 'reports', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="tab-panel {{ $defaultTab === 'support' ? 'is-active' : '' }}" data-tab-panel="support">
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

    <div class="tab-panel {{ $defaultTab === 'media' ? 'is-active' : '' }}" data-tab-panel="media">
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
                            <div class="muted">{{ $report->details ?: __('ui.admin.media_details_empty') }}</div>
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

    <div class="tab-panel {{ $defaultTab === 'comments' ? 'is-active' : '' }}" data-tab-panel="comments">
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.comments') }}</div>
            <div class="card admin-card">
                <div class="admin-table">
                    <div class="admin-row admin-row--head">
                        <div>{{ __('ui.admin.comment_author') }}</div>
                        <div>{{ __('ui.admin.comment_body') }}</div>
                        <div>{{ __('ui.admin.actions') }}</div>
                    </div>
                    @foreach ($comments as $comment)
                        @php
                            $commentUserSlug = $comment->user?->slug ?? \Illuminate\Support\Str::slug($comment->user?->name ?? '');
                        @endphp
                        <div class="admin-row">
                            <div>
                                <div>
                                    @if (!empty($commentUserSlug))
                                        <a href="{{ route('profile.show', $commentUserSlug) }}">{{ $comment->user?->name ?? __('ui.project.anonymous') }}</a>
                                    @else
                                        {{ $comment->user?->name ?? __('ui.project.anonymous') }}
                                    @endif
                                </div>
                                <div class="muted">{{ $comment->post_slug }}</div>
                            </div>
                            <div class="muted">{{ $comment->body }}</div>
                            <div>
                                @can('admin')
                                    <form method="POST" action="{{ route('admin.comments.delete', $comment) }}" data-confirm-submit data-confirm-message="{{ __('ui.js.admin_delete_confirm') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ghost-btn ghost-btn--danger" type="submit">{{ __('ui.admin.delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @if (method_exists($comments, 'links'))
                <div class="admin-pagination">
                    {{ $comments->appends(['tab' => 'comments', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="tab-panel {{ $defaultTab === 'reviews' ? 'is-active' : '' }}" data-tab-panel="reviews">
        <section class="section admin-section">
            <div class="section-title">{{ __('ui.admin.reviews') }}</div>
            <div class="card admin-card">
                <div class="admin-table">
                    <div class="admin-row admin-row--head">
                        <div>{{ __('ui.admin.review_author') }}</div>
                        <div>{{ __('ui.admin.review_body') }}</div>
                        <div>{{ __('ui.admin.actions') }}</div>
                    </div>
                    @foreach ($reviews as $review)
                        @php
                            $reviewUserSlug = $review->user?->slug ?? \Illuminate\Support\Str::slug($review->user?->name ?? '');
                        @endphp
                        <div class="admin-row">
                            <div>
                                <div>
                                    @if (!empty($reviewUserSlug))
                                        <a href="{{ route('profile.show', $reviewUserSlug) }}">{{ $review->user?->name ?? __('ui.project.anonymous') }}</a>
                                    @else
                                        {{ $review->user?->name ?? __('ui.project.anonymous') }}
                                    @endif
                                </div>
                                <div class="muted">{{ $review->post_slug }}</div>
                            </div>
                            <div class="muted">
                                <div>{{ $review->improve }}</div>
                                <div>{{ $review->why }}</div>
                                <div>{{ $review->how }}</div>
                            </div>
                            <div>
                                @can('admin')
                                    <form method="POST" action="{{ route('admin.reviews.delete', $review) }}" data-confirm-submit data-confirm-message="{{ __('ui.js.admin_delete_confirm') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ghost-btn ghost-btn--danger" type="submit">{{ __('ui.admin.delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @if (method_exists($reviews, 'links'))
                <div class="admin-pagination">
                    {{ $reviews->appends(['tab' => 'reviews', 'q' => $adminSearch])->links() }}
                </div>
            @endif
        </section>
    </div>

    <div class="tab-panel {{ $defaultTab === 'log' ? 'is-active' : '' }}" data-tab-panel="log">
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

    @if ($isAdminUser)
        <div class="tab-panel {{ $defaultTab === 'promos' ? 'is-active' : '' }}" data-tab-panel="promos">
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
@endsection
