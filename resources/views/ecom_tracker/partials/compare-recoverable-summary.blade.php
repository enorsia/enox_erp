@props([
    'panels' => [],
    'showInlineCompareDelta' => false,
    'compareMetricDeltas' => [],
])

<div @class(['etd-compare-recoverable-grid', 'etd-compare-recoverable-grid--compare-inline' => $showInlineCompareDelta]) data-compare-sync="recoverable">
    @foreach ($panels as $panel)
        @php
            $dataKey = $panel['dataKey'] ?? '';
            $data = $panel['data'] ?? [];
            $sessionCount = (int) ($data['session_count'] ?? 0);
            $atStake = (float) ($data['at_stake'] ?? 0);
            $panelHref = $panel['href'] ?? null;
            $panelDeltas = $compareMetricDeltas[$dataKey] ?? [];
        @endphp
        @if (filled($panelHref))
            <a href="{{ $panelHref }}" class="etd-kpi-drilldown-link no-underline text-inherit">
        @endif
        <div @class([
            'etd-compare-recoverable-card',
            'etd-compare-recoverable-card--cart' => ($panel['tone'] ?? '') === 'cart',
            'etd-compare-recoverable-card--begin' => ($panel['tone'] ?? '') === 'begin',
            'etd-compare-recoverable-card--proceed' => ($panel['tone'] ?? '') === 'proceed',
            'etd-compare-recoverable-card--success' => ($panel['tone'] ?? '') === 'success',
        ])>
            <p class="etd-compare-recoverable-card__title">{{ $panel['title'] ?? '' }}</p>
            <p class="etd-compare-recoverable-card__value">
                @include('ecom_tracker.partials.category-performance-metric-cell', [
                    'formatted' => number_format($sessionCount).' '.Str::plural('session', $sessionCount),
                    'showInlineCompareDelta' => $showInlineCompareDelta,
                    'delta' => $panelDeltas['session_count'] ?? null,
                ])
            </p>
            <p class="etd-compare-recoverable-card__stake">
                @include('ecom_tracker.partials.category-performance-metric-cell', [
                    'formatted' => '£'.number_format($atStake, 2).' at stake',
                    'showInlineCompareDelta' => $showInlineCompareDelta,
                    'delta' => $panelDeltas['at_stake'] ?? null,
                ])
            </p>
        </div>
        @if (filled($panelHref))
            </a>
        @endif
    @endforeach
</div>
