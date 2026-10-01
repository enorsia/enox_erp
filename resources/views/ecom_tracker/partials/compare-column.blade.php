@props([
    'side' => 'left',
    'd' => [],
    'filters' => [],
    'otherFilters' => [],
    'backUrl' => null,
    'compareSelfUrl' => '',
    'chartCanvasId' => 'etdTrendChartLeft',
    'chartScrollId' => 'etdTrendChartScrollLeft',
    'chartWrapId' => 'etdTrendChartWrapLeft',
    'chartLegendId' => 'etdTrendLegendLeft',
    'chartHintId' => 'etdTrendChartScrollHintLeft',
])

@php
    use App\Support\EcomTrackerViewData;
    use App\Support\TrackerTime;

    $columnPage = EcomTrackerViewData::forCompareColumn($filters, $compareSelfUrl);
    $activityFocusLink = $columnPage['activityFocusLink'];
    $activitySourceLink = $columnPage['activitySourceLink'];

    $periodLabel = $side === 'right' ? 'Period B' : 'Period A';
    $modifier = $side === 'right' ? 'etd-compare-column--b' : 'etd-compare-column--a';
    $period = $filters['period'] ?? '24h';
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $activePreset = match ($period) {
        'yesterday', '7d', '30d', 'custom' => $period,
        default => '24h',
    };
    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';

    $kpiByLabel = collect($d['kpis'] ?? [])->keyBy('label');
    $saleConversion = $d['sale_conversion'] ?? [];
    $funnelDropoff = $d['funnel_dropoff'] ?? [];

    $recoverablePanels = [
        [
            'title' => 'Cart abandoned',
            'shortTitle' => 'Cart',
            'tip' => 'Sessions that added to cart but did not begin checkout.',
            'dataKey' => 'cart_abandonment',
            'tone' => 'cart',
        ],
        [
            'title' => 'Begin checkout abandoned',
            'shortTitle' => 'Begin',
            'tip' => 'Sessions that began checkout but did not proceed.',
            'dataKey' => 'begin_checkout_abandonment',
            'tone' => 'begin',
        ],
        [
            'title' => 'Proceed checkout abandoned',
            'shortTitle' => 'Proceed',
            'tip' => 'Sessions that proceeded to checkout but did not complete payment.',
            'dataKey' => 'proceed_checkout_abandonment',
            'tone' => 'proceed',
        ],
        [
            'title' => 'Payment success',
            'shortTitle' => 'Paid',
            'tip' => 'Sessions with a successful payment.',
            'dataKey' => 'payment_success_events',
            'tone' => 'success',
        ],
    ];
    $recoverableFocusByDataKey = [
        'cart_abandonment' => 'cart_abandonment',
        'begin_checkout_abandonment' => 'begin_checkout_abandonment',
        'proceed_checkout_abandonment' => 'proceed_checkout_abandonment',
        'payment_success_events' => 'payment_success',
    ];

    $recoverablePanels = collect($recoverablePanels)->map(fn (array $panel) => [
        ...$panel,
        'data' => $d[$panel['dataKey']] ?? [],
        'href' => $activityFocusLink($recoverableFocusByDataKey[$panel['dataKey']] ?? 'audience'),
    ])->all();

    $unique = (int) (($d['new_returning']['unique'] ?? $d['new_returning']['new'] ?? 0));
    $returning = (int) ($d['new_returning']['returning'] ?? 0);
    $medianDuration = $d['duration_distribution']['median_label'] ?? null;
@endphp

<div class="etd-compare-column {{ $modifier }}"
     x-data="{
        presetKey: '{{ $activePreset }}',
        basePreset: '{{ $basePreset }}',
        dateFrom: '{{ $dateFrom }}',
        dateTo: '{{ $dateTo }}',
        filtersOpen: false,
        closeFilters() {
            this.filtersOpen = false;
        },
        openFilters() {
            this.filtersOpen = true;
            this.$nextTick(() => window.refreshEtdFilterControls?.(this.$refs.filterForm));
        },
        toggleFilters() {
            if (this.filtersOpen) {
                this.closeFilters();
                return;
            }

            this.openFilters();
        },
        toggleCustom() {
            if (this.presetKey === 'custom' && this.filtersOpen) {
                this.presetKey = this.basePreset;
                this.filtersOpen = false;
                return;
            }

            this.presetKey = 'custom';
            this.$nextTick(() => this.openFilters());
        },
     }"
     @keydown.escape.window="closeFilters()">
    <div x-show="filtersOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeFilters()"
         class="etd-compare-filter-backdrop etd-print-hide"
         aria-hidden="true"
         style="display: none"></div>

    <div class="etd-compare-column__header"
         :class="{ 'etd-compare-column__header--filters-open': filtersOpen }"
         data-compare-sync="header">
        <div class="etd-compare-column__header-row">
            <div class="etd-compare-column__title-wrap">
                <span class="etd-compare-column__badge">{{ $periodLabel }}</span>
                <span class="etd-compare-column__range"
                      title="{{ TrackerTime::rangeDisplayLabel($d['range'] ?? []) }}">
                    @include('ecom_tracker.partials.range-display-label', ['range' => $d['range'] ?? []])
                </span>
            </div>

            <div class="etd-compare-column__controls">
                <div class="etd-compare-column__controls-scroll">
                    @include('ecom_tracker.partials.compare-period-controls', [
                        'side' => $side,
                        'filters' => $filters,
                        'otherFilters' => $otherFilters,
                        'range' => $d['range'] ?? [],
                        'period' => $period,
                        'dateFrom' => $dateFrom,
                        'dateTo' => $dateTo,
                        'backUrl' => $backUrl,
                    ])
                </div>

                <div class="etd-compare-column__filter-wrap etd-print-hide">
                    <div class="etd-compare-column__filter-toggle">
                        <span class="etd-compare-column__filter-label">Filters</span>
                        <button type="button"
                                class="toggle-track"
                                :class="{ 'on': filtersOpen }"
                                @click.stop="toggleFilters()"
                                aria-label="Toggle filters"
                                :aria-expanded="filtersOpen.toString()">
                            <span class="toggle-thumb"></span>
                        </button>
                    </div>

                    @include('ecom_tracker.partials.compare-column-filters', [
                        'side' => $side,
                        'filters' => $filters,
                        'otherFilters' => $otherFilters,
                        'backUrl' => $backUrl,
                    ])
                </div>
            </div>
        </div>
    </div>

    <div class="etd-compare-column__body">
        <div class="etd-kpi-panel etd-compare-kpi-panel" data-compare-sync="kpis">
            <div class="etd-kpi-groups">
                <div class="etd-kpi-group etd-kpi-group--4 etd-kpi-group--audience">
                    <p class="etd-kpi-section-label">Audience &amp; engagement</p>
                    <div class="etd-kpi-group-grid">
                        @foreach (['Unique visitors', 'Sessions', 'Total stay time', 'Avg stay time'] as $label)
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

        <div class="etd-panel etd-compare-panel etd-compare-panel--trend" data-compare-sync="trend">
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

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="categories">
            <div class="etd-panel-head">
                <div>
                    <h3 class="etd-panel-title">Category performance</h3>
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
                    'showInlineCompareDelta' => true,
                    'compareMetricDeltas' => $categoryMetricDeltas ?? [],
                    'categoryActivityLink' => fn (array $category) => $activityFocusLink('categories', [
                        'category' => $category['category_name'] ?? '',
                        'department' => $category['department_name'] ?? '',
                    ]),
                ])
            </div>
        </div>

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="products">
            <div class="etd-panel-head">
                <div>
                    <h3 class="etd-panel-title">Product performance</h3>
                    @php $productTotals = $d['product_catalog_totals'] ?? null; @endphp
                    @if ($productTotals && ($productTotals['product_count'] ?? 0) > 0)
                        <p class="etd-panel-subtitle text-slate-500 text-sm mt-1 mb-0">
                            {{ number_format($productTotals['views']) }} views across {{ number_format($productTotals['product_count']) }} products
                            @if (($productTotals['product_count'] ?? 0) > count($d['products'] ?? []))
                                · showing top {{ count($d['products'] ?? []) }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
                @include('ecom_tracker.partials.product-performance-table', [
                    'products' => $d['products'] ?? [],
                    'showInlineCompareDelta' => true,
                    'compareMetricDeltas' => $productMetricDeltas ?? [],
                    'productActivityLink' => fn (array $query) => $activityFocusLink('products', $query),
                ])
            </div>
        </div>

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="recover-label">Recoverable sale</p>

        <div class="etd-compare-detail-only">
            @include('ecom_tracker.partials.compare-recoverable-summary', [
                'panels' => $recoverablePanels,
                'showInlineCompareDelta' => true,
                'compareMetricDeltas' => $recoverableMetricDeltas ?? [],
            ])
        </div>

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="acq-label">Acquisition &amp; audience</p>

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="devices">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Device &amp; browser</h3>
            </div>
            @include('ecom_tracker.partials.device-browser-breakdown', [
                'devices' => $d['devices'] ?? [],
                'deviceActivityLink' => fn (string $label) => $activityFocusLink('devices', array_filter([
                    'device_type' => in_array(strtolower($label), ['mobile', 'desktop', 'tablet'], true) ? strtolower($label) : null,
                ])),
                'showInlineCompareDelta' => true,
                'deviceMetricDeltas' => $deviceMetricDeltas ?? [],
                'browserMetricDeltas' => $browserMetricDeltas ?? [],
            ])
        </div>

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="traffic">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Traffic sources</h3>
            </div>
            @include('ecom_tracker.partials.traffic-sources-table', [
                'rows' => $d['traffic_sources'] ?? [],
                'activitySourceLink' => $activitySourceLink,
                'showInlineCompareDelta' => true,
                'compareMetricDeltas' => $trafficMetricDeltas ?? [],
            ])
        </div>

        <div class="etd-compare-audience-note etd-compare-audience-note--compare-inline etd-compare-detail-only" data-compare-sync="audience">
            @php $audienceDeltas = $audienceMetricDeltas ?? []; @endphp
            @if ($medianDuration || ($unique + $returning) > 0)
                @if ($medianDuration)
                    <span class="etd-compare-audience-note__item">
                        Median session duration:
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => $medianDuration,
                            'showInlineCompareDelta' => true,
                            'delta' => $audienceDeltas['median_seconds'] ?? null,
                        ])
                    </span>
                @endif
                @if (($unique + $returning) > 0)
                    @if ($medianDuration)
                        <span class="etd-header-sep" aria-hidden="true">·</span>
                    @endif
                    <span class="etd-compare-audience-note__item">
                        <a href="{{ $activityFocusLink('audience') }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($unique).' unique',
                                'showInlineCompareDelta' => true,
                                'delta' => $audienceDeltas['unique'] ?? null,
                            ])
                        </a>
                    </span>
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <span class="etd-compare-audience-note__item">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => number_format($returning).' returning',
                            'showInlineCompareDelta' => true,
                            'delta' => $audienceDeltas['returning'] ?? null,
                        ])
                    </span>
                @endif
            @endif
        </div>
    </div>
</div>
