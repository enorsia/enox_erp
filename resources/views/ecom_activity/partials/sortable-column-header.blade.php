@php
    use App\Support\EcomActivitySessionSort;

    $sortKey = $sortKey ?? '';
    $label = $label ?? '';
    $tip = $tip ?? null;
    $align = $align ?? null;
    $class = $class ?? null;
    $active = EcomActivitySessionSort::isActive(request(), $sortKey);
    $dir = EcomActivitySessionSort::activeDirection(request(), $sortKey);
    $url = EcomActivitySessionSort::sortUrl(request(), $sortKey);
    $linkClass = 'etd-sort-th-link'.($active ? ' is-active' : '');
    if ($align === 'center') {
        $linkClass .= ' etd-sort-th-link--center';
    }
@endphp

<a href="{{ $url }}"
   data-etd-activity-sort
   class="{{ $linkClass }}"
   @if ($active) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if ($tip)
        @include('ecom_tracker.partials.column-header-with-tip', [
            'label' => $label,
            'tip' => $tip,
            'align' => $align,
        ])
    @else
        <span class="etd-sort-th-label">{{ $label }}</span>
    @endif
    @if ($active)
        <span class="etd-sort-arrow" aria-hidden="true">{{ $dir === 'asc' ? '↑' : '↓' }}</span>
    @endif
</a>
