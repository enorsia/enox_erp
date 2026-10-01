@php
    $row = $row ?? [];
    $deltaPct = $row['delta_pct'] ?? null;
    $deltaDirection = $row['delta_direction'] ?? null;
    $sentiment = $row['delta_sentiment'] ?? 'neutral';
    $periodAHref = $period_a_href ?? null;
    $periodBHref = $period_b_href ?? null;
@endphp

<tr class="etd-compare-metric-row">
    <td class="etd-compare-metric-row__label">
        <span class="etd-compare-metric-row__label-text">{{ $row['label'] ?? '' }}</span>
        @if (! empty($row['tip']))
            <button type="button" class="etd-tip-trigger etd-tip-trigger--kpi" aria-label="Definition" title="">
                <svg class="etd-tip-icon" viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
                    <circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.25"/>
                    <path fill="currentColor" d="M7.25 7h1.5V6.1c0-.69.56-1.25 1.25-1.25.69 0 1.25.56 1.25 1.25v.65c0 .69-.56 1.25-1.25 1.25H8.5v3.35H7.25V7z"/>
                    <circle cx="8" cy="4.35" r=".85" fill="currentColor"/>
                </svg>
                <span class="etd-tip-content etd-tip-content--kpi" role="tooltip">{{ $row['tip'] }}</span>
            </button>
        @endif
    </td>
    <td class="etd-num etd-compare-metric-row__value">
        @if (filled($periodAHref))
            <a href="{{ $periodAHref }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">{{ $row['period_a_formatted'] ?? '0' }}</a>
        @else
            {{ $row['period_a_formatted'] ?? '0' }}
        @endif
    </td>
    <td class="etd-num etd-compare-metric-row__value">
        @if (filled($periodBHref))
            <a href="{{ $periodBHref }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">{{ $row['period_b_formatted'] ?? '0' }}</a>
        @else
            {{ $row['period_b_formatted'] ?? '0' }}
        @endif
    </td>
    <td class="etd-num etd-compare-metric-row__delta">
        @if ($deltaPct !== null)
            <span @class([
                'etd-compare-delta-badge',
                'etd-compare-delta--good' => $sentiment === 'good',
                'etd-compare-delta--bad' => $sentiment === 'bad',
                'etd-compare-delta--neutral' => $sentiment === 'neutral',
            ])>
                @if ($deltaDirection === 'up') ↑ @elseif ($deltaDirection === 'down') ↓ @else → @endif
                {{ ($deltaPct > 0 ? '+' : '') . number_format($deltaPct, 1) }}%
            </span>
        @elseif ($deltaDirection === 'up')
            <span class="etd-compare-delta-badge etd-compare-delta--good">New</span>
        @else
            <span class="etd-compare-delta-badge etd-compare-delta--neutral">—</span>
        @endif
    </td>
</tr>
