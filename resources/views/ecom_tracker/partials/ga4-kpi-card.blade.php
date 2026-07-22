@props([
    'label',
    'value' => 0,
    'delta_pct' => null,
    'delta_direction' => null,
    'delta_label' => null,
    'sparkline' => [],
    'comparison_label' => null,
    'compact' => false,
])

@php
    $sparklineId = 'spark-' . md5($label . ($comparison_label ?? ''));
    $points = ! empty($sparkline) ? array_values($sparkline) : [0];
    $max = max(1, max($points));
@endphp

<div @class(['etd-kpi ga4-kpi-card', 'ga4-kpi-card--compact' => $compact])>
    <div class="ga4-kpi-card__label">{{ $label }}</div>
    <div class="ga4-kpi-card__value">{{ is_numeric($value) ? number_format((int) $value) : $value }}</div>

  @if ($delta_pct !== null && is_numeric($delta_pct))
        <div @class([
            'ga4-kpi-card__delta',
            'ga4-kpi-card__delta--up' => $delta_direction === 'up',
            'ga4-kpi-card__delta--down' => $delta_direction === 'down',
        ])>
            @if ($delta_direction === 'up') ↑ @elseif ($delta_direction === 'down') ↓ @else → @endif
            {{ ($delta_pct > 0 ? '+' : '') . number_format($delta_pct, 1) }}%
            @if ($comparison_label)
                <span class="ga4-kpi-card__delta-vs">vs {{ $comparison_label }}</span>
            @endif
        </div>
    @elseif ($delta_label === 'new')
        <div class="ga4-kpi-card__delta ga4-kpi-card__delta--new">New</div>
    @elseif ($delta_label === 'no_prior_data' || ($delta_pct === null && filled($comparison_label)))
        <div class="ga4-kpi-card__delta ga4-kpi-card__delta--muted">No prior data</div>
    @endif

    @if (! $compact && count($points) > 1)
        <div class="ga4-kpi-card__sparkline" aria-hidden="true">
            <svg viewBox="0 0 80 24" preserveAspectRatio="none" class="ga4-kpi-card__sparkline-svg">
                @php
                    $step = 80 / max(1, count($points) - 1);
                    $coords = [];
                    foreach ($points as $i => $pt) {
                        $coords[] = round($i * $step, 1) . ',' . round(24 - (($pt / $max) * 22), 1);
                    }
                @endphp
                <polyline fill="none" stroke="currentColor" stroke-width="1.5" points="{{ implode(' ', $coords) }}" />
            </svg>
        </div>
    @endif
</div>
