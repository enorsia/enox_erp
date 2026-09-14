@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@section('content')
@php
    $d = $dashboard;
    $period = $page['period'];
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $queryParams = $page['queryParams'];
    $detailLink = $page['detailLink'];
    $activityFocusLink = $page['activityFocusLink'];
    $hasActiveFilters = $page['hasActiveFilters'];

    $kpiByLabel = collect($d['kpis'])->keyBy('label');
    $kpiGroups = [
        [
            'title' => 'Audience & engagement',
            'labels' => ['Unique visitors', 'Sessions', 'Total stay time', 'Avg stay time'],
            'cols' => 4,
        ],
    ];

    $saleConversion = $d['sale_conversion'] ?? [];
    $funnelDropoff = $d['funnel_dropoff'] ?? [];

    $period = $period === '90d' ? '30d' : $period;
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';

    $baseQuery = request()->except([
        'date_from', 'date_to', 'period',
        'search', 'category', 'color', 'size', 'sort_by', 'activity',
        'has_purchases', 'has_views', 'has_adds', 'event_scenario',
    ]);
@endphp

<div id="ecom-tracker-dashboard-content" class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.dashboard'),
        'resetUrl' => route('admin.ecom-tracker.dashboard'),
        'showPeriodFilters' => true,
        'showSessionFilters' => true,
        'sessionFiltersHeading' => 'Sessions & audience',
        'includeCountry' => false,
        'period' => $period,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'baseQuery' => $baseQuery,
        'range' => $d['range'],
        'routeName' => 'admin.ecom-tracker.dashboard',
        'periodFiltersMobileOnly' => true,
    ])

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                presetKey: '{{ $activePreset }}',
                basePreset: '{{ $basePreset }}',
                dateFrom: '{{ $dateFrom }}',
                dateTo: '{{ $dateTo }}',
                toggleCustom() {
                    this.presetKey = this.presetKey === 'custom' ? this.basePreset : 'custom';
                },
                applyCustom() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', 'custom');
                    if (this.dateFrom) {
                        url.searchParams.set('date_from', this.dateFrom);
                    } else {
                        url.searchParams.delete('date_from');
                    }
                    if (this.dateTo) {
                        url.searchParams.set('date_to', this.dateTo);
                    } else {
                        url.searchParams.delete('date_to');
                    }
                    window.location.href = url.toString();
                }
             }">
            <div class="etd-page-header-main">
                <div class="etd-page-header-left-stack">
                    <h1 class="etd-page-title">Store performance</h1>
                    <div class="etd-page-meta">
                        @include('ecom_tracker.partials.timezone-notice')
                        @include('ecom_tracker.partials.analytics-cache-notice', ['analytics_cache' => $d['analytics_cache'] ?? null])
                    </div>
                    <p class="etd-page-range">{{ $d['range']['label'] }}</p>
                </div>
            </div>

            <div class="etd-page-header-toolbar">
                <div class="etd-header-toolbar-row">
                    @include('ecom_tracker.partials.dashboard-header-shortcuts', [
                        'showComparisonLink' => true,
                        'showUserActivityLink' => true,
                    ])
                    @include('ecom_tracker.partials.dashboard-header-period-controls', [
                        'baseQuery' => $baseQuery,
                        'range' => $d['range'],
                        'period' => $period,
                        'dateFrom' => $dateFrom,
                        'dateTo' => $dateTo,
                        'routeName' => 'admin.ecom-tracker.dashboard',
                    ])
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-tracker.dashboard'),
                        'active' => count(request()->query()) > 0,
                    ])
                    @include('ecom_tracker.partials.header-filter-button', [
                        'active' => $hasActiveFilters,
                        'count' => $hasActiveFilters ? $activeFilterCount : 0,
                    ])
                    @include('ecom_tracker.partials.header-print-button')
                </div>
            </div>
        </div>

        @include('ecom_tracker.partials.active-filter-chips', ['chips' => $filterChips ?? []])
    </header>

    <div class="etd-kpi-panel mb-5">
        <div class="etd-kpi-groups">
            @foreach ($kpiGroups as $group)
                <div class="etd-kpi-group etd-kpi-group--{{ $group['cols'] }}{{ ($group['cols'] ?? null) === 4 ? ' etd-kpi-group--audience' : '' }}">
                    <p class="etd-kpi-section-label">{{ $group['title'] }}</p>
                    <div class="etd-kpi-group-grid">
                        @foreach ($group['labels'] as $label)
                            @if ($kpiByLabel->has($label))
                                @php $kpi = $kpiByLabel->get($label); @endphp
                                <a href="{{ $activityFocusLink('audience') }}" class="etd-kpi-drilldown-link no-underline text-inherit">
                                    <div class="etd-kpi etd-kpi--compact">
                                        @include('ecom_tracker.partials.kpi-label-with-tip', [
                                            'label' => $kpi['label'],
                                            'tip' => $kpi['tip'] ?? null,
                                        ])
                                        @include('ecom_tracker.partials.kpi-value-with-comparison', [
                                            'formatted' => $kpi['formatted'],
                                            'comparison' => $kpi['comparison'] ?? null,
                                            'valueClass' => $kpi['value_class'] ?? '',
                                        ])
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($saleConversion !== [])
                @include('ecom_tracker.partials.kpi-metric-group', [
                    'title' => 'Sale & conversion',
                    'modifier' => 'etd-kpi-group--sale',
                    'cols' => 2,
                    'metricHrefs' => [
                        $activityFocusLink('conversion'),
                        $activityFocusLink('conversion'),
                    ],
                    'metrics' => [
                        $saleConversion['item_qty'] ?? null,
                        $saleConversion['revenue'] ?? null,
                    ],
                ])
            @endif

            @if ($funnelDropoff !== [])
                @include('ecom_tracker.partials.kpi-metric-group', [
                    'title' => 'Funnel drop-off',
                    'modifier' => 'etd-kpi-group--funnel',
                    'cols' => 4,
                    'metricHrefs' => [
                        $activityFocusLink('cart_abandonment'),
                        $activityFocusLink('begin_checkout_abandonment'),
                        $activityFocusLink('proceed_checkout_abandonment'),
                        $activityFocusLink('payment_success'),
                    ],
                    'metrics' => [
                        $funnelDropoff['cart_drop'] ?? null,
                        $funnelDropoff['checkout_drop'] ?? null,
                        $funnelDropoff['proceed_drop'] ?? null,
                        $funnelDropoff['payments'] ?? null,
                    ],
                ])
            @endif
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
                    @php $categoryTotals = $d['category_catalog_totals'] ?? null; @endphp
                    @if ($categoryTotals && ($categoryTotals['category_count'] ?? 0) > 0)
                        <p class="etd-panel-subtitle text-slate-500 dark:text-slate-400 text-sm mt-1 mb-0">
                            {{ number_format($categoryTotals['category_views'] ?? 0) }} category views · {{ number_format($categoryTotals['product_views'] ?? 0) }} product views across {{ number_format($categoryTotals['category_count']) }} categories
                        </p>
                    @endif
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
                @include('ecom_tracker.partials.category-performance-table', [
                    'departments' => $d['category_departments'] ?? [],
                    'showCurrency' => true,
                    'categoryActivityLink' => fn (array $category) => $activityFocusLink('categories', [
                        'category' => $category['category_name'] ?? '',
                        'department' => $category['department_name'] ?? '',
                    ]),
                ])
            </div>
        </div>

        <div class="etd-panel etd-print-unit" id="products">
            <div class="etd-panel-head">
                <div>
                    <h2 class="etd-panel-title">Product performance</h2>
                    @php $productTotals = $d['product_catalog_totals'] ?? null; @endphp
                    @if ($productTotals && ($productTotals['product_count'] ?? 0) > 0)
                        <p class="etd-panel-subtitle text-slate-500 text-sm mt-1 mb-0">
                            {{ number_format($productTotals['views']) }} views across {{ number_format($productTotals['product_count']) }} products
                            @if (($productTotals['product_count'] ?? 0) > count($d['products']))
                                · showing top {{ count($d['products']) }}
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
                    @forelse ($d['products'] as $product)
                        <tr>
                            <td class="etd-col-product">
                                @php
                                    $productDrillQuery = array_filter([
                                        'product_code' => $product['code'] ?? ($product['product_code'] ?? null),
                                    ]);
                                @endphp
                                <a href="{{ $activityFocusLink('products', $productDrillQuery) }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                                    {{ $product['name'] }}
                                </a>
                            </td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['views']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['adds']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['proceed_checkouts'] ?? 0) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($product['qty'] ?? 0) }}</td>
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
        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'cart-abandon',
            'title' => 'Cart abandoned',
            'dataKey' => 'cart_abandonment',
            'detailSection' => 'cart-abandonment',
            'panelTone' => 'cart',
            'emptyMessage' => 'No cart abandonment in this period.',
        ])

        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'begin-checkout-abandon',
            'title' => 'Begin checkout abandoned',
            'dataKey' => 'begin_checkout_abandonment',
            'detailSection' => 'begin-checkout-abandonment',
            'panelTone' => 'begin',
            'emptyMessage' => 'No begin checkout abandonment in this period.',
        ])

        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'proceed-checkout-abandon',
            'title' => 'Proceed checkout abandoned',
            'dataKey' => 'proceed_checkout_abandonment',
            'detailSection' => 'proceed-checkout-abandonment',
            'panelTone' => 'proceed',
            'emptyMessage' => 'No proceed checkout abandonment in this period.',
        ])

        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'payment-success-events',
            'title' => 'Payment success',
            'dataKey' => 'payment_success_events',
            'detailSection' => 'payment-success-events',
            'panelTone' => 'success',
            'emptyMessage' => 'No payment success events in this period.',
        ])
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
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('devices')])
            </div>
        </div>
        @include('ecom_tracker.partials.device-browser-breakdown', [
            'devices' => $d['devices'],
            'deviceActivityLink' => fn (string $label) => $activityFocusLink('devices', array_filter([
                'device_type' => in_array(strtolower($label), ['mobile', 'desktop', 'tablet'], true) ? strtolower($label) : null,
            ])),
        ])
    </div>

    <div class="etd-panel etd-print-unit mb-3" id="traffic">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Traffic sources</h2>
            <div class="etd-panel-head-actions">
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('traffic-sources')])
            </div>
        </div>
        @include('ecom_tracker.partials.traffic-sources-table', [
            'rows' => $d['traffic_sources'],
            'activitySourceLink' => $page['activitySourceLink'],
        ])
    </div>

    @include('ecom_tracker.partials.acquisition-insights', [
        'distribution' => $d['duration_distribution'] ?? [],
        'newReturning' => $d['new_returning'] ?? [],
        'activityDurationLink' => fn (array $bucket) => filled($bucket['key'] ?? null)
            ? $activityFocusLink('duration', ['duration_bucket' => $bucket['key']])
            : null,
    ])
            </div>
        </div>
    </section>
</div>

<script>
    window.ecomTrackerDashboardData = @json($d['chart_payload']);
</script>
@endsection
