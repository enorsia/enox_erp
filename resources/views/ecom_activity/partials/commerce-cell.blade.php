@props(['metrics' => []])

@php
    $display = $metrics['commerce_display'] ?? '—';
    $meta = $metrics['commerce_meta'] ?? null;
    $tip = $metrics['commerce_tip'] ?? null;
    $hasOrder = (bool) ($metrics['commerce_has_order'] ?? false);
@endphp

<div
    class="etd-commerce-cell"
    @if (filled($tip)) title="{{ $tip }}" @endif
>
    @if ($display === '—')
        <span class="etd-commerce-cell__empty">—</span>
    @else
        <span @class(['etd-commerce-cell__line', 'etd-commerce-cell__line--order' => $hasOrder])>{{ $display }}</span>
        @if (filled($meta))
            <span class="etd-commerce-cell__meta">{{ $meta }}</span>
        @endif
    @endif
</div>
