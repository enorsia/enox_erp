@props([
    'side' => 'left',
    'd' => [],
    'filters' => [],
    'otherFilters' => [],
    'backUrl' => null,
    'chartCanvasId' => 'etdTrendChartLeft',
    'chartScrollId' => 'etdTrendChartScrollLeft',
    'chartWrapId' => 'etdTrendChartWrapLeft',
    'chartLegendId' => 'etdTrendLegendLeft',
    'chartHintId' => 'etdTrendChartScrollHintLeft',
])

@php
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
@endphp

<div class="etd-compare-column {{ $modifier }}"
     x-data="{
        presetKey: '{{ $activePreset }}',
        basePreset: '{{ $basePreset }}',
        dateFrom: '{{ $dateFrom }}',
        dateTo: '{{ $dateTo }}',
        filtersOpen: false,
        toggleCustom() {
            this.presetKey = this.presetKey === 'custom' ? this.basePreset : 'custom';
        },
        applyCustom() {
            const url = new URL(window.location.href);
            const prefix = '{{ $side === 'right' ? 'right_' : 'left_' }}';
            url.searchParams.set(prefix + 'period', 'custom');
            if (this.dateFrom) {
                url.searchParams.set(prefix + 'date_from', this.dateFrom);
            } else {
                url.searchParams.delete(prefix + 'date_from');
            }
            if (this.dateTo) {
                url.searchParams.set(prefix + 'date_to', this.dateTo);
            } else {
                url.searchParams.delete(prefix + 'date_to');
            }
            window.location.href = url.toString();
        }
     }">
    <div class="etd-compare-column__header" data-compare-sync="header">
        <div class="etd-compare-column__header-row">
            <div class="etd-compare-column__title-wrap">
                <span class="etd-compare-column__badge">{{ $periodLabel }}</span>
                <span class="etd-compare-column__range"
                      title="{{ $d['range']['label'] ?? '' }}">{{ $d['range']['label'] ?? '' }}</span>
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

                <div class="etd-compare-column__filter-toggle etd-print-hide">
                    <span class="etd-compare-column__filter-label">Filters</span>
                    <button type="button"
                            class="toggle-track"
                            :class="{ 'on': filtersOpen }"
                            @click="filtersOpen = !filtersOpen"
                            aria-label="Toggle filters"
                            :aria-expanded="filtersOpen.toString()">
                        <span class="toggle-thumb"></span>
                    </button>
                </div>
            </div>
        </div>

        @include('ecom_tracker.partials.compare-column-filters', [
            'side' => $side,
            'filters' => $filters,
            'otherFilters' => $otherFilters,
            'backUrl' => $backUrl,
        ])

        <div x-show="presetKey === 'custom'"
             x-collapse
             x-effect="if (presetKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
             class="etd-custom-dates etd-custom-dates--inline etd-date-range etd-compare-custom-dates"
             data-etd-date-range
             @if ($activePreset !== 'custom') style="display: none" @endif>
            <input type="text"
                   x-model="dateFrom"
                   data-range="from"
                   data-default="{{ $dateFrom }}"
                   value="{{ $dateFrom }}"
                   placeholder="From date"
                   readonly
                   class="etd-flatpickr-date f-input etd-date-input"
                   aria-label="From date">
            <span class="etd-custom-dates-sep">–</span>
            <input type="text"
                   x-model="dateTo"
                   data-range="to"
                   data-default="{{ $dateTo }}"
                   value="{{ $dateTo }}"
                   placeholder="To date"
                   readonly
                   class="etd-flatpickr-date f-input etd-date-input"
                   aria-label="To date">
            <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
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
                                    <p class="etd-kpi-value {{ $kpi['value_class'] ?? '' }}">{{ $kpi['formatted'] }}</p>
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

        <div class="etd-panel etd-compare-panel" data-compare-sync="trend">
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

        <p class="etd-compare-block-label" data-compare-sync="merch-label">Merchandising</p>

        <div class="etd-panel etd-compare-panel" data-compare-sync="categories">
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
                ])
            </div>
        </div>

        <div class="etd-panel etd-compare-panel" data-compare-sync="products">
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($d['products'] ?? [] as $product)
                            <tr>
                                <td class="etd-col-product">{{ $product['name'] }}</td>
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

        <p class="etd-compare-block-label" data-compare-sync="recover-label">Recoverable sale</p>

        @include('ecom_tracker.partials.compare-recoverable-summary', ['panels' => $recoverablePanels])

        <p class="etd-compare-block-label" data-compare-sync="acq-label">Acquisition &amp; audience</p>

        <div class="etd-panel etd-compare-panel" data-compare-sync="devices">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Device &amp; browser</h3>
            </div>
            @include('ecom_tracker.partials.device-browser-breakdown', [
                'devices' => $d['devices'] ?? [],
                'readOnly' => true,
            ])
        </div>

        <div class="etd-panel etd-compare-panel" data-compare-sync="traffic">
            <div class="etd-panel-head">
                <h3 class="etd-panel-title">Traffic sources</h3>
            </div>
            @include('ecom_tracker.partials.traffic-sources-table', [
                'rows' => $d['traffic_sources'] ?? [],
                'readOnly' => true,
            ])
        </div>

        <div class="etd-compare-audience-note" data-compare-sync="audience">
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
