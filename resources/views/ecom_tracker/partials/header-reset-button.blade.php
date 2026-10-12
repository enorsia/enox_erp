@props([
    'url',
    'active' => false,
    'count' => 0,
])

@php
    $filterCount = (int) $count;
    $isFiltered = $active || $filterCount > 0;
@endphp

<a href="{{ $url }}"
   class="etd-header-btn etd-header-btn--icon-only no-underline {{ $isFiltered ? 'etd-header-btn--filtered' : '' }} {{ $filterCount > 0 ? 'etd-header-btn--has-badge' : '' }}"
   aria-label="{{ $filterCount > 0 ? 'Reset '.$filterCount.' filters' : 'Reset filters' }}"
   title="{{ $filterCount > 0 ? 'Reset '.$filterCount.' filters' : 'Reset all filters' }}">
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    @if ($filterCount > 0)
        <span class="etd-header-btn-badge etd-header-btn-badge--corner">{{ $filterCount }}</span>
    @endif
</a>
