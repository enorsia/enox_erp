@props([
    'url',
    'active' => false,
])

<a href="{{ $url }}"
   class="etd-header-btn etd-header-btn--icon no-underline {{ $active ? 'etd-header-btn--filtered' : '' }}"
   aria-label="Reset filters"
   title="Reset all filters">
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    <span class="etd-header-btn-text">Reset</span>
</a>
