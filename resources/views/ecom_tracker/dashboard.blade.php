@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@section('content')
@php
    $d = $dashboard;
    $period = $page['period'];
    $dateFrom = $filters['date_from'] ?? '';
    $dateTo = $filters['date_to'] ?? '';
    $queryParams = $page['queryParams'];
    $exportUrl = $page['exportUrl'];
    $detailLink = $page['detailLink'];
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

    $activePreset = match ($period) {
        'yesterday', '7d', '30d', '90d', 'custom' => $period,
        default => '24h',
    };

    $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d', '90d'], true) ? $period : '24h';
    $baseQuery = request()->except([
        'date_from', 'date_to', 'period',
        'search', 'category', 'color', 'size', 'sort_by', 'activity',
        'has_purchases', 'has_views', 'has_adds', 'event_scenario',
    ]);
    $presetUrl = fn (string $preset) => route('admin.ecom-tracker.dashboard', array_merge($baseQuery, ['period' => $preset]));
@endphp

<div id="ecom-tracker-dashboard-content" class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.dashboard'),
        'resetUrl' => route('admin.ecom-tracker.dashboard'),
        'preservePeriodParams' => true,
        'showSessionFilters' => true,
        'sessionFiltersHeading' => 'Sessions & audience',
        'period' => $period,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
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
            <div class="etd-page-header-left">
                <h1 class="etd-page-title">Store performance</h1>
                <span class="etd-header-sep" aria-hidden="true">·</span>
                <span class="etd-page-range">{{ $d['range']['label'] }}</span>
                <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                <div class="etd-page-meta">
                    @include('ecom_tracker.partials.timezone-notice')
                    @include('ecom_tracker.partials.analytics-cache-notice', ['analytics_cache' => $d['analytics_cache'] ?? null])
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                    <a href="{{ $presetUrl('24h') }}" class="etd-segmented-btn {{ $activePreset === '24h' ? 'active' : '' }} no-underline" aria-label="{{ \App\Support\TrackerTime::todayPresetLabel() }}">{{ \App\Support\TrackerTime::todayPresetButtonLabel() }}</a>
                    <a href="{{ $presetUrl('yesterday') }}" class="etd-segmented-btn {{ $activePreset === 'yesterday' ? 'active' : '' }} no-underline" aria-label="{{ \App\Support\TrackerTime::yesterdayPresetLabel() }}">{{ \App\Support\TrackerTime::yesterdayPresetButtonLabel() }}</a>
                    <a href="{{ $presetUrl('7d') }}" class="etd-segmented-btn {{ $activePreset === '7d' ? 'active' : '' }} no-underline" aria-label="Last 7 days">7d</a>
                    <a href="{{ $presetUrl('30d') }}" class="etd-segmented-btn {{ $activePreset === '30d' ? 'active' : '' }} no-underline" aria-label="Last 30 days">30d</a>
                    <a href="{{ $presetUrl('90d') }}" class="etd-segmented-btn {{ $activePreset === '90d' ? 'active' : '' }} no-underline" aria-label="Last 90 days">90d</a>
                    <button type="button"
                            class="etd-segmented-btn"
                            :class="{ 'active': presetKey === 'custom' }"
                            aria-label="Custom date range"
                            @click="toggleCustom()">Custom</button>
                </div>

                <div class="etd-header-actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-tracker.dashboard'),
                        'active' => count(request()->query()) > 0,
                    ])
                    <button type="button" @click="drawerOpen = true" class="etd-header-btn etd-header-btn--icon {{ $hasActiveFilters ? 'etd-header-btn--filtered' : '' }}" aria-label="Filters">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                        <span class="etd-header-btn-text">Filters</span>
                        @if ($hasActiveFilters)
                            <span class="etd-header-btn-badge">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                    <a href="{{ $exportUrl }}" class="etd-header-btn etd-header-btn--primary no-underline" title="Export Excel">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l4-4m-4 4L8 11M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"/></svg>
                        <span class="etd-header-btn-text">Export</span>
                    </a>
                </div>
            </div>

            <div x-show="presetKey === 'custom'"
                 x-collapse
                 x-effect="if (presetKey === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
                 class="etd-custom-dates etd-custom-dates--inline etd-date-range"
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

        @if ($hasActiveFilters)
            <p class="etd-filter-active-note etd-filter-active-note--compact">Filters applied — open Filters to change or reset.</p>
        @endif

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
            @endforeach

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

    <div class="mb-3">
        <div class="etd-panel" id="trend">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Shopper journey over time</h2>
            </div>
            <div class="etd-chart-wrap xl">
                <canvas id="etdTrendChart"></canvas>
            </div>
        </div>
    </div>

    <h2 class="etd-section-title"><span class="etd-section-num">01</span> Merchandising decisions</h2>
    <p class="etd-section-note">Where traffic goes vs where money is made — use to reorder homepage, deprioritize dead categories, and flag products that get eyeballs but not carts.</p>

    <div class="etd-grid-4-8 mb-3">
        <div class="etd-panel" id="categories">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Category performance</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('categories')])
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="etd-num">Views</th>
                        <th class="etd-num">Adds</th>
                        <th class="etd-num">Conversion</th>
                        <th>
                            @include('ecom_tracker.partials.signal-header')
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['categories'] as $category)
                        <tr>
                            <td>{{ $category['name'] }}</td>
                            <td class="etd-num">{{ number_format($category['views']) }}</td>
                            <td class="etd-num">{{ $category['add_rate'] }}%</td>
                            <td class="etd-num">{{ $category['conversion_rate'] }}%</td>
                            <td><span class="etd-badge {{ $category['signal'] }}">{{ $category['signal_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-400">No category views in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="etd-panel" id="products">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Product & variant performance</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('products')])
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table etd-table--product-catalog">
                <thead>
                    <tr>
                        <th class="etd-col-product">Product</th>
                        <th class="etd-num">Views</th>
                        <th class="etd-num">
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => 'Adds',
                                'tip' => 'Add to cart',
                                'align' => 'right',
                            ])
                        </th>
                        <th class="etd-num">Purchases</th>
                        <th class="etd-num">Qty</th>
                        <th class="etd-num">Sale</th>
                        <th class="etd-num">Variants</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['products'] as $product)
                        <tr>
                            <td class="etd-col-product">
                                {{ $product['name'] }}
                                <div class="etd-subtle">{{ $product['code'] }}</div>
                            </td>
                            <td class="etd-num">{{ number_format($product['views']) }}</td>
                            <td class="etd-num">{{ number_format($product['adds']) }}</td>
                            <td class="etd-num">{{ number_format($product['purchases']) }}</td>
                            <td class="etd-num">{{ number_format($product['qty'] ?? 0) }}</td>
                            <td class="etd-num">
                                £{{ number_format($product['revenue'], 2) }}
                                <div class="etd-mini-bar"><div style="width: {{ $product['revenue_bar_percent'] }}%"></div></div>
                            </td>
                            <td class="etd-num">{{ number_format($product['variant_count'] ?? count($product['variants'] ?? [])) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-slate-400">No product activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <h2 class="etd-section-title"><span class="etd-section-num">02</span> Recoverable sale</h2>
    <p class="etd-section-note">Sessions that dropped off at each funnel step — review for retargeting.</p>

    <div class="etd-grid-3 etd-grid-3--abandonment mb-3">
        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'cart-abandon',
            'title' => 'Cart abandoned',
            'subtitle' => 'Added to cart but didn\'t begin checkout.',
            'dataKey' => 'cart_abandonment',
            'detailSection' => 'cart-abandonment',
            'detailLabel' => 'Last item',
            'valueLabel' => 'Cart value',
            'emptyMessage' => 'No cart abandonment in this period.',
        ])

        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'begin-checkout-abandon',
            'title' => 'Begin checkout abandoned',
            'subtitle' => 'Began checkout but didn\'t proceed.',
            'dataKey' => 'begin_checkout_abandonment',
            'detailSection' => 'begin-checkout-abandonment',
            'detailLabel' => 'Coupon',
            'valueLabel' => 'Total',
            'emptyMessage' => 'No begin checkout abandonment in this period.',
        ])

        @include('ecom_tracker.partials.abandonment-panel', [
            'd' => $d,
            'detailLink' => $detailLink,
            'panelId' => 'proceed-checkout-abandon',
            'title' => 'Proceed checkout abandoned',
            'subtitle' => 'Proceeded to checkout but didn\'t complete payment.',
            'dataKey' => 'proceed_checkout_abandonment',
            'detailSection' => 'proceed-checkout-abandonment',
            'detailLabel' => 'Coupon',
            'valueLabel' => 'Total',
            'emptyMessage' => 'No proceed checkout abandonment in this period.',
        ])
    </div>

    <h2 class="etd-section-title"><span class="etd-section-num">03</span> Acquisition &amp; audience</h2>
    <p class="etd-section-note">Where traffic comes from and which device/market needs UX attention.</p>

    <div class="etd-grid-5-7 mb-3">
        <div class="etd-panel" id="device">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Device &amp; browser</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('devices')])
            </div>
            <div class="etd-two-donut">
                <div class="etd-donut-block">
                    <div class="etd-chart-wrap sm"><canvas id="etdDeviceChart"></canvas></div>
                    <div class="etd-donut-cap">by device</div>
                </div>
                <div class="etd-donut-block">
                    <div class="etd-chart-wrap sm"><canvas id="etdLoginChart"></canvas></div>
                    <div class="etd-donut-cap">guest vs logged-in</div>
                </div>
            </div>
            <ul class="etd-legend">
                @php $deviceColors = ['#1D9E75', '#f59e0b', '#64748b', '#3b82f6', '#8b5cf6']; @endphp
                @foreach ($d['devices']['legend'] as $index => $item)
                    <li>
                        <span>
                            <span class="etd-swatch" style="background: {{ $deviceColors[$index % count($deviceColors)] }}"></span>
                            {{ $item['label'] }}
                        </span>
                        <span>{{ $item['share'] }}% · conv. {{ $item['conversion_rate'] }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="etd-panel" id="traffic">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Traffic sources</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('traffic-sources')])
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Medium</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Conversion</th>
                        <th class="etd-num">Sale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['traffic_sources'] as $source)
                        <tr>
                            <td>{{ $source['source'] }}</td>
                            <td>{{ $source['medium'] }}</td>
                            <td class="etd-num">{{ number_format($source['sessions']) }}</td>
                            <td class="etd-num">{{ $source['conversion_rate'] }}%</td>
                            <td class="etd-num">£{{ number_format($source['revenue'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-400">No traffic source data in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="etd-grid-2">
        <div class="etd-panel" id="geo">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Geography</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('geography')])
            </div>
            <div class="etd-table-scroll etd-table-scroll--narrow etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Sale</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['geography'] as $geo)
                        <tr>
                            <td>{{ $geo['location'] }}</td>
                            <td class="etd-num">{{ number_format($geo['sessions']) }}</td>
                            <td class="etd-num">£{{ number_format($geo['revenue'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-slate-400">No geography data in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="etd-panel" id="engagement">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Engagement quality</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('engagement')])
            </div>
            <div class="etd-chart-wrap sm">
                <canvas id="etdDwellChart"></canvas>
            </div>
            <p class="etd-section-note" style="margin-top: 0.75rem;">
                Buyers vs non-buyers average active time on category and product pages.
            </p>
        </div>
    </div>
</div>

<script>
    window.ecomTrackerDashboardData = @json($d['chart_payload']);
</script>
@endsection
