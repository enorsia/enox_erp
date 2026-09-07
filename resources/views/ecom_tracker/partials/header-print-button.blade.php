@props([
    'id' => 'etdDashboardPrintBtn',
    'label' => 'Print',
])

<button
    type="button"
    id="{{ $id }}"
    class="etd-header-btn etd-header-btn--icon etd-print-hide"
    aria-label="Print dashboard"
    title="Print dashboard"
>
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v7H6v-7z"/>
    </svg>
    <span class="etd-header-btn-text">{{ $label }}</span>
</button>
