@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@section('content')
@php
    $d = $dashboard;
    $period = $filters['period'] ?? '24h';
    $back = urlencode(request()->fullUrl());
    $queryParams = request()->only(['period', 'date_from', 'date_to', 'device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium']);
    $exportQuery = array_filter(array_merge($queryParams, ['period' => $period]), fn ($value) => filled($value));
    $exportUrl = route('admin.ecom-tracker.dashboard.export', $exportQuery);
    $detailLink = fn (string $section) => route('admin.ecom-tracker.dashboard.details', $section).'?'.http_build_query(array_merge($queryParams, ['back' => $back]));
    $hasActiveFilters = ($activeFilterCount ?? 0) > 0;
@endphp

<div id="ecom-tracker-dashboard-content" class="etd-page" x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">
    @include('ecom_tracker.partials.filter-drawer', [
        'action' => route('admin.ecom-tracker.dashboard'),
        'resetUrl' => route('admin.ecom-tracker.dashboard'),
        'showDashboardFilters' => true,
        'showSessionFilters' => true,
        'period' => $period,
        'dateFrom' => $filters['date_from'] ?? '',
        'dateTo' => $filters['date_to'] ?? '',
    ])

    <header class="etd-page-header">
        <div class="etd-page-header-bar"
             x-data="{
                period: '{{ $period }}',
                dateFrom: '{{ $filters['date_from'] ?? '' }}',
                dateTo: '{{ $filters['date_to'] ?? '' }}',
                apply(period) {
                    this.period = period;
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', period);
                    if (period !== 'custom') {
                        url.searchParams.delete('date_from');
                        url.searchParams.delete('date_to');
                    }
                    window.location.href = url.toString();
                },
                applyCustom() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', 'custom');
                    url.searchParams.set('date_from', this.dateFrom);
                    url.searchParams.set('date_to', this.dateTo);
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
                    @foreach (['24h' => '24 hours', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $periodKey => $periodLabel)
                        <button type="button" class="etd-segmented-btn {{ $period === $periodKey ? 'active' : '' }}" aria-label="{{ $periodLabel }}" @click="apply('{{ $periodKey }}')">{{ $periodKey }}</button>
                    @endforeach
                    <button type="button" class="etd-segmented-btn {{ $period === 'custom' ? 'active' : '' }}" aria-label="Custom date range" @click="period = 'custom'">Custom</button>
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

            <div x-show="period === 'custom'"
                 x-collapse
                 x-effect="if (period === 'custom') { $nextTick(() => window.refreshEtdFilterControls?.($el)) }"
                 class="etd-custom-dates etd-custom-dates--inline etd-date-range"
                 data-etd-date-range>
                <input type="text"
                       x-model="dateFrom"
                       data-range="from"
                       data-default="{{ $filters['date_from'] ?? '' }}"
                       value="{{ $filters['date_from'] ?? '' }}"
                       placeholder="From date"
                       readonly
                       class="etd-flatpickr-date f-input etd-date-input"
                       aria-label="From date">
                <span class="etd-custom-dates-sep">–</span>
                <input type="text"
                       x-model="dateTo"
                       data-range="to"
                       data-default="{{ $filters['date_to'] ?? '' }}"
                       value="{{ $filters['date_to'] ?? '' }}"
                       placeholder="To date"
                       readonly
                       class="etd-flatpickr-date f-input etd-date-input"
                       aria-label="To date">
                <button type="button" class="etd-header-btn etd-header-btn--primary etd-pill-apply" @click="applyCustom()">Apply</button>
            </div>
        </div>

        @if ($d['has_session_filters'] ?? false)
            <p class="etd-filter-active-note etd-filter-active-note--compact">Filtered sessions active — open Filters to adjust.</p>
        @endif
    </header>

    <div class="etd-kpi-panel mb-5">
        <div class="etd-kpi-groups">
            @foreach ([
                ['title' => 'Audience & engagement', 'items' => array_slice($d['kpis'], 0, 4), 'cols' => 4],
                ['title' => 'Sale & conversion', 'items' => array_slice($d['kpis'], 4, 2), 'cols' => 2],
                ['title' => 'Funnel drop-off', 'items' => array_slice($d['kpis'], 6, 3), 'cols' => 3],
            ] as $group)
                <div class="etd-kpi-group etd-kpi-group--{{ $group['cols'] }}">
                    <p class="etd-kpi-section-label">{{ $group['title'] }}</p>
                    <div class="etd-kpi-group-grid">
                        @foreach ($group['items'] as $kpi)
                            <div class="etd-kpi etd-kpi--compact">
                                <div class="etd-kpi-label">{{ $kpi['label'] }}</div>
                                <div class="etd-kpi-value">{{ $kpi['formatted'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="etd-grid-2 mb-3">
        <div class="etd-panel" id="funnel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Conversion funnel</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('funnel')])
            </div>
            <div class="etd-funnel">
                @foreach ($d['funnel'] as $row)
                    <div class="etd-funnel-row">
                        <div class="etd-funnel-stage">{{ $row['stage'] }}</div>
                        <div class="etd-funnel-track">
                            <div class="etd-funnel-fill" style="width: {{ max(8, $row['percent_of_top']) }}%">
                                {{ number_format($row['count']) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Sessions &amp; conversion trend</h2>
                @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink('trend')])
            </div>
            <div class="etd-chart-wrap">
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
