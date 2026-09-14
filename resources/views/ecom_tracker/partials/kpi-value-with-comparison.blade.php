@props([
    'formatted',
    'comparison' => null,
    'valueClass' => '',
])

@php
    use App\Support\TrackerTime;
@endphp

<div class="etd-kpi-value-stack">
    <div class="etd-kpi-value {{ $valueClass }}">{{ $formatted }}</div>

    @if (! empty($comparison))
        @php
            $deltaPct = $comparison['delta_pct'] ?? null;
            $deltaDirection = $comparison['delta_direction'] ?? null;
            $deltaLabel = $comparison['delta_label'] ?? null;
            $deltaSentiment = $comparison['delta_sentiment'] ?? null;
            $comparisonLabel = $comparison['comparison_label'] ?? 'previous period';
            $previousFormatted = $comparison['previous_formatted'] ?? '0';
            $hasDelta = $deltaPct !== null && is_numeric($deltaPct);
            $hasBaseline = $previousFormatted !== ''
                && ($comparison['delta_label'] ?? null) !== 'no_prior_data';
            $showDivider = $hasBaseline && ($hasDelta || ($deltaDirection === 'up' && $deltaPct === null));
        @endphp
        <div class="etd-kpi-compare">
            <div class="etd-kpi-compare__main">
                @if ($hasDelta)
                    <span @class([
                        'etd-kpi-compare__change',
                        'etd-kpi-compare__change--good' => $deltaSentiment === 'good',
                        'etd-kpi-compare__change--bad' => $deltaSentiment === 'bad',
                        'etd-kpi-compare__change--neutral' => $deltaSentiment === 'neutral',
                        'etd-kpi-compare__change--up' => $deltaSentiment === null && $deltaDirection === 'up',
                        'etd-kpi-compare__change--down' => $deltaSentiment === null && $deltaDirection === 'down',
                        'etd-kpi-compare__change--flat' => $deltaSentiment === null && $deltaDirection !== 'up' && $deltaDirection !== 'down',
                    ])>
                        <span class="etd-kpi-compare__arrow" aria-hidden="true">
                            @if ($deltaDirection === 'up') ↑ @elseif ($deltaDirection === 'down') ↓ @else → @endif
                        </span>
                        <span class="etd-kpi-compare__pct">{{ ($deltaPct > 0 ? '+' : '') . number_format($deltaPct, 1) }}%</span>
                    </span>
                @elseif ($deltaLabel === 'new')
                    <span @class([
                        'etd-kpi-compare__change',
                        'etd-kpi-compare__change--new' => $deltaSentiment === null,
                        'etd-kpi-compare__change--good' => $deltaSentiment === 'good',
                        'etd-kpi-compare__change--bad' => $deltaSentiment === 'bad',
                        'etd-kpi-compare__change--neutral' => $deltaSentiment === 'neutral',
                    ])>New</span>
                @elseif ($deltaDirection === 'up' && $deltaPct === null && $hasBaseline)
                    <span class="etd-kpi-compare__change etd-kpi-compare__change--up" aria-label="Up from zero in previous period">
                        <span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span>
                    </span>
                @elseif (($comparison['delta_label'] ?? null) === 'no_prior_data')
                    <span class="etd-kpi-compare__change etd-kpi-compare__change--muted">No prior data</span>
                @endif

                @if ($showDivider)
                    <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                @endif

                @if ($hasBaseline)
                    <span class="etd-kpi-compare__prev">{{ $previousFormatted }}</span>
                @endif
            </div>

            @if ($hasBaseline)
                @php $periodParts = TrackerTime::comparisonLabelParts($comparisonLabel); @endphp
                @if (($periodParts['type'] ?? '') === 'range')
                    <span class="etd-kpi-compare__period etd-date-range">
                        <span class="etd-date-range__from">{{ $periodParts['from'] }} –</span>
                        <span class="etd-date-range__to">{{ $periodParts['to'] }}</span>
                    </span>
                @else
                    <span class="etd-kpi-compare__period">{{ ucfirst($periodParts['label'] ?? $comparisonLabel) }}</span>
                @endif
            @endif
        </div>
    @endif
</div>
