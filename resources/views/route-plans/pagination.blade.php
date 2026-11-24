@if ($paginator->hasPages())
    <nav class="dromos-pagination" aria-label="Σελιδοποίηση διαδρομών">
        <p>
            Εμφάνιση {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            από {{ $paginator->total() }} διαδρομές
        </p>

        <div class="dromos-pagination-links">
            @if ($paginator->onFirstPage())
                <span class="pagination-direction is-disabled" aria-disabled="true">‹ Προηγούμενη</span>
            @else
                <a class="pagination-direction" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Προηγούμενη</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="pagination-page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-page" href="{{ $url }}" aria-label="Σελίδα {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-direction" href="{{ $paginator->nextPageUrl() }}" rel="next">Επόμενη ›</a>
            @else
                <span class="pagination-direction is-disabled" aria-disabled="true">Επόμενη ›</span>
            @endif
        </div>
    </nav>
@endif
