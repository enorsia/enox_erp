@props([
    'sortKey',
    'sortBy' => '',
    'sortDir' => 'desc',
    'sortUrl' => null,
    'label',
])

@if (is_callable($sortUrl))
    @php
        $isActiveSort = $sortBy === $sortKey;
    @endphp
    <a href="{{ $sortUrl($sortKey) }}"
       class="etd-col-sort-toggle no-underline"
       aria-label="Sort by {{ $label }}"
       @if ($isActiveSort) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
        <svg @class([
            'etd-col-sort-toggle__chev etd-col-sort-toggle__chev--up',
            'is-active' => $isActiveSort && $sortDir === 'asc',
        ]) viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 2.5 10 8H2L6 2.5z"/></svg>
        <svg @class([
            'etd-col-sort-toggle__chev etd-col-sort-toggle__chev--down',
            'is-active' => $isActiveSort && $sortDir === 'desc',
        ]) viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><path d="M6 9.5 2 4h8L6 9.5z"/></svg>
    </a>
@endif
