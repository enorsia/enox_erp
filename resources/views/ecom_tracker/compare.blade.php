@extends('layouts.app')

@section('title', 'Compare Store Performance')

@section('content')
<script>
    window.ecomTrackerCompareData = @json($compare['chartData']);
</script>

<div id="ecom-tracker-compare-content" class="etd-page etd-compare-page">
    <header class="etd-page-header">
        <div class="etd-page-header-bar etd-compare-page-header-bar">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">{{ $compare['header']['pageTitle'] }}</h1>
                <div class="etd-page-header-sub etd-compare-page-header-sub">
                    <span class="etd-compare-page-mode">{{ $compare['header']['modeLabel'] }}</span>
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <p class="etd-compare-page-subtitle">
                        <span class="etd-compare-page-subtitle-range">{{ $compare['header']['rangeA'] }}</span>
                        <span class="etd-compare-page-subtitle-vs">vs</span>
                        <span class="etd-compare-page-subtitle-range">{{ $compare['header']['rangeB'] }}</span>
                    </p>
                    <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                    <div class="etd-page-meta">
                        <span class="etd-meta-item">{{ $compare['header']['timezone'] }}</span>
                    </div>
                </div>
            </div>
            <div class="etd-page-header-right">
                <div class="etd-header-actions etd-compare-header-actions">
                    <a href="{{ $compare['backUrl'] }}" class="etd-header-btn etd-header-btn--icon no-underline">
                        <span class="etd-header-btn-text">Back</span>
                    </a>
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => $compare['reset']['url'],
                        'count' => $compare['reset']['activeCount'],
                    ])
                    <button type="button" id="{{ $compare['printButtonId'] }}" class="etd-header-btn etd-header-btn--icon">
                        <span class="etd-header-btn-text">Print</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <section class="etd-compare-exec etd-panel etd-print-unit"
             aria-label="Executive comparison summary"
             x-data="{ execOpen: true }">
        <button type="button"
                class="etd-compare-exec__toggle"
                @click="execOpen = !execOpen; $nextTick(() => window.dispatchEvent(new Event('resize')))"
                :aria-expanded="execOpen.toString()"
                aria-controls="etdCompareExecBody">
            <span class="etd-compare-exec__toggle-main">
                <span class="etd-panel-title etd-compare-exec__title">Executive summary</span>
                <span class="etd-compare-exec__subtitle" x-show="!execOpen" x-cloak>
                    <span class="etd-compare-exec__period-range">{{ $compare['header']['rangeA'] }}</span>
                    <span class="etd-compare-exec__period-vs" aria-hidden="true">vs</span>
                    <span class="etd-compare-exec__period-range">{{ $compare['header']['rangeB'] }}</span>
                </span>
            </span>
            <span class="etd-compare-exec__chevron" :class="{ 'is-open': execOpen }" aria-hidden="true">
                <svg class="etd-compare-exec__chevron-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        </button>

        <div id="etdCompareExecBody" class="etd-compare-exec__body" x-show="execOpen" x-collapse>
            <div class="etd-compare-exec__content">
                <div class="etd-compare-exec__header-group">
                    <h2 class="etd-compare-exec__print-heading">Executive summary</h2>
                    <p class="etd-compare-exec__periods-banner">
                        <span class="etd-compare-exec__periods">
                            <span class="etd-compare-exec__period">
                                <span class="etd-compare-exec__period-badge">Period A</span>
                                <span class="etd-compare-exec__period-range">{{ $compare['header']['rangeA'] }}</span>
                            </span>
                            <span class="etd-compare-exec__period-vs" aria-hidden="true">vs</span>
                            <span class="etd-compare-exec__period">
                                <span class="etd-compare-exec__period-badge">Period B</span>
                                <span class="etd-compare-exec__period-range">{{ $compare['header']['rangeB'] }}</span>
                            </span>
                        </span>
                    </p>
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
                            @foreach ($compare['executive'] as $row)
                                <tr class="etd-compare-metric-row">
                                    <td class="etd-compare-metric-row__label"><span class="etd-compare-metric-row__label-text">{{ $row['label'] }}</span></td>
                                    <td class="etd-num">{{ $row['a'] }}</td>
                                    <td class="etd-num">{{ $row['b'] }}</td>
                                    <td class="etd-num"><span class="etd-compare-delta-badge {{ $row['changeClass'] }}">{{ $row['change'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="etd-compare-grid">
        @foreach ($compare['columns'] as $column)
            <div class="etd-compare-column {{ $column['modifier'] }}"
                 x-data="{ filtersOpen: false, toggleFilters() { this.filtersOpen = !this.filtersOpen } }">
                <div class="etd-compare-column__header" data-compare-sync="header">
                    <div class="etd-compare-column__header-row">
                        <div class="etd-compare-column__title-wrap">
                            <span class="etd-compare-column__badge">{{ $column['badge'] }}</span>
                            <span class="etd-compare-column__range">{{ $column['rangeLabel'] }}</span>
                        </div>
                        <div class="etd-compare-column__controls etd-print-hide">
                            <div class="etd-compare-column__controls-scroll">
                                <div class="etd-compare-period-nav">
                                    @if ($column['periodNav']['prevUrl'])
                                        <a href="{{ $column['periodNav']['prevUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Previous period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></a>
                                    @endif
                                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                                        @foreach ($column['periodNav']['presets'] as $preset)
                                            <a href="{{ $preset['url'] }}" class="etd-segmented-btn {{ $preset['active'] ? 'active' : '' }} no-underline">{{ $preset['label'] }}</a>
                                        @endforeach
                                    </div>
                                    @if ($column['periodNav']['nextUrl'])
                                        <a href="{{ $column['periodNav']['nextUrl'] }}" class="etd-segmented-btn etd-date-nav-btn no-underline" aria-label="Next period"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></a>
                                    @endif
                                </div>
                            </div>
                            <div class="etd-compare-column__filter-wrap">
                                <div class="etd-compare-column__filter-controls">
                                    @php $columnFilterCount = (int) ($column['filter']['activeCount'] ?? 0); @endphp
                                    @if ($columnFilterCount > 0)
                                        <a href="{{ $column['filter']['resetUrl'] }}"
                                           class="etd-compare-column__filter-reset etd-compare-column__filter-reset--active no-underline"
                                           aria-label="Reset {{ $columnFilterCount }} {{ $column['badge'] }} filters"
                                           title="Reset {{ $columnFilterCount }} filters">
                                            <svg class="etd-compare-column__filter-reset-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            <span class="etd-compare-column__filter-reset-badge">{{ $columnFilterCount }}</span>
                                        </a>
                                    @else
                                        <span class="etd-compare-column__filter-reset" aria-hidden="true">
                                            <svg class="etd-compare-column__filter-reset-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </span>
                                    @endif
                                    <div class="etd-compare-column__filter-toggle">
                                        <span class="etd-compare-column__filter-label">Filters</span>
                                        <button type="button" class="toggle-track" :class="{ 'on': filtersOpen }" @click.stop="toggleFilters()" aria-label="Toggle filters">
                                            <span class="toggle-thumb"></span>
                                        </button>
                                    </div>
                                </div>
                                <form method="GET"
                                      action="{{ $column['filter']['action'] }}"
                                      class="etd-compare-column-filters"
                                      x-show="filtersOpen"
                                      x-cloak
                                      @click.stop>
                                    @foreach ($column['filter']['hidden'] as $hidden)
                                        <input type="hidden" name="{{ $hidden['name'] }}" value="{{ $hidden['value'] }}">
                                    @endforeach
                                    <input type="hidden" name="{{ $column['filter']['prefix'] }}period" value="{{ $column['filter']['period'] }}">
                                    @if ($column['filter']['period'] === 'custom')
                                        <input type="hidden" name="{{ $column['filter']['prefix'] }}date_from" value="{{ $column['filter']['dateFrom'] }}">
                                        <input type="hidden" name="{{ $column['filter']['prefix'] }}date_to" value="{{ $column['filter']['dateTo'] }}">
                                    @endif
                                    <div class="etd-compare-column-filters__body">
                                        <label class="etd-filter-compact-field">
                                            <span class="etd-filter-compact-label">Device</span>
                                            <select name="{{ $column['filter']['prefix'] }}device_type" class="f-input w-full">
                                                @foreach ($column['filter']['deviceOptions'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="etd-filter-compact-field">
                                            <span class="etd-filter-compact-label">Logged in</span>
                                            <select name="{{ $column['filter']['prefix'] }}logged_in" class="f-input w-full">
                                                @foreach ($column['filter']['loggedInOptions'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="etd-filter-compact-field">
                                            <span class="etd-filter-compact-label">Has order</span>
                                            <select name="{{ $column['filter']['prefix'] }}has_order" class="f-input w-full">
                                                @foreach ($column['filter']['hasOrderOptions'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="etd-filter-compact-field">
                                            <span class="etd-filter-compact-label">UTM source</span>
                                            <select name="{{ $column['filter']['prefix'] }}utm_source" class="f-input w-full">
                                                @foreach ($column['filter']['utmSourceOptions'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="etd-filter-compact-field">
                                            <span class="etd-filter-compact-label">UTM medium</span>
                                            <select name="{{ $column['filter']['prefix'] }}utm_medium" class="f-input w-full">
                                                @foreach ($column['filter']['utmMediumOptions'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected($option['selected'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                    <div class="etd-compare-column-filters__actions">
                                        <button type="submit" class="etd-header-btn etd-header-btn--primary">Apply filters</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="etd-compare-column__body">
                    <div class="etd-kpi-panel etd-compare-kpi-panel" data-compare-sync="kpis">
                        <div class="etd-kpi-groups">
                            @foreach ($column['kpiGroups'] as $group)
                                @include('ecom_tracker.partials.kpi-metric-group', [
                                    'title' => $group['title'],
                                    'modifier' => $group['modifier'],
                                    'cols' => $group['cols'],
                                    'metrics' => $group['metrics'],
                                ])
                            @endforeach
                        </div>
                    </div>

                    <div class="etd-panel etd-compare-panel etd-compare-panel--trend" data-compare-sync="trend">
                        <div class="etd-panel-head"><h3 class="etd-panel-title">Shopper journey over time</h3></div>
                        <div class="etd-trend-chart">
                            <div class="etd-trend-legend" id="{{ $column['chart']['legendId'] }}" hidden></div>
                            <div class="etd-trend-chart-scroll" id="{{ $column['chart']['scrollId'] }}">
                                <div class="etd-chart-wrap xl etd-chart-wrap--trend" id="{{ $column['chart']['wrapId'] }}">
                                    <canvas id="{{ $column['chart']['canvasId'] }}"></canvas>
                                </div>
                            </div>
                            <p class="etd-trend-chart-scroll-hint" id="{{ $column['chart']['hintId'] }}" hidden>Swipe horizontally to see all dates</p>
                        </div>
                    </div>

                    <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="merch-label">Merchandising</p>

                    <div class="etd-panel etd-compare-panel etd-compare-detail-only etd-print-unit" data-compare-sync="categories">
                        <div class="etd-panel-head">
                            <div>
                                <h2 class="etd-panel-title">Category performance</h2>
                                @if (($column['merchandising']['categoryTotals']['category_count'] ?? 0) > 0)
                                    <p class="etd-panel-subtitle text-slate-500 dark:text-slate-400 text-sm mt-1 mb-0">
                                        {{ number_format($column['merchandising']['categoryTotals']['category_views']) }} category views · {{ number_format($column['merchandising']['categoryTotals']['product_views']) }} product views across {{ number_format($column['merchandising']['categoryTotals']['category_count']) }} categories
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="etd-table-scroll etd-table-scroll--fixed">
                            @include('ecom_tracker.partials.category-performance-table', [
                                'departments' => $column['merchandising']['departments'],
                                'showCurrency' => true,
                            ])
                        </div>
                    </div>

                    <div class="etd-panel etd-compare-panel etd-compare-detail-only etd-print-unit" data-compare-sync="products">
                        <div class="etd-panel-head">
                            <div>
                                <h2 class="etd-panel-title">Product performance</h2>
                                @if (($column['merchandising']['productTotals']['product_count'] ?? 0) > 0)
                                    <p class="etd-panel-subtitle text-slate-500 dark:text-slate-400 text-sm mt-1 mb-0">
                                        {{ number_format($column['merchandising']['productTotals']['views']) }} views across {{ number_format($column['merchandising']['productTotals']['product_count']) }} products
                                        @if ($column['merchandising']['productTotals']['product_count'] > count($column['merchandising']['products']))
                                            · showing top {{ count($column['merchandising']['products']) }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="etd-table-scroll etd-table-scroll--fixed">
                            @include('ecom_tracker.partials.product-performance-table', [
                                'products' => $column['merchandising']['products'],
                            ])
                        </div>
                    </div>

                    <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="recover-label">Recoverable sale</p>

                    <div class="etd-compare-detail-only">
                        @include('ecom_tracker.partials.compare-recoverable-summary', [
                            'panels' => $column['recoverable'],
                        ])
                    </div>

                    <p class="etd-compare-block-label etd-compare-detail-only" data-compare-sync="acq-label">Acquisition &amp; audience</p>

                    <div class="etd-panel etd-panel--acquisition etd-panel--device-browser-full etd-compare-panel etd-compare-detail-only etd-print-unit" data-compare-sync="devices">
                        <div class="etd-panel-head etd-panel-head--device-browser">
                            <h2 class="etd-panel-title">Device &amp; browser</h2>
                            <div class="etd-panel-head-actions">
                                @include('ecom_tracker.partials.view-details-button', [
                                    'detailUrl' => $column['acquisition']['devicesDetailUrl'],
                                ])
                            </div>
                        </div>
                        @include('ecom_tracker.partials.device-browser-breakdown', [
                            'devices' => $column['acquisition']['devices'],
                        ])
                    </div>

                    <div class="etd-panel etd-compare-panel etd-compare-detail-only etd-print-unit" data-compare-sync="traffic">
                        <div class="etd-panel-head">
                            <h2 class="etd-panel-title">Traffic sources</h2>
                            <div class="etd-panel-head-actions">
                                @include('ecom_tracker.partials.view-details-button', [
                                    'detailUrl' => $column['acquisition']['trafficDetailUrl'],
                                ])
                            </div>
                        </div>
                        @include('ecom_tracker.partials.traffic-sources-table', [
                            'rows' => $column['acquisition']['traffic'],
                        ])
                    </div>

                    @include('ecom_tracker.partials.acquisition-insights', [
                        'distribution' => $column['acquisition']['durations'],
                        'newReturning' => $column['acquisition']['newReturning'],
                        'newReturningChartId' => $column['chart']['newReturningCanvasId'],
                        'forCompare' => true,
                        'audienceDetailUrl' => $column['acquisition']['audienceDetailUrl'],
                    ])
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
