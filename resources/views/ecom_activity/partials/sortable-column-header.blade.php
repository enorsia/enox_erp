<a href="{{ $header['url'] }}"
   data-etd-activity-sort
   class="{{ $header['linkClass'] }}"
   @if ($header['active']) aria-sort="{{ $header['dir'] === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if (! empty($tip))
        @include('ecom_tracker.partials.column-header-with-tip', [
            'label' => $label,
            'tip' => $tip,
            'align' => $align ?? null,
        ])
    @else
        <span class="etd-sort-th-label">{{ $label }}</span>
    @endif
    @if ($header['active'])
        <span class="etd-sort-arrow" aria-hidden="true">{{ $header['dir'] === 'asc' ? '↑' : '↓' }}</span>
    @endif
</a>
