@props([
    'panels' => [],
    'showInlineCompareDelta' => false,
    'compareMetricDeltas' => [],
])

<div @class(['etd-compare-recoverable-grid', 'etd-compare-recoverable-grid--compare-inline' => $showInlineCompareDelta]) data-compare-sync="recoverable">
    @foreach ($panels as $panel)
        @php
            $sessionCount = (int) ($panel['session_count'] ?? 0);
            $atStake = (float) ($panel['at_stake'] ?? 0);
            $panelHref = $panel['href'] ?? null;
            $panelDeltas = $compareMetricDeltas[$panel['dataKey'] ?? ''] ?? [];
            $tone = (string) ($panel['tone'] ?? 'cart');
        @endphp
        <div @class([
            'etd-compare-recoverable-card',
            'etd-compare-recoverable-card--cart' => $tone === 'cart',
            'etd-compare-recoverable-card--begin' => $tone === 'begin',
            'etd-compare-recoverable-card--proceed' => $tone === 'proceed',
            'etd-compare-recoverable-card--success' => $tone === 'success',
        ])>
            <h3 class="etd-panel-title etd-compare-recoverable-card__heading">{{ $panel['title'] ?? '' }}</h3>
            @if (filled($panelHref))
                <a href="{{ $panelHref }}" class="etd-kpi-drilldown-link etd-compare-recoverable-card__link no-underline text-inherit">
            @endif
            <p class="etd-recoverable-summary etd-compare-recoverable-card__summary">
                @if ($showInlineCompareDelta)
                    @include('ecom_tracker.partials.category-performance-metric-cell', [
                        'formatted' => number_format($sessionCount).' '.Str::plural('session', $sessionCount).' · £'.number_format($atStake, 2),
                        'showInlineCompareDelta' => true,
                        'delta' => $panelDeltas['session_count'] ?? null,
                    ])
                @else
                    {{ number_format($sessionCount) }} {{ Str::plural('session', $sessionCount) }}
                    · £{{ number_format($atStake, 2) }}
                @endif
            </p>
            @if (filled($panelHref))
                </a>
            @endif
        </div>
    @endforeach
</div>
