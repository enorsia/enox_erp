@props([
    'metrics' => [],
    'events' => [],
    'sessionKey' => '',
])

@php
    $display = $metrics['commerce_display'] ?? '—';
    $meta = $metrics['commerce_meta'] ?? null;
    $tip = $metrics['commerce_tip'] ?? null;
    $hasOrder = (bool) ($metrics['commerce_has_order'] ?? false);
    $hasExpandableEvents = $events !== [];
@endphp

<div
    class="etd-commerce-cell"
    @if (filled($tip)) title="{{ $tip }}" @endif
>
    @if (! $hasExpandableEvents)
        @if ($display === '—')
            <span class="etd-commerce-cell__empty">—</span>
        @else
            <span @class(['etd-commerce-cell__line', 'etd-commerce-cell__line--order' => $hasOrder])>{{ $display }}</span>
        @endif
    @endif

    @if ($hasExpandableEvents)
        @include('ecom_activity.partials.commerce-events', [
            'events' => $events,
            'sessionKey' => $sessionKey,
            'hasOrder' => $hasOrder,
        ])
    @endif

    @if (filled($meta))
        <span class="etd-commerce-cell__meta">{{ $meta }}</span>
    @endif
</div>
