@props([
    'label',
    'tip' => null,
])

<span class="etd-kpi-label-wrap">
    <span class="etd-kpi-label-text">{{ $label }}</span>
    @if ($tip)
        <button type="button" class="etd-tip-trigger etd-tip-trigger--kpi" aria-label="{{ $tip }}">
            <svg class="etd-tip-icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                <circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.25"/>
                <path fill="currentColor" d="M7.25 7h1.5V6.1c0-.69.56-1.25 1.25-1.25.69 0 1.25.56 1.25 1.25v.65c0 .69-.56 1.25-1.25 1.25H8.5v3.35H7.25V7z"/>
                <circle cx="8" cy="4.35" r=".85" fill="currentColor"/>
            </svg>
            <span class="etd-tip-content etd-tip-content--kpi" role="tooltip">{{ $tip }}</span>
        </button>
    @endif
</span>
