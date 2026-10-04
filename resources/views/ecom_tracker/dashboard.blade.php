@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@section('content')

<div id="ecom-tracker-dashboard-content" class="etd-page">

@include('ecom_tracker.filter-drawer')

<header class="etd-page-header">
    <div class="etd-page-header-bar">
        <div class="etd-page-header-main">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">Store performance</h1>
                <div class="etd-page-meta">
                    <span class="etd-meta-item">All times (store timezone)</span>
                    <span class="etd-meta-item text-slate-500">Preview · sample data</span>
                </div>
                <p class="etd-page-range">{{ match ($data['period']) { 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'custom' => 'Custom', default => 'Today' } }}</p>
            </div>
        </div>
        <div class="etd-page-header-toolbar">
            <div class="etd-header-toolbar-row">
                <a href="{{ route('admin.ecom-tracker.dashboard.compare') }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <span class="etd-header-btn-text">Compare</span>
                </a>
                <a href="{{ route('admin.ecom-activity.index') }}" class="etd-header-btn etd-header-btn--icon no-underline">
                    <span class="etd-header-btn-text">Activity</span>
                </a>
                <div class="etd-header-period-nav etd-print-hide">
                    <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg></span>
                    <div class="etd-segmented etd-segmented--compact" role="group" aria-label="Date range">
                        <a href="{{ route('admin.ecom-tracker.dashboard', ['period' => '24h']) }}" class="etd-segmented-btn {{ $data['period'] === '24h' ? 'active' : '' }} no-underline">Today</a>
                        <a href="{{ route('admin.ecom-tracker.dashboard', ['period' => 'yesterday']) }}" class="etd-segmented-btn {{ $data['period'] === 'yesterday' ? 'active' : '' }} no-underline">Yesterday</a>
                        <a href="{{ route('admin.ecom-tracker.dashboard', ['period' => '7d']) }}" class="etd-segmented-btn {{ $data['period'] === '7d' ? 'active' : '' }} no-underline">7d</a>
                        <a href="{{ route('admin.ecom-tracker.dashboard', ['period' => '30d']) }}" class="etd-segmented-btn {{ $data['period'] === '30d' ? 'active' : '' }} no-underline">30d</a>
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
                    <span class="etd-segmented-btn etd-date-nav-btn is-disabled" aria-disabled="true"><svg class="etd-date-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg></span>
                </div>
                <div class="etd-header-toolbar-icon-group etd-header-toolbar-icon-group--actions">
                    @include('ecom_tracker.partials.header-reset-button', [
                        'url' => route('admin.ecom-tracker.dashboard'),
                        'active' => false,
                    ])
                    @include('ecom_tracker.partials.header-print-button')
                    <button type="button" id="ecom-dashboard-filter-open" class="etd-header-btn etd-header-btn--icon-only js-ecom-dashboard-filter-open" aria-label="Filters" aria-expanded="false">
                        <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="etd-kpi-panel mb-5 etd-print-unit" data-etd-dashboard-demo="kpis">
    <div class="etd-kpi-groups">
        <div class="etd-kpi-group etd-kpi-group--4 etd-kpi-group--audience">
            <p class="etd-kpi-section-label">Audience &amp; engagement</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Unique visitors</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">704</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+15.0%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">612</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Sessions</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">847</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+10.3%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">768</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Total stay time</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">4d 11h</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+19.3%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">3d 18h</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Avg stay time</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">3m 42s</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+12.1%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">3m 18s</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="etd-kpi-group etd-kpi-group--2 etd-kpi-group--sale">
            <p class="etd-kpi-section-label">Sale &amp; conversion</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Items sold</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">38</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+22.6%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">31</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Sale amount</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">£2,847.50</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+18.2%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">£2,410.00</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="etd-kpi-group etd-kpi-group--4 etd-kpi-group--funnel">
            <p class="etd-kpi-section-label">Funnel drop-off</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Cart drop</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">7.3% / 62</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--bad etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+14.8%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">6.4% / 54</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Checkout drop</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">3.3% / 28</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--down"><span class="etd-kpi-compare__arrow" aria-hidden="true">↓</span><span class="etd-kpi-compare__pct">-9.7%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">3.8% / 31</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Proceed drop</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value">1.4% / 12</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--down"><span class="etd-kpi-compare__arrow" aria-hidden="true">↓</span><span class="etd-kpi-compare__pct">-14.3%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">1.6% / 14</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Payments</span></span>
                    <div class="etd-kpi-value-stack">
                        <div class="etd-kpi-value etd-kpi-value--success">4.5% / 38</div>
                        <div class="etd-kpi-compare">
                            <div class="etd-kpi-compare__main">
                                <span class="etd-kpi-compare__change etd-kpi-compare__change--good etd-kpi-compare__change--up"><span class="etd-kpi-compare__arrow" aria-hidden="true">↑</span><span class="etd-kpi-compare__pct">+18.8%</span></span>
                                <span class="etd-kpi-compare__divider" aria-hidden="true"></span>
                                <span class="etd-kpi-compare__prev">3.9% / 32</span>
                            </div>
                            <span class="etd-kpi-compare__period">Yesterday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        </div>
        <div class="etd-section-print-body">
    <div class="etd-grid-4-8 mb-3">
        <div class="etd-panel etd-print-unit" id="categories">
            <div class="etd-panel-head">
                <div>
                    <h2 class="etd-panel-title">Category performance</h2>
                    <p class="etd-panel-subtitle text-slate-500 dark:text-slate-400 text-sm mt-1 mb-0">1,284 category views · 3,902 product views across 18 categories</p>
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
                <div class="etd-category-departments">
                <table class="etd-table etd-table--categories etd-table--catalog etd-table--performance-metrics w-full">
                    <thead>
                        <tr>
                            <th class="etd-col-category">Department / Category</th>
                            <th class="etd-num etd-col-metric">C view</th>
                            <th class="etd-num etd-col-metric">P view</th>
                            <th class="etd-num etd-col-metric">Adds</th>
                            <th class="etd-num etd-col-metric">Proceed</th>
                            <th class="etd-num etd-col-metric">Qty</th>
                            <th class="etd-num etd-col-metric">Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="etd-catalog-product-row etd-category-dept-row">
                            <td class="etd-col-category"><span class="font-medium">Living room</span></td>
                            <td class="etd-num etd-col-metric">412</td>
                            <td class="etd-num etd-col-metric">1,240</td>
                            <td class="etd-num etd-col-metric">86</td>
                            <td class="etd-num etd-col-metric">34</td>
                            <td class="etd-num etd-col-metric">14</td>
                            <td class="etd-num etd-col-metric">£1,124.00<div class="etd-mini-bar"><div style="width: 100%"></div></div></td>
                        </tr>
                        <tr class="etd-category-child-row">
                            <td class="etd-col-category etd-category-child-label">Sofas &amp; sectionals</td>
                            <td class="etd-num etd-col-metric">188</td>
                            <td class="etd-num etd-col-metric">520</td>
                            <td class="etd-num etd-col-metric">38</td>
                            <td class="etd-num etd-col-metric">16</td>
                            <td class="etd-num etd-col-metric">6</td>
                            <td class="etd-num etd-col-metric">£648.00<div class="etd-mini-bar"><div style="width: 58%"></div></div></td>
                        </tr>
                        <tr class="etd-catalog-product-row etd-category-dept-row">
                            <td class="etd-col-category"><span class="font-medium">Bedroom</span></td>
                            <td class="etd-num etd-col-metric">296</td>
                            <td class="etd-num etd-col-metric">892</td>
                            <td class="etd-num etd-col-metric">52</td>
                            <td class="etd-num etd-col-metric">22</td>
                            <td class="etd-num etd-col-metric">9</td>
                            <td class="etd-num etd-col-metric">£742.50<div class="etd-mini-bar"><div style="width: 66%"></div></div></td>
                        </tr>
                        <tr class="etd-catalog-product-row etd-category-dept-row">
                            <td class="etd-col-category"><span class="font-medium">Office</span></td>
                            <td class="etd-num etd-col-metric">176</td>
                            <td class="etd-num etd-col-metric">510</td>
                            <td class="etd-num etd-col-metric">31</td>
                            <td class="etd-num etd-col-metric">11</td>
                            <td class="etd-num etd-col-metric">5</td>
                            <td class="etd-num etd-col-metric">£381.00<div class="etd-mini-bar"><div style="width: 34%"></div></div></td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <div class="etd-panel etd-print-unit" id="products">
            <div class="etd-panel-head"><h2 class="etd-panel-title">Product performance</h2></div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
                <table class="etd-table etd-table--product-catalog etd-table--performance-metrics w-full">
                    <thead>
                        <tr>
                            <th class="etd-col-product">Product</th>
                            <th class="etd-num etd-col-metric">Views</th>
                            <th class="etd-num etd-col-metric">Adds</th>
                            <th class="etd-num etd-col-metric">Proceed</th>
                            <th class="etd-num etd-col-metric">Sold</th>
                            <th class="etd-num etd-col-metric">Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="etd-col-product">Oslo corner sofa — grey</td>
                            <td class="etd-num etd-col-metric">284</td>
                            <td class="etd-num etd-col-metric">24</td>
                            <td class="etd-num etd-col-metric">11</td>
                            <td class="etd-num etd-col-metric">4</td>
                            <td class="etd-num etd-col-metric">£1,196.00<div class="etd-mini-bar"><div style="width: 100%"></div></div></td>
                        </tr>
                        <tr>
                            <td class="etd-col-product">Linen duvet set — king</td>
                            <td class="etd-num etd-col-metric">196</td>
                            <td class="etd-num etd-col-metric">18</td>
                            <td class="etd-num etd-col-metric">8</td>
                            <td class="etd-num etd-col-metric">6</td>
                            <td class="etd-num etd-col-metric">£354.00<div class="etd-mini-bar"><div style="width: 30%"></div></div></td>
                        </tr>
                        <tr>
                            <td class="etd-col-product">Ergo desk chair — black</td>
                            <td class="etd-num etd-col-metric">152</td>
                            <td class="etd-num etd-col-metric">14</td>
                            <td class="etd-num etd-col-metric">6</td>
                            <td class="etd-num etd-col-metric">3</td>
                            <td class="etd-num etd-col-metric">£417.00<div class="etd-mini-bar"><div style="width: 35%"></div></div></td>
                        </tr>
                        <tr>
                            <td class="etd-col-product">Oak bedside table</td>
                            <td class="etd-num etd-col-metric">118</td>
                            <td class="etd-num etd-col-metric">9</td>
                            <td class="etd-num etd-col-metric">4</td>
                            <td class="etd-num etd-col-metric">2</td>
                            <td class="etd-num etd-col-metric">£178.00<div class="etd-mini-bar"><div style="width: 15%"></div></div></td>
                        </tr>
                        <tr>
                            <td class="etd-col-product">Floor lamp — brass</td>
                            <td class="etd-num etd-col-metric">94</td>
                            <td class="etd-num etd-col-metric">7</td>
                            <td class="etd-num etd-col-metric">3</td>
                            <td class="etd-num etd-col-metric">2</td>
                            <td class="etd-num etd-col-metric">£142.50<div class="etd-mini-bar"><div style="width: 12%"></div></div></td>
                        </tr>
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
        </div>
        <div class="etd-section-print-body">
    <div class="etd-recoverable-section mb-3">
    <div class="etd-grid-4 etd-grid-4--recoverable">
        <div class="etd-panel etd-panel--abandonment etd-panel--recoverable etd-panel--recoverable-cart etd-print-unit" id="cart-abandon">
            <div class="etd-panel-head etd-panel-head--abandonment">
                <div class="etd-panel-head-main">
                    <h2 class="etd-panel-title">Cart abandoned</h2>
                    <p class="etd-recoverable-summary">18 sessions · £1,842.00</p>
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
                <table class="etd-table etd-table--abandonment etd-table--recoverable w-full">
                    <thead><tr><th class="etd-abandon-session">Session</th><th class="etd-col-center etd-abandon-qty">Qty</th><th class="etd-col-center etd-abandon-value">Value</th><th class="etd-col-center etd-abandon-time">Time</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…8f2a</span></td>
                            <td class="etd-col-center etd-abandon-qty">2</td>
                            <td class="etd-col-center etd-abandon-value">£248.00</td>
                            <td class="etd-col-center etd-abandon-time">12m ago</td>
                        </tr>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…c41b</span></td>
                            <td class="etd-col-center etd-abandon-qty">1</td>
                            <td class="etd-col-center etd-abandon-value">£129.00</td>
                            <td class="etd-col-center etd-abandon-time">38m ago</td>
                        </tr>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…91de</span></td>
                            <td class="etd-col-center etd-abandon-qty">3</td>
                            <td class="etd-col-center etd-abandon-value">£412.00</td>
                            <td class="etd-col-center etd-abandon-time">1h ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="etd-panel etd-panel--abandonment etd-panel--recoverable etd-panel--recoverable-begin etd-print-unit" id="begin-abandon">
            <div class="etd-panel-head etd-panel-head--abandonment">
                <div class="etd-panel-head-main">
                    <h2 class="etd-panel-title">Begin checkout abandoned</h2>
                    <p class="etd-recoverable-summary">9 sessions · £624.50</p>
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
                <table class="etd-table etd-table--abandonment etd-table--recoverable w-full">
                    <thead><tr><th class="etd-abandon-session">Session</th><th class="etd-col-center etd-abandon-qty">Qty</th><th class="etd-col-center etd-abandon-value">Value</th><th class="etd-col-center etd-abandon-time">Time</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…2b7c</span></td>
                            <td class="etd-col-center etd-abandon-qty">1</td>
                            <td class="etd-col-center etd-abandon-value">£89.00</td>
                            <td class="etd-col-center etd-abandon-time">22m ago</td>
                        </tr>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…55aa</span></td>
                            <td class="etd-col-center etd-abandon-qty">2</td>
                            <td class="etd-col-center etd-abandon-value">£196.50</td>
                            <td class="etd-col-center etd-abandon-time">2h ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="etd-panel etd-panel--abandonment etd-panel--recoverable etd-panel--recoverable-proceed etd-print-unit" id="proceed-abandon">
            <div class="etd-panel-head etd-panel-head--abandonment">
                <div class="etd-panel-head-main">
                    <h2 class="etd-panel-title">Proceed checkout abandoned</h2>
                    <p class="etd-recoverable-summary">4 sessions · £318.00</p>
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
                <table class="etd-table etd-table--abandonment etd-table--recoverable w-full">
                    <thead><tr><th class="etd-abandon-session">Session</th><th class="etd-col-center etd-abandon-qty">Qty</th><th class="etd-col-center etd-abandon-value">Value</th><th class="etd-col-center etd-abandon-time">Time</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…e903</span></td>
                            <td class="etd-col-center etd-abandon-qty">1</td>
                            <td class="etd-col-center etd-abandon-value">£139.00</td>
                            <td class="etd-col-center etd-abandon-time">45m ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="etd-panel etd-panel--abandonment etd-panel--recoverable etd-panel--recoverable-success etd-print-unit" id="payment-success">
            <div class="etd-panel-head etd-panel-head--abandonment">
                <div class="etd-panel-head-main">
                    <h2 class="etd-panel-title">Payment success</h2>
                    <p class="etd-recoverable-summary">38 sessions · £2,847.50</p>
                </div>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
                <table class="etd-table etd-table--abandonment etd-table--recoverable w-full">
                    <thead><tr><th class="etd-abandon-session">Session</th><th class="etd-col-center etd-abandon-qty">Qty</th><th class="etd-col-center etd-abandon-value">Value</th><th class="etd-col-center etd-abandon-time">Time</th></tr></thead>
                    <tbody>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…1f44</span></td>
                            <td class="etd-col-center etd-abandon-qty">2</td>
                            <td class="etd-col-center etd-abandon-value">£298.00</td>
                            <td class="etd-col-center etd-abandon-time">8m ago</td>
                        </tr>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…7c21</span></td>
                            <td class="etd-col-center etd-abandon-qty">1</td>
                            <td class="etd-col-center etd-abandon-value">£59.00</td>
                            <td class="etd-col-center etd-abandon-time">31m ago</td>
                        </tr>
                        <tr>
                            <td class="etd-abandon-session"><span class="etd-chip etd-chip--recoverable">…a0be</span></td>
                            <td class="etd-col-center etd-abandon-qty">4</td>
                            <td class="etd-col-center etd-abandon-value">£512.00</td>
                            <td class="etd-col-center etd-abandon-time">1h ago</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
        </div>
    </div>
</section>

<section class="etd-dashboard-section">
    <div class="etd-section-print-block etd-print-section">
        <div class="etd-section-intro">
            <h2 class="etd-section-title"><span class="etd-section-num">03</span> Acquisition &amp; audience</h2>
            <p class="etd-section-note">Device mix, traffic sources, session length, and new vs returning visitors.</p>
        </div>
        <div class="etd-section-print-body">
    <div class="etd-panel etd-panel--acquisition etd-panel--device-browser-full etd-print-unit mb-3" id="device">
        <div class="etd-panel-head etd-panel-head--device-browser"><h2 class="etd-panel-title">Device &amp; browser</h2></div>
        <div class="etd-device-browser etd-device-browser-grid px-4 pb-4">
            <div class="etd-device-browser-panel">
                <h3 class="etd-device-browser-panel__title">Device</h3>
                <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--device-browser">
                    <table class="etd-table etd-table--device-browser w-full">
                        <thead>
                            <tr>
                                <th>Device</th>
                                <th class="etd-num">Sessions</th>
                                <th class="etd-num">Views</th>
                                <th class="etd-num">Cart</th>
                                <th class="etd-num">Paid</th>
                                <th class="etd-num">Conv.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Mobile</td><td class="etd-num">482</td><td class="etd-num">1,840</td><td class="etd-num">94</td><td class="etd-num">21</td><td class="etd-num">4.4%</td></tr>
                            <tr><td>Desktop</td><td class="etd-num">312</td><td class="etd-num">1,420</td><td class="etd-num">58</td><td class="etd-num">14</td><td class="etd-num">4.5%</td></tr>
                            <tr><td>Tablet</td><td class="etd-num">53</td><td class="etd-num">198</td><td class="etd-num">8</td><td class="etd-num">3</td><td class="etd-num">5.7%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="etd-device-browser-panel">
                <h3 class="etd-device-browser-panel__title">Browser</h3>
                <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--device-browser">
                    <table class="etd-table etd-table--device-browser w-full">
                        <thead>
                            <tr>
                                <th>Browser</th>
                                <th class="etd-num">Sessions</th>
                                <th class="etd-num">Views</th>
                                <th class="etd-num">Cart</th>
                                <th class="etd-num">Paid</th>
                                <th class="etd-num">Conv.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>Chrome</td><td class="etd-num">518</td><td class="etd-num">2,104</td><td class="etd-num">102</td><td class="etd-num">24</td><td class="etd-num">4.6%</td></tr>
                            <tr><td>Safari</td><td class="etd-num">214</td><td class="etd-num">892</td><td class="etd-num">38</td><td class="etd-num">9</td><td class="etd-num">4.2%</td></tr>
                            <tr><td>Edge</td><td class="etd-num">68</td><td class="etd-num">312</td><td class="etd-num">12</td><td class="etd-num">3</td><td class="etd-num">4.4%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="etd-panel etd-print-unit mb-3" id="traffic">
        <div class="etd-panel-head"><h2 class="etd-panel-title">Traffic sources</h2></div>
        <div class="etd-traffic-sources">
            <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--traffic">
                <table class="etd-table etd-table--traffic w-full">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Medium</th>
                            <th class="etd-num">Sessions</th>
                            <th class="etd-num">Views</th>
                            <th class="etd-num">Cart</th>
                            <th class="etd-num">Paid</th>
                            <th class="etd-num">Sale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>google</td><td>cpc</td><td class="etd-num">312</td><td class="etd-num">1,240</td><td class="etd-num">48</td><td class="etd-num">14</td><td class="etd-num">£1,042.00</td></tr>
                        <tr><td>(direct)</td><td>(none)</td><td class="etd-num">198</td><td class="etd-num">820</td><td class="etd-num">32</td><td class="etd-num">9</td><td class="etd-num">£612.50</td></tr>
                        <tr><td>instagram</td><td>social</td><td class="etd-num">142</td><td class="etd-num">510</td><td class="etd-num">22</td><td class="etd-num">6</td><td class="etd-num">£428.00</td></tr>
                        <tr><td>newsletter</td><td>email</td><td class="etd-num">86</td><td class="etd-num">296</td><td class="etd-num">18</td><td class="etd-num">5</td><td class="etd-num">£385.00</td></tr>
                        <tr><td>bing</td><td>organic</td><td class="etd-num">64</td><td class="etd-num">248</td><td class="etd-num">9</td><td class="etd-num">2</td><td class="etd-num">£142.00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="etd-grid-2 etd-grid-2--acquisition-insights mt-5 mb-5" id="duration" data-etd-dashboard-demo="acquisition">
        <div class="etd-panel etd-panel--duration-distribution etd-print-unit etd-panel--in-grid">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Session duration distribution</h2>
            </div>
            <div class="etd-panel-body">
                <p class="etd-duration-summary m-0 mb-3"><strong>847</strong> sessions · median <strong>3m 42s</strong></p>
                <div class="etd-duration-buckets" role="list">
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">0–1 min</span><span class="etd-duration-bucket__stats">198 <span class="etd-duration-bucket__pct">(23.4%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 23.4%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">1–3 min</span><span class="etd-duration-bucket__stats">245 <span class="etd-duration-bucket__pct">(28.9%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 28.9%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">3–5 min</span><span class="etd-duration-bucket__stats">156 <span class="etd-duration-bucket__pct">(18.4%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 18.4%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">5–7 min</span><span class="etd-duration-bucket__stats">98 <span class="etd-duration-bucket__pct">(11.6%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 11.6%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">7–9 min</span><span class="etd-duration-bucket__stats">62 <span class="etd-duration-bucket__pct">(7.3%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 7.3%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">9–11 min</span><span class="etd-duration-bucket__stats">38 <span class="etd-duration-bucket__pct">(4.5%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 4.5%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">11–13 min</span><span class="etd-duration-bucket__stats">24 <span class="etd-duration-bucket__pct">(2.8%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 2.8%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">13–15 min</span><span class="etd-duration-bucket__stats">14 <span class="etd-duration-bucket__pct">(1.7%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 1.7%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">15–30 min</span><span class="etd-duration-bucket__stats">9 <span class="etd-duration-bucket__pct">(1.1%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 1.1%"></span></div>
                    </div>
                    <div class="etd-duration-bucket" role="listitem">
                        <div class="etd-duration-bucket__head"><span class="etd-duration-bucket__label">30+ min</span><span class="etd-duration-bucket__stats">3 <span class="etd-duration-bucket__pct">(0.4%)</span></span></div>
                        <div class="etd-duration-bucket__bar" aria-hidden="true"><span class="etd-duration-bucket__fill" style="width: 0.4%"></span></div>
                    </div>
                </div>
                <p class="etd-panel-note etd-panel-note--duration m-0 mt-3">How long shoppers stay per session — useful for landing-page quality, content engagement, and checkout friction.</p>
            </div>
        </div>
        <div class="etd-panel etd-panel--new-returning etd-print-unit">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Unique vs returning</h2>
            </div>
            <div class="etd-panel-body">
                <p class="etd-new-returning-summary m-0 mb-3"><strong>704</strong> unique visitors · <strong>143</strong> returning sessions</p>
                <div class="etd-chart-wrap xs"><canvas id="etdNewReturningChart"></canvas></div>
            </div>
        </div>
    </div>
        </div>
    </div>
</section>

</div>
@endsection
