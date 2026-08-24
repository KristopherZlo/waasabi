@extends('layouts.app')

@section('title', ($active_stream ?? 'projects') === 'collaboration' ? __('ui.collaboration.title') : __('ui.feed.title'))
@section('description', ($active_stream ?? 'projects') === 'collaboration' ? __('ui.collaboration.subtitle') : __('ui.app.description'))
@section('page', 'feed')

@section('content')
    @php
        $showQaBlock = true;
        $qaInterval = 50;
        $projectCount = 0;
        $feedItems = $feed_items ?? [];
        $hasQaThreads = !empty($qa_threads ?? []);
        $activeStream = $active_stream ?? 'projects';
        $isCollaborationStream = $activeStream === 'collaboration';
    @endphp
    <section class="feed-header">
        <div class="feed-title">{{ __('ui.feed.all_streams') }}</div>
        <div class="tabs feed-tabs" role="group" aria-label="{{ __('ui.feed.streams_label') }}">
            @if ($isCollaborationStream)
                <a class="tab feed-tab" href="{{ route('feed', ['stream' => 'projects']) }}">{{ __('ui.feed.tab_projects') }}</a>
                <a class="tab feed-tab" href="{{ route('feed', ['stream' => 'questions']) }}">{{ __('ui.feed.tab_questions') }}</a>
                <a class="tab feed-tab is-active" href="{{ route('feed', ['stream' => 'collaboration']) }}" aria-current="page">{{ __('ui.feed.tab_collaboration') }}</a>
            @else
                <button type="button" class="tab feed-tab {{ $activeStream === 'projects' ? 'is-active' : '' }}" data-feed-tab="projects" aria-pressed="{{ $activeStream === 'projects' ? 'true' : 'false' }}">{{ __('ui.feed.tab_projects') }}</button>
                <button type="button" class="tab feed-tab {{ $activeStream === 'questions' ? 'is-active' : '' }}" data-feed-tab="questions" aria-pressed="{{ $activeStream === 'questions' ? 'true' : 'false' }}">{{ __('ui.feed.tab_questions') }}</button>
                <a class="tab feed-tab" href="{{ route('feed', ['stream' => 'collaboration']) }}">{{ __('ui.feed.tab_collaboration') }}</a>
            @endif
        </div>
        @unless ($isCollaborationStream)
            <div class="feed-tags" role="group" aria-label="{{ __('ui.feed.tags_label') }}">
                @foreach ($feed_tags as $tag)
                    @php
                        $tagLabel = is_array($tag) ? ($tag['label'] ?? '') : (string) $tag;
                        $tagSlug = is_array($tag) ? ($tag['slug'] ?? \Illuminate\Support\Str::slug($tagLabel)) : \Illuminate\Support\Str::slug($tagLabel);
                        $tagCount = is_array($tag) ? ($tag['count'] ?? null) : null;
                    @endphp
                    @if ($tagLabel !== '')
                        <button type="button" class="feed-tag" data-feed-tag="{{ $tagSlug }}" aria-pressed="false">
                            <span>{{ $tagLabel }}</span>
                            @if ($tagCount !== null)
                                <span class="feed-tag__count">{{ $tagCount }}</span>
                            @endif
                        </button>
                    @endif
                @endforeach
            </div>
            <div class="feed-filter-bar">
                <div class="feed-filters" role="group" aria-label="{{ __('ui.feed.sort_label') }}">
                    <button type="button" class="feed-filter is-active" data-feed-filter="all" aria-pressed="true">
                        {{ __('ui.feed.filter_all') }}
                    </button>
                    <button type="button" class="feed-filter" data-feed-filter="best" aria-pressed="false">
                        {{ __('ui.feed.filter_best') }}
                    </button>
                    <button type="button" class="feed-filter" data-feed-filter="fresh" aria-pressed="false">
                        {{ __('ui.feed.filter_fresh') }}
                    </button>
                    <button type="button" class="feed-filter" data-feed-filter="reading" aria-pressed="false">
                        {{ __('ui.feed.filter_reading') }}
                    </button>
                </div>
                <button type="button" class="feed-filter-reset" data-feed-reset hidden>{{ __('ui.feed.filter_reset') }}</button>
            </div>
            <div class="feed-filter-status sr-only" data-feed-status data-result-label="{{ __('ui.feed.results_count') }}" role="status" aria-live="polite"></div>
        @endunless
    </section>

    @if ($isCollaborationStream)
        @include('partials.collaboration-feed')
    @else
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
    @endif
@endsection

@section('sidebar')
    @include('partials.sidebar-feed')
@endsection
