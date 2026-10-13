@props([
    'metrics' => [],
    'sessionKey' => '',
])

@php
    $presentation = $metrics['presentation'] ?? [];
    $badge = $presentation['badge'] ?? '—';
    $tone = $presentation['tone'] ?? 'muted';
    $headline = $presentation['headline'] ?? '';
    $lines = $presentation['lines'] ?? [];
    $detailLabel = $presentation['detail_label'] ?? null;
    $showExpand = (bool) ($presentation['show_expand'] ?? false);
    $expandableEvents = $metrics['expandable_commerce_events'] ?? [];
    $hasOrder = ($tone === 'sold');
@endphp

<div class="etd-product-commerce-cell">
    <div class="etd-product-event">
        <span @class(['etd-product-event__badge', 'etd-product-event__badge--'.$tone])>{{ $badge }}</span>
        @if ($headline !== '')
            <p class="etd-product-event__headline">{{ $headline }}</p>
        @endif
        @foreach ($lines as $line)
            @if (filled($line['text'] ?? null))
                <p class="etd-product-event__line">{{ $line['text'] }}</p>
            @endif
        @endforeach
    </div>

    @if ($showExpand && $expandableEvents !== [])
        @include('ecom_activity.partials.commerce-events', [
            'events' => $expandableEvents,
            'sessionKey' => $sessionKey,
            'hasOrder' => $hasOrder,
            'productDrill' => true,
            'detailLabel' => $detailLabel,
        ])
    @endif
</div>
