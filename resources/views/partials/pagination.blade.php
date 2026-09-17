@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('ui.pagination.label') }}">
        <p class="pagination__summary">
            {{ __('ui.pagination.summary', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>

        <div class="pagination__links">
            @if ($paginator->onFirstPage())
                <span class="pagination__link" aria-disabled="true">{{ __('ui.pagination.previous') }}</span>
            @else
                <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    {{ __('ui.pagination.previous') }}
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination__link pagination__page" aria-current="page">{{ $page }}</span>
                        @else
                            <a
                                class="pagination__link pagination__page"
                                href="{{ $url }}"
                                aria-label="{{ __('ui.pagination.page', ['page' => $page]) }}"
                            >{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    {{ __('ui.pagination.next') }}
                </a>
            @else
                <span class="pagination__link" aria-disabled="true">{{ __('ui.pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
