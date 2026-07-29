@props([
    'formatted',
    'comparison' => null,
    'valueClass' => '',
])

<div class="etd-kpi-value-stack">
    <div class="etd-kpi-value {{ $valueClass }}">{{ $formatted }}</div>

    @if (! empty($comparison))
        @php
            $deltaPct = $comparison['delta_pct'] ?? null;
            $deltaDirection = $comparison['delta_direction'] ?? null;
            $deltaLabel = $comparison['delta_label'] ?? null;
            $comparisonLabel = $comparison['comparison_label'] ?? 'previous period';
            $previousFormatted = $comparison['previous_formatted'] ?? '0';
            $hasDelta = $deltaPct !== null && is_numeric($deltaPct);
            $hasBaseline = $previousFormatted !== ''
                && ! in_array($deltaLabel, ['no_prior_data', 'new'], true)
                && ($comparison['previous'] ?? null) != 0;
        @endphp
        <div class="etd-kpi-compare">
            <div class="etd-kpi-compare__row">
                @if ($hasDelta)
                    <span @class([
                        'etd-kpi-compare__change',
                        'etd-kpi-compare__change--up' => $deltaDirection === 'up',
                        'etd-kpi-compare__change--down' => $deltaDirection === 'down',
                        'etd-kpi-compare__change--flat' => $deltaDirection !== 'up' && $deltaDirection !== 'down',
                    ])>
                        <span class="etd-kpi-compare__arrow" aria-hidden="true">
                            @if ($deltaDirection === 'up') ↑ @elseif ($deltaDirection === 'down') ↓ @else → @endif
                        </span>
                        <span class="etd-kpi-compare__pct">{{ ($deltaPct > 0 ? '+' : '') . number_format($deltaPct, 1) }}%</span>
                    </span>
                @elseif ($deltaLabel === 'new')
                    <span class="etd-kpi-compare__change etd-kpi-compare__change--new">New</span>
                @else
                    <span class="etd-kpi-compare__change etd-kpi-compare__change--muted">No prior data</span>
                @endif

                @if ($hasBaseline && ($hasDelta || $deltaLabel === 'new'))
                    <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                    <span class="etd-kpi-compare__context">
                        <span class="etd-kpi-compare__prev">{{ $previousFormatted }}</span>
                        <span class="etd-kpi-compare__period">{{ ucfirst($comparisonLabel) }}</span>
                    </span>
                @elseif ($hasBaseline)
                    <span class="etd-kpi-compare__context etd-kpi-compare__context--solo">
                        <span class="etd-kpi-compare__prev">{{ $previousFormatted }}</span>
                        <span class="etd-kpi-compare__period">{{ ucfirst($comparisonLabel) }}</span>
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>
