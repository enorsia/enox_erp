@props([
    'detailUrl',
    'viewLabel' => 'View',
])

<a href="{{ $detailUrl }}"
   class="etd-view-details-btn no-underline"
   aria-label="{{ $viewLabel }} details">
    {{ $viewLabel }}
    <svg class="etd-view-details-btn__icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
</a>
