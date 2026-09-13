@props([
    'active' => false,
    'count' => 0,
    'label' => 'Filters',
])

<button type="button"
        @click="drawerOpen = true"
        class="etd-header-btn etd-header-btn--icon-only {{ $count > 0 ? 'etd-header-btn--has-badge' : '' }} {{ $active ? 'etd-header-btn--filtered' : '' }}"
        aria-label="{{ $label }}"
        :aria-expanded="drawerOpen.toString()">
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/>
    </svg>
    @if ($count > 0)
        <span class="etd-header-btn-badge etd-header-btn-badge--corner" aria-hidden="true">{{ $count }}</span>
    @endif
</button>
