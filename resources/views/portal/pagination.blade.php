{{--
    The portal ships its own stylesheet instead of Tailwind, so Laravel's
    default paginator (which relies on Tailwind utilities) renders both its
    mobile and desktop halves at once. This is the same markup in portal CSS.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        <div class="pager-summary">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            of {{ $paginator->total() }}
        </div>

        <div class="pager-links">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-disabled="true" aria-label="Previous">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">&lsaquo;</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="is-gap" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">&rsaquo;</a>
            @else
                <span class="is-disabled" aria-disabled="true" aria-label="Next">&rsaquo;</span>
            @endif
        </div>
    </nav>
@endif
