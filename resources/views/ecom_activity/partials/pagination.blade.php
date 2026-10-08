@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = collect([1, $last])
        ->merge(range(max(1, $current - 1), min($last, $current + 1)))
        ->unique()
        ->sort()
        ->values();
    $pages = [];
    foreach ($window as $i => $pageNumber) {
        if ($i > 0 && $pageNumber - $window[$i - 1] > 1) {
            $pages[] = $pageNumber - $window[$i - 1] === 2 ? $pageNumber - 1 : null;
        }
        $pages[] = $pageNumber;
    }
    $chevronLeft = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>';
    $chevronRight = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
    $chevronFirst = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"/></svg>';
    $chevronLast = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>';
@endphp

<div class="etd-pager">
    <p class="etd-pager__summary">
        <span>{{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}</span>
        of <span>{{ number_format($paginator->total()) }}</span> sessions
    </p>

    @if ($paginator->hasPages())
        <nav class="etd-pager__nav" aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="etd-pager__btn etd-pager__btn--icon is-disabled" aria-hidden="true">{!! $chevronFirst !!}</span>
                <span class="etd-pager__btn etd-pager__btn--icon is-disabled" aria-hidden="true">{!! $chevronLeft !!}</span>
            @else
                <a href="{{ $paginator->url(1) }}" class="etd-pager__btn etd-pager__btn--icon" aria-label="First page" title="First page">{!! $chevronFirst !!}</a>
                <a href="{{ $paginator->previousPageUrl() }}" class="etd-pager__btn etd-pager__btn--icon" rel="prev" aria-label="Previous page" title="Previous page">{!! $chevronLeft !!}</a>
            @endif

            @foreach ($pages as $pageNumber)
                @if ($pageNumber === null)
                    <span class="etd-pager__gap" aria-hidden="true">…</span>
                @elseif ($pageNumber === $current)
                    <span class="etd-pager__btn is-active" aria-current="page">{{ number_format($pageNumber) }}</span>
                @else
                    <a href="{{ $paginator->url($pageNumber) }}" class="etd-pager__btn" aria-label="Page {{ $pageNumber }}">{{ number_format($pageNumber) }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="etd-pager__btn etd-pager__btn--icon" rel="next" aria-label="Next page" title="Next page">{!! $chevronRight !!}</a>
                <a href="{{ $paginator->url($last) }}" class="etd-pager__btn etd-pager__btn--icon" aria-label="Last page" title="Last page">{!! $chevronLast !!}</a>
            @else
                <span class="etd-pager__btn etd-pager__btn--icon is-disabled" aria-hidden="true">{!! $chevronRight !!}</span>
                <span class="etd-pager__btn etd-pager__btn--icon is-disabled" aria-hidden="true">{!! $chevronLast !!}</span>
            @endif
        </nav>

        @if ($last > 7)
            <form method="get" action="{{ url()->current() }}" class="etd-pager__jump">
                @foreach (request()->except('page') as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="etd-pager-jump">Page</label>
                <input id="etd-pager-jump" type="number" name="page" min="1" max="{{ $last }}" value="{{ $current }}" class="etd-pager__input" inputmode="numeric">
                <span>of {{ number_format($last) }}</span>
            </form>
        @endif
    @endif
</div>
