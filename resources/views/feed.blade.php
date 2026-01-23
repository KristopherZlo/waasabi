@extends('layouts.app')

@section('title', __('ui.feed.title'))
@section('page', 'feed')

@section('content')
    @php
        $showQaBlock = true;
        $qaInterval = 50;
        $projectCount = 0;
        $feedItems = $feed_items ?? [];
        $hasQaThreads = !empty($qa_threads ?? []);
    @endphp
    <section class="feed-header">
        <div class="feed-title">{{ __('ui.feed.all_streams') }}</div>
        <div class="tabs feed-tabs">
            <button type="button" class="tab feed-tab is-active" data-feed-tab="projects">{{ __('ui.feed.tab_projects') }}</button>
            <button type="button" class="tab feed-tab" data-feed-tab="questions">{{ __('ui.feed.tab_questions') }}</button>
            <button type="button" class="tab feed-tab" data-feed-tab="collaboration">{{ __('ui.feed.tab_collaboration') }}</button>
        </div>
        <div class="feed-tags">
            @foreach ($feed_tags as $tag)
                @php
                    $tagLabel = is_array($tag) ? ($tag['label'] ?? '') : (string) $tag;
                    $tagSlug = is_array($tag) ? ($tag['slug'] ?? \Illuminate\Support\Str::slug($tagLabel)) : \Illuminate\Support\Str::slug($tagLabel);
                    $tagCount = is_array($tag) ? ($tag['count'] ?? null) : null;
                @endphp
                @if ($tagLabel !== '')
                    <button type="button" class="feed-tag" data-feed-tag="{{ $tagSlug }}">
                        <span>{{ $tagLabel }}</span>
                        @if ($tagCount !== null)
                            <span class="feed-tag__count">{{ $tagCount }}</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>
        <div class="feed-filters">
            <button type="button" class="feed-filter is-active" data-feed-filter="all">{{ __('ui.feed.filter_all') }}</button>
            <button type="button" class="feed-filter" data-feed-filter="best">{{ __('ui.feed.filter_best') }}</button>
            <button type="button" class="feed-filter" data-feed-filter="fresh">{{ __('ui.feed.filter_fresh') }}</button>
            <button type="button" class="feed-filter" data-feed-filter="reading">{{ __('ui.feed.filter_reading') }}</button>
        </div>
    </section>

    <section class="section" style="margin-top: 16px;">
        <div
            class="list"
            data-feed-list
            data-feed-endpoint="{{ route('feed.chunk') }}"
            data-feed-page-size="{{ $feed_page_size ?? 10 }}"
            data-feed-offset-projects="{{ $feed_projects_offset ?? 0 }}"
            data-feed-total-projects="{{ $feed_projects_total ?? 0 }}"
            data-feed-offset-questions="{{ $feed_questions_offset ?? 0 }}"
            data-feed-total-questions="{{ $feed_questions_total ?? 0 }}"
            data-qa-interval="{{ $qaInterval }}"
        >
            @foreach ($feedItems as $item)
                @include('partials.feed-item', ['item' => $item])
                @if (($item['type'] ?? '') === 'project')
                    @php
                        $projectCount += 1;
                    @endphp
                @endif
                @if ($showQaBlock && $hasQaThreads && $qaInterval > 0 && $projectCount > 0 && $projectCount % $qaInterval === 0)
                    @include('partials.qa-block', ['threads' => $qa_threads, 'qa_anchor' => $projectCount])
                @endif
            @endforeach
            <div class="list-item" data-feed-empty hidden>{{ __('ui.feed.empty') }}</div>
            <div class="feed-loader skeleton" data-feed-loader hidden></div>
            <div class="feed-sentinel" data-feed-sentinel aria-hidden="true"></div>
        </div>
        @if ($showQaBlock && $hasQaThreads)
            <template data-qa-template>
                @include('partials.qa-block', ['threads' => $qa_threads])
            </template>
        @endif
    </section>
@endsection

@section('sidebar')
    @include('partials.sidebar-feed')
@endsection
