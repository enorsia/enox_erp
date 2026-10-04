@extends('layouts.app')

@section('title', 'Compare Store Performance')

@section('content')
<div id="ecom-tracker-compare-content" class="etd-page etd-compare-page">
    <header class="etd-page-header">
        <div class="etd-page-header-bar etd-compare-page-header-bar">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">Store performance</h1>
                <div class="etd-page-header-sub etd-compare-page-header-sub">
                    <span class="etd-compare-page-mode">Compare</span>
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <p class="etd-compare-page-subtitle">
                        <span class="etd-compare-page-subtitle-range">{{ match ($data['leftPeriod']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }}</span>
                        <span class="etd-compare-page-subtitle-vs">vs</span>
                        <span class="etd-compare-page-subtitle-range">{{ match ($data['rightPeriod']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }}</span>
                    </p>
                    <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                    <div class="etd-page-meta">
                        <span class="etd-meta-item">All times (store timezone)</span>
                        <span class="etd-meta-item text-slate-500">Preview · sample data</span>
                    </div>
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-header-actions etd-compare-header-actions">
                    @include('ecom_tracker.partials.header-back-button', [
                        'url' => $backUrl,
                        'label' => 'Back',
                    ])
                    @include('ecom_tracker.partials.header-print-button', [
                        'id' => 'etdComparePrintBtn',
                        'label' => 'Print',
                    ])
                </div>
            </div>
        </div>
    </header>

    <section class="etd-compare-exec etd-panel etd-print-exec-expanded etd-print-unit" aria-label="Executive comparison summary">
        <div class="etd-compare-exec__body" style="display: block;">
            <div class="etd-compare-exec__content">
                <div class="etd-compare-exec__header-group">
                    <h2 class="etd-compare-exec__print-heading">Executive summary</h2>
                </div>
                <div class="etd-compare-exec__table-wrap">
                    <table class="etd-compare-exec__table">
                        <thead>
                            <tr>
                                <th scope="col" class="etd-compare-exec__col-metric">Metric</th>
                                <th scope="col" class="etd-num etd-compare-exec__col-period etd-compare-exec__col-period--a">Period A</th>
                                <th scope="col" class="etd-num etd-compare-exec__col-period etd-compare-exec__col-period--b">Period B</th>
                                <th scope="col" class="etd-num etd-compare-exec__col-change">Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="etd-compare-metric-row">
                                <td class="etd-compare-metric-row__label"><span class="etd-compare-metric-row__label-text">Unique visitors</span></td>
                                <td class="etd-num">704</td>
                                <td class="etd-num">612</td>
                                <td class="etd-num"><span class="etd-compare-delta-badge etd-compare-delta--good">↑ +15.0%</span></td>
                            </tr>
                            <tr class="etd-compare-metric-row">
                                <td class="etd-compare-metric-row__label"><span class="etd-compare-metric-row__label-text">Sessions</span></td>
                                <td class="etd-num">847</td>
                                <td class="etd-num">768</td>
                                <td class="etd-num"><span class="etd-compare-delta-badge etd-compare-delta--good">↑ +10.3%</span></td>
                            </tr>
                            <tr class="etd-compare-metric-row">
                                <td class="etd-compare-metric-row__label"><span class="etd-compare-metric-row__label-text">Sale amount</span></td>
                                <td class="etd-num">£2,847.50</td>
                                <td class="etd-num">£2,410.00</td>
                                <td class="etd-num"><span class="etd-compare-delta-badge etd-compare-delta--good">↑ +18.2%</span></td>
                            </tr>
                            <tr class="etd-compare-metric-row">
                                <td class="etd-compare-metric-row__label"><span class="etd-compare-metric-row__label-text">Items sold</span></td>
                                <td class="etd-num">38</td>
                                <td class="etd-num">31</td>
                                <td class="etd-num"><span class="etd-compare-delta-badge etd-compare-delta--good">↑ +22.6%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="etd-compare-grid">
        @include('ecom_tracker.partials.compare-column-shell', [
            'side' => 'left',
            'periodBadge' => 'Period A',
            'rangeLabel' => match ($data['leftPeriod']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' },
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

        @include('ecom_tracker.partials.compare-column-shell', [
            'side' => 'right',
            'periodBadge' => 'Period B',
            'rangeLabel' => match ($data['rightPeriod']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' },
            'chartCanvasId' => 'etdTrendChartRight',
            'chartScrollId' => 'etdTrendChartScrollRight',
            'chartWrapId' => 'etdTrendChartWrapRight',
            'chartLegendId' => 'etdTrendLegendRight',
            'chartHintId' => 'etdTrendChartScrollHintRight',
            'newReturningCanvasId' => 'etdNewReturningChartRight',
            'visitors' => '612',
            'sessions' => '768',
            'revenue' => '£2,410.00',
            'itemsSold' => '31',
            'returningSessions' => '156',
            'totalStay' => '3d 18h',
            'avgStay' => '3m 18s',
            'medianDuration' => '3m 18s',
            'cartDrop' => '6.4% / 54',
            'checkoutDrop' => '3.8% / 31',
            'proceedDrop' => '1.6% / 14',
            'payments' => '3.9% / 32',
            'paymentSummary' => '32 sessions · £2,410.00',
        ])
    </div>
</div>
@endsection
