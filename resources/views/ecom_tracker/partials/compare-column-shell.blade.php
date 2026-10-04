@props([
    'side' => 'left',
    'periodBadge' => 'Period A',
    'rangeLabel' => 'Today',
    'chartCanvasId' => 'etdTrendChartLeft',
    'chartScrollId' => 'etdTrendChartScrollLeft',
    'chartWrapId' => 'etdTrendChartWrapLeft',
    'chartLegendId' => 'etdTrendLegendLeft',
    'chartHintId' => 'etdTrendChartScrollHintLeft',
    'newReturningCanvasId' => 'etdNewReturningChartLeft',
    'visitors' => '704',
    'sessions' => '847',
    'revenue' => '£2,847.50',
    'itemsSold' => '38',
    'returningSessions' => '143',
    'totalStay' => '4d 11h',
    'avgStay' => '3m 42s',
    'medianDuration' => '3m 42s',
    'cartDrop' => '7.3% / 62',
    'checkoutDrop' => '3.3% / 28',
    'proceedDrop' => '1.4% / 12',
    'payments' => '4.5% / 38',
    'paymentSummary' => '38 sessions · £2,847.50',
])

@php
    $modifier = $side === 'right' ? 'etd-compare-column--b' : 'etd-compare-column--a';
@endphp

<div class="etd-compare-column {{ $modifier }}">
    <div class="etd-compare-column__header">
        <div class="etd-compare-column__header-row">
            <div class="etd-compare-column__title-wrap">
                <span class="etd-compare-column__badge">{{ $periodBadge }}</span>
                <span class="etd-compare-column__range">{{ $rangeLabel }}</span>
            </div>
        </div>
    </div>

    <div class="etd-compare-column__body">
        @include('ecom_tracker.partials.demo.static-kpis-compare', [
            'visitors' => $visitors,
            'sessions' => $sessions,
            'totalStay' => $totalStay,
            'avgStay' => $avgStay,
            'itemsSold' => $itemsSold,
            'revenue' => $revenue,
            'cartDrop' => $cartDrop,
            'checkoutDrop' => $checkoutDrop,
            'proceedDrop' => $proceedDrop,
            'payments' => $payments,
        ])

        <div class="etd-panel etd-compare-panel etd-compare-panel--trend etd-print-unit" data-compare-sync="trend">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Shopper journey over time</h3>
            </div>
            <div class="etd-trend-chart">
                <div class="etd-trend-legend" id="{{ $chartLegendId }}" hidden></div>
                <div class="etd-trend-chart-scroll" id="{{ $chartScrollId }}">
                    <div class="etd-chart-wrap xl etd-chart-wrap--trend" id="{{ $chartWrapId }}">
                        <canvas id="{{ $chartCanvasId }}"></canvas>
                    </div>
                </div>
                <p class="etd-trend-chart-scroll-hint" id="{{ $chartHintId }}" hidden>Swipe horizontally to see all dates</p>
            </div>
        </div>

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="merch-label">Merchandising</p>

        @include('ecom_tracker.partials.demo.static-merchandising-compare')

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="recover-label">Recoverable sale</p>

        @include('ecom_tracker.partials.demo.static-recoverable-compare', [
            'paymentSummary' => $paymentSummary,
        ])

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="acq-label">Acquisition &amp; audience</p>

        @include('ecom_tracker.partials.demo.static-acquisition-compare', [
            'newReturningCanvasId' => $newReturningCanvasId,
            'visitors' => $visitors,
            'returningSessions' => $returningSessions,
            'sessions' => $sessions,
            'medianDuration' => $medianDuration,
        ])
    </div>
</div>
