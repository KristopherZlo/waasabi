@extends('layouts.support')

@section('title', __('ui.support.title_full'))
@section('page', 'support')

@section('content')
    @php
        $supportTickets = collect($support_tickets ?? []);
        $ticketsReady = (bool) ($support_tickets_ready ?? false);
        $canOpenTicket = (bool) ($support_can_open_ticket ?? false);
        $isBanned = (bool) ($support_is_banned ?? false);
        $isLoggedIn = Auth::check();
        $isStaff = (bool) ($support_is_staff ?? false);
        $currentUserId = (int) (Auth::id() ?? 0);
        $supportThreads = $support_threads ?? [];
        $activeTicket = $support_active_ticket ?? null;
        $supportArticles = collect($support_articles ?? []);
        $supportArticlePayload = $supportArticles->values()->all();
        $activeTab = (string) request('tab', 'home');
        $tabOptions = ['home', 'tickets', 'new'];
        if (!in_array($activeTab, $tabOptions, true)) {
            $activeTab = request()->has('ticket') ? 'tickets' : 'home';
        }
    @endphp

    <div class="support-portal" data-support-root data-support-articles='@json($supportArticlePayload)'>
        <section class="support-tab-panel support-tab-panel--home {{ $activeTab === 'home' ? 'is-active' : '' }}" data-tab-panel="home">
            <div class="support-home">
                <div class="support-home__inner">
                    <div class="support-search" role="search">
                        <i data-lucide="search" class="icon"></i>
                        <input
                            class="support-search__input"
                            type="search"
                            placeholder="{{ __('ui.support.portal_search_placeholder') }}"
                            aria-label="{{ __('ui.support.portal_search_label') }}"
                            data-support-search
                            autocomplete="off"
                            spellcheck="false"
                        >
                    </div>

                    <div class="support-cards">
                        <article class="support-card">
                            <div class="support-card__title">{{ __('ui.support.portal_card_knowledge_title') }}</div>
                            <p class="support-card__text">{{ __('ui.support.portal_card_knowledge_text') }}</p>
                            <a class="ghost-btn support-card__cta" href="{{ route('support', ['tab' => 'home']) }}#support-knowledge">{{ __('ui.support.portal_card_knowledge_cta') }}</a>
                        </article>
                        <article class="support-card">
                            <div class="support-card__title">{{ __('ui.support.portal_card_tickets_title') }}</div>
                            <p class="support-card__text">{{ __('ui.support.portal_card_tickets_text') }}</p>
                            <a class="ghost-btn support-card__cta" href="{{ route('support', ['tab' => 'tickets']) }}">{{ __('ui.support.portal_card_tickets_cta') }}</a>
                        </article>
                        <article class="support-card">
                            <div class="support-card__title">{{ __('ui.support.portal_card_new_title') }}</div>
                            <p class="support-card__text">{{ __('ui.support.portal_card_new_text') }}</p>
                            <a class="ghost-btn support-card__cta" href="{{ route('support', ['tab' => 'new']) }}">{{ __('ui.support.portal_card_new_cta') }}</a>
                        </article>
                    </div>

                    <div class="support-knowledge" id="support-knowledge">
                        <div class="support-knowledge__header">
                            <h3>{{ __('ui.support.portal_kb_title') }}</h3>
                            <p>{{ __('ui.support.portal_kb_text') }}</p>
                        </div>
                        <div class="support-knowledge__list">
                            @foreach ($supportArticles as $article)
                                <a
                                    class="support-knowledge__item"
                                    href="{{ $article['url'] ?? '#' }}"
                                    data-support-item
                                    data-support-search="{{ $article['search'] ?? '' }}"
                                >
                                    <div class="support-knowledge__title">{{ $article['title'] ?? '' }}</div>
                                    <div class="support-knowledge__text">{{ $article['summary'] ?? '' }}</div>
                                </a>
                            @endforeach
                            <div class="support-empty support-empty--inline" data-support-empty @if ($supportArticles->isNotEmpty()) hidden @endif>
                                <div class="support-empty__title">{{ __('ui.support.portal_kb_empty') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="support-tab-panel {{ $activeTab === 'tickets' ? 'is-active' : '' }}" data-tab-panel="tickets">
            <div class="support-section">
                <div class="support-section__header">
                    <h2>{{ __('ui.support.portal_nav_tickets') }}</h2>
                </div>

                @if (!$ticketsReady)
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ __('ui.support.portal_tickets_unavailable') }}</div>
                    </div>
                @elseif (!$isLoggedIn && !$isStaff)
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ __('ui.support.portal_tickets_login') }}</div>
                        <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.topbar.login') }}</a>
                    </div>
                @elseif ($supportTickets->isEmpty())
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ $isStaff ? __('ui.support.portal_tickets_empty_staff') : __('ui.support.portal_tickets_empty') }}</div>
                    </div>
                @else
                    @php
                        $showChat = (bool) $activeTicket;
                    @endphp
                    @if (!$showChat && !$isStaff)
                        <div class="support-ticket-list support-ticket-list--full">
                            @foreach ($supportTickets as $ticket)
                                @php
                                    $ticketStatus = (string) ($ticket->status ?? 'open');
                                    if ($ticketStatus === 'answered') {
                                        $ticketStatus = 'waiting';
                                    }
                                    $ticketStatusKey = in_array($ticketStatus, ['open', 'waiting', 'closed'], true) ? $ticketStatus : 'open';
                                    $ticketKindKey = in_array($ticket->kind ?? '', ['question', 'bug', 'complaint'], true) ? $ticket->kind : 'question';
                                    $ticketCreated = $ticket->created_at ? $ticket->created_at->format('d.m.Y') : '';
                                    $ticketActive = $activeTicket && $activeTicket->id === $ticket->id;
                                    $ticketOwner = $ticket->user?->name ?? __('ui.support.portal_guest');
                                    $ticketBody = preg_replace('/\s+/', ' ', strip_tags((string) ($ticket->body ?? '')));
                                    $ticketPreview = \Illuminate\Support\Str::limit(trim((string) $ticketBody), 128, '');
                                    $showMeta = ($ticketCreated !== '') || $isStaff;
                                @endphp
                                <a class="support-ticket-link {{ $ticketActive ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]) }}">
                                    <div class="support-ticket-link__header">
                                        <div class="support-ticket-link__number">{{ __('ui.support.portal_ticket_number', ['id' => $ticket->id]) }}</div>
                                        <span class="badge badge--support-{{ $ticketStatusKey }}">{{ __('ui.support.portal_ticket_status_' . $ticketStatusKey) }}</span>
                                    </div>
                                    <div class="support-ticket-link__subject">{{ $ticket->subject }}</div>
                                    <div class="support-ticket-link__tags">
                                        <span class="support-ticket-link__tag">{{ __('ui.support.ticket_kind_' . $ticketKindKey) }}</span>
                                        <span class="support-ticket-link__tag">{{ __('ui.support.portal_ticket_status_' . $ticketStatusKey) }}</span>
                                    </div>
                                    @if ($ticketPreview !== '')
                                        <div class="support-ticket-link__preview">{{ $ticketPreview }}</div>
                                    @endif
                                    @if ($showMeta)
                                        <div class="support-ticket-link__meta">
                                            @if ($ticketCreated !== '')
                                                <span>{{ __('ui.support.portal_ticket_created', ['date' => $ticketCreated]) }}</span>
                                            @endif
                                            @if ($isStaff)
                                                <span>&middot;</span>
                                                <span>{{ __('ui.support.portal_ticket_owner', ['name' => $ticketOwner]) }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="support-ticket-layout {{ $showChat ? 'support-ticket-layout--active' : '' }}">
                            <div class="support-ticket-list">
                                @foreach ($supportTickets as $ticket)
                                    @php
                                        $ticketStatus = (string) ($ticket->status ?? 'open');
                                        if ($ticketStatus === 'answered') {
                                            $ticketStatus = 'waiting';
                                        }
                                        $ticketStatusKey = in_array($ticketStatus, ['open', 'waiting', 'closed'], true) ? $ticketStatus : 'open';
                                        $ticketKindKey = in_array($ticket->kind ?? '', ['question', 'bug', 'complaint'], true) ? $ticket->kind : 'question';
                                        $ticketCreated = $ticket->created_at ? $ticket->created_at->format('d.m.Y') : '';
                                        $ticketActive = $activeTicket && $activeTicket->id === $ticket->id;
                                        $ticketOwner = $ticket->user?->name ?? __('ui.support.portal_guest');
                                        $ticketBody = preg_replace('/\s+/', ' ', strip_tags((string) ($ticket->body ?? '')));
                                        $ticketPreview = \Illuminate\Support\Str::limit(trim((string) $ticketBody), 128, '');
                                        $showMeta = ($ticketCreated !== '') || $isStaff;
                                    @endphp
                                    <a class="support-ticket-link {{ $ticketActive ? 'is-active' : '' }}" href="{{ route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]) }}">
                                        <div class="support-ticket-link__header">
                                            <div class="support-ticket-link__number">{{ __('ui.support.portal_ticket_number', ['id' => $ticket->id]) }}</div>
                                            <span class="badge badge--support-{{ $ticketStatusKey }}">{{ __('ui.support.portal_ticket_status_' . $ticketStatusKey) }}</span>
                                        </div>
                                        <div class="support-ticket-link__subject">{{ $ticket->subject }}</div>
                                        <div class="support-ticket-link__tags">
                                            <span class="support-ticket-link__tag">{{ __('ui.support.ticket_kind_' . $ticketKindKey) }}</span>
                                            <span class="support-ticket-link__tag">{{ __('ui.support.portal_ticket_status_' . $ticketStatusKey) }}</span>
                                        </div>
                                        @if ($ticketPreview !== '')
                                            <div class="support-ticket-link__preview">{{ $ticketPreview }}</div>
                                        @endif
                                        @if ($showMeta)
                                            <div class="support-ticket-link__meta">
                                                @if ($ticketCreated !== '')
                                                    <span>{{ __('ui.support.portal_ticket_created', ['date' => $ticketCreated]) }}</span>
                                                @endif
                                                @if ($isStaff)
                                                    <span>&middot;</span>
                                                    <span>{{ __('ui.support.portal_ticket_owner', ['name' => $ticketOwner]) }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>

                            <div class="support-chat">
                                @if ($activeTicket)
                                    @php
                                        $activeStatus = (string) ($activeTicket->status ?? 'open');
                                        if ($activeStatus === 'answered') {
                                            $activeStatus = 'waiting';
                                        }
                                        $activeStatusKey = in_array($activeStatus, ['open', 'waiting', 'closed'], true) ? $activeStatus : 'open';
                                        $thread = $supportThreads[$activeTicket->id] ?? [];
                                        $counterpartName = $activeTicket->user?->name ?? __('ui.support.portal_guest');
                                        if (!$isStaff) {
                                            $supportName = '';
                                            foreach ($thread as $message) {
                                                if (($message['author_type'] ?? '') === 'support' && !empty($message['author_name'])) {
                                                    $supportName = (string) $message['author_name'];
                                                }
                                            }
                                            $counterpartName = $supportName !== '' ? $supportName : ($activeTicket->respondedBy?->name ?? $activeTicket->resolvedBy?->name ?? __('ui.support.portal_support_team'));
                                        }
                                    @endphp
                                    <div class="support-chat__header">
                                        <div>
                                            <div class="support-chat__title">{{ __('ui.support.portal_ticket_number', ['id' => $activeTicket->id]) }}</div>
                                            <div class="support-chat__meta">
                                                <span>{{ __('ui.support.portal_chat_with', ['name' => $counterpartName]) }}</span>
                                                <span>&middot;</span>
                                                <span>{{ __('ui.support.portal_ticket_status_label', ['status' => __('ui.support.portal_ticket_status_' . $activeStatusKey)]) }}</span>
                                            </div>
                                        </div>
                                        <span class="badge badge--support-{{ $activeStatusKey }}">{{ __('ui.support.portal_ticket_status_' . $activeStatusKey) }}</span>
                                    </div>

                                    <div class="support-chat__messages">
                                        @foreach ($thread as $message)
                                            @php
                                                $isUserMessage = ($message['author_type'] ?? '') === 'user';
                                                $messageAuthorId = (int) ($message['author_id'] ?? 0);
                                                $isOwnMessage = $isStaff
                                                    ? ($messageAuthorId > 0 && $messageAuthorId === $currentUserId)
                                                    : $isUserMessage;
                                                if ($isStaff) {
                                                    $authorLabel = $isUserMessage
                                                        ? ($message['author_name'] ?? __('ui.support.portal_guest'))
                                                        : (($messageAuthorId > 0 && $messageAuthorId === $currentUserId)
                                                            ? __('ui.support.portal_you')
                                                            : ($message['author_name'] ?? __('ui.support.portal_support_team')));
                                                } else {
                                                    $authorLabel = $isUserMessage
                                                        ? __('ui.support.portal_you')
                                                        : ($message['author_name'] ?? __('ui.support.portal_support_team'));
                                                }
                                                $timestamp = (string) ($message['created_at'] ?? '');
                                            @endphp
                                            <div class="support-message {{ $isOwnMessage ? 'support-message--user' : 'support-message--support' }}">
                                                <div class="support-message__meta">
                                                    <span class="support-message__author">{{ $authorLabel }}</span>
                                                    @if ($timestamp !== '')
                                                        <span>&middot;</span>
                                                        <span>{{ $timestamp }}</span>
                                                    @endif
                                                </div>
                                                <div class="support-message__body">{{ $message['body'] ?? '' }}</div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($canOpenTicket || $isStaff)
                                        <form class="support-chat__form" method="POST" action="{{ route('support.ticket.message', $activeTicket) }}">
                                            @csrf
                                            <textarea class="input support-chat__textarea" name="message" rows="4" placeholder="{{ $isStaff ? __('ui.support.portal_chat_placeholder_staff') : __('ui.support.portal_chat_placeholder') }}" maxlength="4000" required></textarea>
                                            <div class="support-chat__actions">
                                                <button type="submit" class="primary-cta">{{ $isStaff ? __('ui.support.portal_chat_send_staff') : __('ui.support.portal_chat_send') }}</button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="support-empty card">
                                            <div class="support-empty__title">{{ __('ui.support.portal_chat_locked') }}</div>
                                        </div>
                                    @endif
                                @else
                                    <div class="support-empty card">
                                        <div class="support-empty__title">{{ __('ui.support.portal_chat_empty') }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </section>

        <section class="support-tab-panel {{ $activeTab === 'new' ? 'is-active' : '' }}" data-tab-panel="new">
            <div class="support-section">
                <div class="support-section__header">
                    <h2>{{ __('ui.support.portal_nav_new') }}</h2>
                </div>

                @if (!$ticketsReady)
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ __('ui.support.portal_new_unavailable') }}</div>
                    </div>
                @elseif ($isBanned)
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ __('ui.support.portal_new_banned') }}</div>
                    </div>
                @elseif (!$canOpenTicket)
                    <div class="support-empty card">
                        <div class="support-empty__title">{{ __('ui.support.portal_new_login') }}</div>
                        <a class="ghost-btn" href="{{ route('login') }}">{{ __('ui.topbar.login') }}</a>
                    </div>
                @else
                    @include('support.partials.ticket-form')
                @endif
            </div>
        </section>
    </div>
@endsection
