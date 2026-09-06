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
            $showDivider = $hasBaseline && ($hasDelta || $deltaLabel === 'new');
        @endphp
        <div class="etd-kpi-compare">
            <div class="etd-kpi-compare__main">
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
                @elseif (! $hasBaseline)
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
                <span class="etd-kpi-compare__period">{{ ucfirst($comparisonLabel) }}</span>
            @endif
        </div>
    @endif
</div>
