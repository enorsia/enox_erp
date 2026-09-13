@props([
    'side' => 'left',
    'd' => [],
    'filters' => [],
    'otherFilters' => [],
    'backUrl' => null,
    'tableCompareDeltas' => [],
    'chartCanvasId' => 'etdTrendChartLeft',
    'chartScrollId' => 'etdTrendChartScrollLeft',
    'chartWrapId' => 'etdTrendChartWrapLeft',
    'chartLegendId' => 'etdTrendLegendLeft',
    'chartHintId' => 'etdTrendChartScrollHintLeft',
])

@php
    use App\Support\TrackerTime;

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
        ['title' => 'Cart abandoned', 'dataKey' => 'cart_abandonment', 'tone' => 'cart'],
        ['title' => 'Begin checkout abandoned', 'dataKey' => 'begin_checkout_abandonment', 'tone' => 'begin'],
        ['title' => 'Proceed checkout abandoned', 'dataKey' => 'proceed_checkout_abandonment', 'tone' => 'proceed'],
        ['title' => 'Payment success', 'dataKey' => 'payment_success_events', 'tone' => 'success'],
    ];
    $recoverablePanels = collect($recoverablePanels)->map(fn (array $panel) => [
        ...$panel,
        'data' => $d[$panel['dataKey']] ?? [],
    ])->all();

    $unique = (int) (($d['new_returning']['unique'] ?? $d['new_returning']['new'] ?? 0));
    $returning = (int) ($d['new_returning']['returning'] ?? 0);
    $medianDuration = $d['duration_distribution']['median_label'] ?? null;
    $showTableDeltas = $side === 'left' && ! empty($tableCompareDeltas);
    $deviceDeltas = $tableCompareDeltas['devices'] ?? [];
    $browserDeltas = $tableCompareDeltas['browsers'] ?? [];
    $trafficDeltas = $tableCompareDeltas['traffic'] ?? [];
    $productDeltas = $tableCompareDeltas['products'] ?? [];
    $categoryDeltas = $tableCompareDeltas['categories'] ?? [];
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
                            @endif
                        @endforeach
                    </div>
                </div>

                @if ($saleConversion !== [])
                    @include('ecom_tracker.partials.kpi-metric-group', [
                        'title' => 'Sale & conversion',
                        'modifier' => 'etd-kpi-group--sale',
                        'cols' => 2,
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
                    'readOnly' => true,
                    'showCompareDelta' => $showTableDeltas,
                    'compareDeltas' => $categoryDeltas,
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
                            @if ($showTableDeltas)
                                <th class="etd-num etd-col-metric etd-compare-table-delta-head">Δ vs B</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($d['products'] ?? [] as $product)
                            @php
                                $productDelta = $productDeltas[$product['name'] ?? ''] ?? null;
                                $productRowClass = match ($productDelta['highlight'] ?? null) {
                                    'up' => 'etd-compare-row--up',
                                    'down' => 'etd-compare-row--down',
                                    default => '',
                                };
                            @endphp
                            <tr @class([$productRowClass])>
                                <td class="etd-col-product">{{ $product['name'] }}</td>
                                <td class="etd-num etd-col-metric">{{ number_format($product['views']) }}</td>
                                <td class="etd-num etd-col-metric">{{ number_format($product['adds']) }}</td>
                                <td class="etd-num etd-col-metric">{{ number_format($product['proceed_checkouts'] ?? 0) }}</td>
                                <td class="etd-num etd-col-metric">{{ number_format($product['qty'] ?? 0) }}</td>
                                <td class="etd-num etd-col-metric">
                                    £{{ number_format($product['revenue'], 2) }}
                                    <div class="etd-mini-bar"><div style="width: {{ $product['revenue_bar_percent'] }}%"></div></div>
                                </td>
                                @if ($showTableDeltas)
                                    @include('ecom_tracker.partials.compare-table-delta-cell', ['delta' => $productDelta])
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $showTableDeltas ? 7 : 6 }}" class="text-slate-400">No product activity in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="recover-label">Recoverable sale</p>

        <div class="etd-compare-detail-only">
            @include('ecom_tracker.partials.compare-recoverable-summary', ['panels' => $recoverablePanels])
        </div>

        <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="acq-label">Acquisition &amp; audience</p>

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="devices">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Device &amp; browser</h3>
            </div>
            @include('ecom_tracker.partials.device-browser-breakdown', [
                'devices' => $d['devices'] ?? [],
                'readOnly' => true,
                'showCompareDelta' => $showTableDeltas,
                'deviceDeltas' => $deviceDeltas,
                'browserDeltas' => $browserDeltas,
            ])
        </div>

        <div class="etd-panel etd-compare-panel etd-compare-detail-only" data-compare-sync="traffic">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Traffic sources</h3>
            </div>
            @include('ecom_tracker.partials.traffic-sources-table', [
                'rows' => $d['traffic_sources'] ?? [],
                'readOnly' => true,
                'showCompareDelta' => $showTableDeltas,
                'compareDeltas' => $trafficDeltas,
            ])
        </div>

        <div class="etd-compare-audience-note etd-compare-detail-only" data-compare-sync="audience">
            @if ($medianDuration || ($unique + $returning) > 0)
                @if ($medianDuration)
                    <span>Median session duration: <strong>{{ $medianDuration }}</strong></span>
                @endif
                @if (($unique + $returning) > 0)
                    @if ($medianDuration)
                        <span class="etd-header-sep" aria-hidden="true">·</span>
                    @endif
                    <span><strong>{{ number_format($unique) }}</strong> unique · <strong>{{ number_format($returning) }}</strong> returning</span>
                @endif
            @endif
        </div>
    </div>
</div>
