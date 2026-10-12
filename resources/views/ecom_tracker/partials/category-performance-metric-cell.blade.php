@php
    $delta = $delta ?? null;
    $showInlineCompareDelta = $showInlineCompareDelta ?? false;
    $deltaPct = $delta['delta_pct'] ?? null;
    $deltaDirection = $delta['delta_direction'] ?? null;
    $sentiment = $delta['delta_sentiment'] ?? 'neutral';
    $hasDelta = $showInlineCompareDelta && ($deltaPct !== null || $deltaDirection === 'up');
@endphp

<span @class(['etd-category-metric-cell', 'etd-category-metric-cell--stacked' => $hasDelta])>
    <span class="etd-category-metric-cell__value">{{ $formatted }}</span>
    @if ($hasDelta)
        <span class="etd-category-metric-cell__delta">
            @if ($deltaPct !== null)
                <span @class([
                    'etd-category-metric-cell__pct',
                    'etd-compare-delta--good' => $sentiment === 'good',
                    'etd-compare-delta--bad' => $sentiment === 'bad',
                    'etd-compare-delta--neutral' => $sentiment === 'neutral',
                ])>
                    {{ ($deltaPct > 0 ? '+' : '').number_format($deltaPct, 1) }}%
                </span>
            @else
                <span class="etd-category-metric-cell__pct etd-compare-delta--good">New</span>
            @endif
        </span>
    @endif
</span>
