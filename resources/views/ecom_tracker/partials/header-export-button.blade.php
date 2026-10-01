@props([
    'onclick' => 'window.openActivityExportModal && window.openActivityExportModal()',
    'label' => 'Export',
])

<button type="button"
        onclick="{{ $onclick }}"
        class="etd-header-btn etd-header-btn--icon-only"
        aria-label="{{ $label }}"
        title="{{ $label }}">
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3m0 0L7.5 7.5M12 3l4.5 4.5M4.5 19.5h15"/>
    </svg>
</button>
