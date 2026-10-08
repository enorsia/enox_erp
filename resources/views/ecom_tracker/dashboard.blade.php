@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@php
    $activityLink = $data['activityLink'];
    $categoryTotals = $dashboard['categories']['totals'];
    $productTotals = $dashboard['products']['totals'];
    $products = $dashboard['products']['rows'];
@endphp

@section('content')

<script>
    window.ecomTrackerDashboardData = @json($dashboard['charts']);
</script>

<div id="ecom-tracker-dashboard-content"
     class="etd-page"
     data-sync-url="{{ route('admin.ecom-tracker.dashboard.sync') }}">

@include('ecom_tracker.filter-drawer')

<header class="etd-page-header">
    <div class="etd-page-header-bar">
        <div class="etd-page-header-main">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">Store performance</h1>
                <div class="etd-page-meta">
                    <span class="etd-meta-item">All times (store timezone)</span>
                </div>
                <p class="etd-page-range">{{ match ($data['period']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }} ({{ $data['rangeLabel'] }})</p>
            </div>
        </div>
        <div class="etd-page-header-toolbar">
            <div class="etd-header-toolbar-row">
                <a href="{{ route('admin.ecom-tracker.dashboard.compare') }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <span class="etd-header-btn-text">Compare</span>
                </a>
                <a href="{{ $activityLink() }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <span class="etd-header-btn-text">Activity</span>
                </a>
                <div class="etd-header-period-nav etd-print-hide">
                    @if ($data['prevUrl'])
                        <a href="{{ $data['prevUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Previous period" title="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></a>
                    @else
                        <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></span>
                    @endif
                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                        @foreach (['24h' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7d', '30d' => '30d'] as $periodKey => $periodLabel)
                            <a href="{{ route('admin.ecom-tracker.dashboard', ['period' => $periodKey]) }}" class="etd-segmented-btn {{ $data['period'] === $periodKey ? 'active' : '' }} no-underline">{{ $periodLabel }}</a>
                        @endforeach
                        <button type="button" class="etd-segmented-btn js-ecom-dashboard-period-custom-toggle {{ $data['period'] === 'custom' ? 'active' : '' }}" aria-label="Custom date range">Custom</button>
                    </div>
                    <div id="ecom-dashboard-header-custom-dates"
                         class="etd-custom-dates etd-custom-dates--header @if($data['period'] !== 'custom') etd-custom-dates--closed @endif"
                         data-etd-date-range-single>
                        <input type="text" class="etd-flatpickr-date-range f-input etd-date-input etd-date-input--range" data-default-from="{{ $data['dateFrom'] }}" data-default-to="{{ $data['dateTo'] }}" placeholder="Select date range" readonly aria-label="Custom date range">
                        <input type="hidden" data-range="from" id="ecom-dashboard-header-date-from" value="{{ $data['dateFrom'] }}">
                        <input type="hidden" data-range="to" id="ecom-dashboard-header-date-to" value="{{ $data['dateTo'] }}">
                        <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply js-ecom-dashboard-header-custom-apply">Apply</button>
                    </div>
                    @if ($data['nextUrl'])
                        <a href="{{ $data['nextUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Next period" title="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></a>
                    @else
                        <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                    @endif
                </div>
                <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-tracker.dashboard'),
                        'active' => $data['period'] !== '24h',
                    ])
                    @include('ecom_tracker.partials.header-print-button')
                    <button type="button" id="ecom-dashboard-filter-open" class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open" aria-label="Filters" aria-expanded="false">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                    </button>
                    <button type="button" id="ecom-dashboard-sync" class="etd-header-btn etd-header-btn--icon etd-header-btn--sync etd-print-hide" aria-label="Sync" title="Sync">
                        <span class="etd-header-btn-text">Sync</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>


<div class="etd-kpi-panel mb-5 etd-print-unit">
    <div class="etd-kpi-groups">
        @foreach ($dashboard['kpiGroups'] as $group)
            @include('ecom_tracker.partials.kpi-metric-group', [
                'title' => $group['title'],
                'modifier' => $group['modifier'],
                'cols' => $group['cols'],
                'metrics' => $group['metrics'],
                'metricHrefs' => array_map(fn (array $metric) => $activityLink($metric['activity']), $group['metrics']),
            ])
        @endforeach
    </div>
</div>

<div class="etd-print-lead mb-3 etd-print-unit">
    <div class="etd-panel" id="trend">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Shopper journey over time</h2>
        </div>
        <div class="etd-trend-chart">
            <div class="etd-trend-legend" id="etdTrendLegend" hidden></div>
            <div class="etd-trend-chart-scroll" id="etdTrendChartScroll">
                <div class="etd-chart-wrap xl etd-chart-wrap--trend" id="etdTrendChartWrap">
                    <canvas id="etdTrendChart"></canvas>
                </div>
            </div>
            <p class="etd-trend-chart-scroll-hint" id="etdTrendChartScrollHint" hidden>Swipe horizontally to see all dates</p>
        </div>
    </div>
</div>

<section class="etd-dashboard-section">
    <div class="etd-section-print-block etd-print-section">
        <div class="etd-section-intro">
            <h2 class="etd-section-title"><span class="etd-section-num">01</span> Merchandising decisions</h2>
            <p class="etd-section-note">Where traffic goes vs where money is made — use to reorder homepage, deprioritize dead categories, and flag products that get eyeballs but not carts.</p>
        </div>
        <div class="etd-section-print-body">
    <div class="etd-grid-4-8 mb-3">
        <div class="etd-panel etd-print-unit" id="categories">
            <div class="etd-panel-head">
                <div>
                    <h2 class="etd-panel-title">Category performance</h2>
                    @if ($categoryTotals['category_count'] > 0)
                        <p class="etd-panel-subtitle text-slate-500 dark:text-slate-400 text-sm mt-1 mb-0">
                            {{ number_format($categoryTotals['category_views']) }} category views · {{ number_format($categoryTotals['product_views']) }} product views across {{ number_format($categoryTotals['category_count']) }} categories
                        </p>
                    @endif
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
                @include('ecom_tracker.partials.category-performance-table', [
                    'departments' => $dashboard['categories']['departments'],
                    'showCurrency' => true,
                    'categoryActivityLink' => fn (array $category) => $activityLink([
                        'department' => $category['department_id'],
                        'category' => $category['category_id'],
                    ]),
                ])
            </div>
        </div>

        <div class="etd-panel etd-print-unit" id="products">
            <div class="etd-panel-head">
                <div>
                    <h2 class="etd-panel-title">Product performance</h2>
                    @if ($productTotals['product_count'] > 0)
                        <p class="etd-panel-subtitle text-slate-500 text-sm mt-1 mb-0">
                            {{ number_format($productTotals['views']) }} views across {{ number_format($productTotals['product_count']) }} products
                            @if ($productTotals['product_count'] > count($products))
                                · showing top {{ count($products) }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table etd-table--product-catalog etd-table--performance-metrics">
                <thead>
                    <tr>
                        <th class="etd-col-product">Product</th>
                        <th class="etd-num etd-col-metric">Views</th>
                        <th class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => 'Adds',
                                'tip' => 'Add to cart',
                                'align' => 'center',
                            ])
                        </th>
                        <th class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => 'Proceed',
                                'tip' => 'Proceed to checkout',
                                'align' => 'center',
                            ])
                        </th>
                        <th class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => 'Sold',
                                'tip' => 'Sale item',
                                'align' => 'center',
                            ])
                        </th>
                        <th class="etd-num etd-col-metric">Sale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="etd-col-product">
                                <a href="{{ $activityLink(['search' => $product['code']]) }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                                    {{ $product['name'] }}
                                </a>
                            </td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['views']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['adds']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['proceed_checkouts']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['qty']) }}</td>
                            <td class="etd-num etd-col-metric">
                                £{{ number_format($product['revenue'], 2) }}
                                <div class="etd-mini-bar"><div style="width: {{ $product['revenue_bar_percent'] }}%"></div></div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-slate-400">No product activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>
        </div>
    </div>
</section>

<section class="etd-dashboard-section">
    <div class="etd-section-print-block etd-print-section">
        <div class="etd-section-intro">
            <h2 class="etd-section-title"><span class="etd-section-num">02</span> Recoverable sale</h2>
            <p class="etd-section-note">Sessions at each funnel step plus completed payments — click a session to review activity.</p>
        </div>
        <div class="etd-section-print-body">
    <div class="etd-recoverable-section mb-3">
        <div class="etd-grid-4 etd-grid-4--recoverable">
        @foreach ($dashboard['recoverable'] as $panel)
            @include('ecom_tracker.partials.abandonment-panel', [
                'panel' => $panel,
                'panelId' => $panel['panelId'],
                'title' => $panel['title'],
                'panelTone' => $panel['tone'],
                'emptyMessage' => $panel['emptyMessage'],
                'detailUrl' => $activityLink(['funnel' => $panel['stage']]),
            ])
        @endforeach
        </div>
    </div>
        </div>
    </div>
</section>

<section class="etd-dashboard-section">
    <div class="etd-section-print-block etd-print-section">
        <div class="etd-section-intro">
            <h2 class="etd-section-title"><span class="etd-section-num">03</span> Acquisition &amp; audience</h2>
            <p class="etd-section-note">Device mix and where sessions originate.</p>
        </div>
        <div class="etd-section-print-body">
    <div class="etd-panel etd-panel--acquisition etd-panel--device-browser-full etd-print-unit mb-3" id="device">
        <div class="etd-panel-head etd-panel-head--device-browser">
            <h2 class="etd-panel-title">Device &amp; browser</h2>
            <div class="etd-panel-head-actions">
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $activityLink()])
            </div>
        </div>
        @include('ecom_tracker.partials.device-browser-breakdown', [
            'devices' => $dashboard['devices'],
            'deviceActivityLink' => fn (array $row) => $activityLink(['device_type' => $row['id']]),
        ])
    </div>

    <div class="etd-panel etd-print-unit mb-3" id="traffic">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Traffic sources</h2>
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $activityLink()])
        </div>
        @include('ecom_tracker.partials.traffic-sources-table', [
            'rows' => $dashboard['traffic'],
            'activitySourceLink' => fn (array $row) => $activityLink(['utm_source' => $row['id']]),
        ])
    </div>

    @include('ecom_tracker.partials.acquisition-insights', [
        'distribution' => $dashboard['durations'],
        'newReturning' => $dashboard['newReturning'],
        'activityDurationLink' => fn (array $bucket) => $activityLink(['duration' => $bucket['key']]),
    ])
        </div>
    </div>
</section>

</div>
@endsection
