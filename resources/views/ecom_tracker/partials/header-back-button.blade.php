@props([
    'url',
    'label' => 'Back',
])

<a href="{{ $url }}"
   class="etd-header-btn etd-header-btn--icon no-underline"
   aria-label="{{ $label }}"
   title="{{ $label }}">
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    <span class="etd-header-btn-text">{{ $label }}</span>
</a>
