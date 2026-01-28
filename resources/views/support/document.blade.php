@extends('layouts.support')

@section('title', $document_title ?? __('ui.support.title_full'))
@section('page', $document_page ?? 'support-document')

@section('content')
    @php
        $title = (string) ($document_title ?? '');
        $summary = (string) ($document_summary ?? '');
        $backUrl = (string) ($document_back_url ?? route('support', ['tab' => 'home']));
        $backLabel = (string) ($document_back_label ?? __('ui.support.ticket_back'));
        $documentSection = (string) ($document_section ?? '');
        $documentSlug = (string) ($document_slug ?? '');
        $documentNavSections = collect($document_nav_sections ?? []);
    @endphp

    <div class="support-page support-page--doc" data-support-root>
        <header class="support-banner support-banner--doc">
            <div class="support-banner__inner">
                <div class="support-banner__content">
                    <div class="support-breadcrumbs">
                        <a class="support-breadcrumbs__back" href="{{ $backUrl }}" aria-label="{{ $backLabel }}" title="{{ $backLabel }}">
                            <i data-lucide="arrow-left" class="icon"></i>
                        </a>
                        <a class="support-breadcrumbs__link" href="{{ route('support') }}">{{ __('ui.support.title') }}</a>
                        @if ($documentSection !== '')
                            <span class="support-breadcrumbs__sep">/</span>
                            <span>{{ $documentSection }}</span>
                        @endif
                        @if ($title !== '')
                            <span class="support-breadcrumbs__sep">/</span>
                            <span>{{ $title }}</span>
                        @endif
                    </div>
                    @if ($title !== '')
                        <h1>{{ $title }}</h1>
                    @endif
                    @if ($summary !== '')
                        <p class="support-banner__summary">{{ $summary }}</p>
                    @endif
                </div>
                <label class="support-banner__search" role="search">
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
                </label>
            </div>
        </header>

        <div class="support-doc">
            @if ($documentNavSections->isNotEmpty())
                <aside class="support-doc__nav">
                    @foreach ($documentNavSections as $section)
                        <div class="support-doc__section" data-support-group>
                            <div class="support-doc__section-title">{{ $section['title'] ?? '' }}</div>
                            @if (!empty($section['summary']))
                                <div class="support-doc__section-text">{{ $section['summary'] }}</div>
                            @endif
                            <div class="support-doc__links">
                                @foreach (($section['items'] ?? []) as $article)
                                    @php
                                        $articleSlug = (string) ($article['slug'] ?? '');
                                        $isActive = $documentSlug !== '' && $articleSlug === $documentSlug;
                                    @endphp
                                    <a
                                        class="support-doc__link {{ $isActive ? 'is-active' : '' }}"
                                        href="{{ $article['url'] ?? '#' }}"
                                        data-support-item
                                        data-support-search="{{ $article['search'] ?? '' }}"
                                    >
                                        <span>{{ $article['title'] ?? '' }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div class="support-empty support-empty--inline" data-support-empty hidden>
                        <div class="support-empty__title">{{ __('ui.support.portal_kb_empty') }}</div>
                    </div>
                </aside>
            @endif

            <div class="support-doc__content support-document">
                @include('support._markdown', ['path' => $markdown_path ?? null])
            </div>
        </div>
    </div>
@endsection
