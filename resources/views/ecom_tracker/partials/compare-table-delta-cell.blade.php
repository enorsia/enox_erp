@php
    $delta = $delta ?? null;
    $deltaPct = $delta['delta_pct'] ?? null;
    $deltaDirection = $delta['delta_direction'] ?? null;
    $sentiment = $delta['delta_sentiment'] ?? 'neutral';
@endphp

<td class="etd-num etd-compare-table-delta">
    @if ($deltaPct !== null)
        <span @class([
            'etd-compare-delta-badge etd-compare-delta-badge--compact',
            'etd-compare-delta--good' => $sentiment === 'good',
            'etd-compare-delta--bad' => $sentiment === 'bad',
            'etd-compare-delta--neutral' => $sentiment === 'neutral',
        ])>
            @if ($deltaDirection === 'up') ↑ @elseif ($deltaDirection === 'down') ↓ @else → @endif
            {{ ($deltaPct > 0 ? '+' : '').number_format($deltaPct, 1) }}%
        </span>
    @elseif ($deltaDirection === 'up')
        <span class="etd-compare-delta-badge etd-compare-delta-badge--compact etd-compare-delta--good">New</span>
    @else
        <span class="etd-compare-delta-badge etd-compare-delta-badge--compact etd-compare-delta--neutral">—</span>
    @endif
</td>
