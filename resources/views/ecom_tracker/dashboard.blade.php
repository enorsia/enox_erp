@extends('layouts.app')

@section('title', 'Ecom Tracker Dashboard')

@section('content')
@php
    $d = $dashboard;
    $period = $filters['period'] ?? '30d';
@endphp

<div id="ecom-tracker-dashboard-content" class="etd-page">

    <div class="etd-topbar">
        <div class="etd-topbar-intro">
            <h1 class="etd-page-title">Store performance</h1>
            <p class="etd-page-desc">
                {{ $d['range']['label'] }}
            </p>
            <p class="etd-page-live">
                <span class="etd-live-dot"></span>Live · last event {{ $d['live']['label'] }}
            </p>
        </div>

        <div class="etd-toolbar"
             x-data="{
                period: '{{ $period }}',
                dateFrom: '{{ $filters['date_from'] }}',
                dateTo: '{{ $filters['date_to'] }}',
                apply(period) {
                    this.period = period;
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', period);
                    if (period !== 'custom') {
                        url.searchParams.delete('date_from');
                        url.searchParams.delete('date_to');
                        window.location.href = url.toString();
                    }
                },
                applyCustom() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('period', 'custom');
                    url.searchParams.set('date_from', this.dateFrom);
                    url.searchParams.set('date_to', this.dateTo);
                    window.location.href = url.toString();
                },
                exportUrl() {
                    const url = new URL('{{ route('admin.ecom-tracker.dashboard.export') }}', window.location.origin);
                    url.searchParams.set('period', this.period);
                    if (this.period === 'custom') {
                        url.searchParams.set('date_from', this.dateFrom);
                        url.searchParams.set('date_to', this.dateTo);
                    }
                    return url.toString();
                }
             }">
            @foreach (['7d' => '7d', '30d' => '30d', '90d' => '90d'] as $key => $label)
                <button type="button" class="etd-pill {{ $period === $key ? 'active' : '' }}" @click="apply('{{ $key }}')">{{ $label }}</button>
            @endforeach
            <button type="button" class="etd-pill {{ $period === 'custom' ? 'active' : '' }}" @click="period = 'custom'">Custom</button>
            <a :href="exportUrl()" class="etd-pill etd-pill-primary no-underline">Export Excel</a>

            <div x-show="period === 'custom'" x-collapse class="etd-custom-dates">
                <input type="date" x-model="dateFrom" class="f-input etd-date-input">
                <input type="date" x-model="dateTo" class="f-input etd-date-input">
                <button type="button" class="etd-pill etd-pill-primary etd-pill-apply" @click="applyCustom()">Apply</button>
            </div>
        </div>
    </div>

    <div class="etd-kpi-grid mb-5">
        @foreach ($d['kpis'] as $kpi)
            <div class="etd-kpi">
                <div class="etd-kpi-label">{{ $kpi['label'] }}</div>
                <div class="etd-kpi-value">{{ $kpi['formatted'] }}</div>
                <div class="etd-kpi-delta {{ $kpi['delta']['direction'] }}">{{ $kpi['delta']['text'] }}</div>
            </div>
        @endforeach
    </div>

    <p class="mb-5">
        <a href="{{ route('admin.ecom-tracker.visitors') }}" class="text-accent-600 hover:text-accent-700 text-sm font-medium">
            View detailed visitor analytics &rarr;
        </a>
    </p>

    <div class="etd-grid-2 mb-3">
        <div class="etd-panel" id="funnel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Conversion funnel</h2>
                <span class="etd-panel-hint">category_view → payment_success · {{ $d['range']['label'] }}</span>
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
                        <div class="etd-funnel-stats">
                            {{ $row['percent_of_top'] }}% of top
                            @if ($row['drop_off_percent'] !== null)
                                <span class="etd-funnel-drop">−{{ $row['drop_off_percent'] }}% drop-off</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="etd-panel">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Sessions &amp; conversion trend</h2>
                <span class="etd-tag">{{ $d['trend']['range_label'] ?? $d['range']['label'] }}</span>
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
                <span class="etd-panel-hint">views → conversion</span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="etd-num">Views</th>
                        <th class="etd-num">Add-rate</th>
                        <th class="etd-num">Conv.</th>
                        <th>
                            <span class="etd-th-with-tip">
                                Signal
                                <button type="button" class="etd-tip-trigger" aria-label="Signal definitions">
                                    <svg class="etd-tip-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                                        <circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="1.25"/>
                                        <path fill="currentColor" d="M7.25 7h1.5V6.1c0-.69.56-1.25 1.25-1.25.69 0 1.25.56 1.25 1.25v.65c0 .69-.56 1.25-1.25 1.25H8.5v3.35H7.25V7z"/>
                                        <circle cx="8" cy="4.35" r=".85" fill="currentColor"/>
                                    </svg>
                                    <span class="etd-tip-content" role="tooltip">
                                        <span class="etd-tip-line"><span class="etd-badge high">Promote</span> View-to-purchase conversion ≥ 2.5%</span>
                                        <span class="etd-tip-line"><span class="etd-badge mid">Steady</span> Conversion 1.5%–2.5%</span>
                                        <span class="etd-tip-line"><span class="etd-badge low">Investigate</span> Conversion below 1.5%</span>
                                    </span>
                                </button>
                            </span>
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
                <h2 class="etd-panel-title">Top products</h2>
                <span class="etd-panel-hint">sorted by revenue</span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="etd-num">Views</th>
                        <th class="etd-num">Add to cart</th>
                        <th class="etd-num">Purchases</th>
                        <th class="etd-num">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['products'] as $product)
                        <tr>
                            <td>
                                {{ $product['name'] }}
                                <div class="etd-subtle">{{ $product['code'] }}</div>
                            </td>
                            <td class="etd-num">{{ number_format($product['views']) }}</td>
                            <td class="etd-num">{{ number_format($product['adds']) }}</td>
                            <td class="etd-num">{{ number_format($product['purchases']) }}</td>
                            <td class="etd-num">
                                £{{ number_format($product['revenue'], 2) }}
                                <div class="etd-mini-bar"><div style="width: {{ $product['revenue_bar_percent'] }}%"></div></div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-slate-400">No product activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="etd-panel mb-3" id="colors">
        <div class="etd-panel-head">
            <h2 class="etd-panel-title">Color / variant performance — viewed vs purchased</h2>
            <span class="etd-panel-hint">grouped by product</span>
        </div>
        <div class="etd-table-scroll etd-table-scroll--wide etd-table-scroll--fixed etd-table-scroll--tall">
            <table class="etd-table etd-table--colors">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="etd-num">SKU</th>
                        <th>Color</th>
                        <th class="etd-num">Viewed</th>
                        <th class="etd-num">Purchased</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['colors']['products'] as $product)
                        <tr class="etd-color-product-row">
                            <td class="etd-color-product-name">{{ $product['product'] }}</td>
                            <td class="etd-num"><span class="etd-chip">{{ $product['sku'] }}</span></td>
                            <td></td>
                            <td class="etd-num">{{ number_format($product['viewed']) }}</td>
                            <td class="etd-num">{{ number_format($product['purchased']) }}</td>
                        </tr>
                        @foreach ($product['variants'] as $variant)
                            <tr class="etd-color-variant-row">
                                <td></td>
                                <td></td>
                                <td>{{ $variant['color'] }}</td>
                                <td class="etd-num">{{ number_format($variant['viewed']) }}</td>
                                <td class="etd-num">{{ number_format($variant['purchased']) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="5" class="text-slate-400">No color / variant activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="etd-section-title"><span class="etd-section-num">02</span> Recoverable revenue</h2>
    <p class="etd-section-note">Sessions that showed buying intent but didn't convert — review for retargeting.</p>

    <div class="etd-grid-2 mb-3">
        <div class="etd-panel" id="cart-abandon">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Cart abandoned</h2>
                <span class="etd-stake-badge">
                    {{ number_format($d['cart_abandonment']['session_count']) }} sessions · £{{ number_format($d['cart_abandonment']['at_stake'], 2) }} at stake
                    @if ($d['cart_abandonment']['session_count'] > count($d['cart_abandonment']['rows']))
                        · latest {{ count($d['cart_abandonment']['rows']) }} shown
                    @endif
                </span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
            <table class="etd-table etd-table--abandonment">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Last item</th>
                        <th class="etd-num">Cart value</th>
                        <th>Idle</th>
                        <th class="etd-col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['cart_abandonment']['rows'] as $row)
                        <tr>
                            <td><span class="etd-chip">{{ $row['session_label'] }}</span></td>
                            <td>{{ $row['detail'] }}</td>
                            <td class="etd-num">£{{ number_format($row['value'], 2) }}</td>
                            <td>{{ $row['idle'] }}</td>
                            <td class="etd-col-action"><a href="{{ $row['activity_url'] }}" class="etd-link">View session</a></td>
                        </tr>
                    @empty
                        <tr class="etd-table-empty">
                            <td colspan="5" class="etd-table-empty-cell text-slate-400">No cart abandonment in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="etd-panel" id="checkout-abandon">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Checkout abandoned</h2>
                <span class="etd-stake-badge">
                    {{ number_format($d['checkout_abandonment']['session_count']) }} sessions · £{{ number_format($d['checkout_abandonment']['at_stake'], 2) }} at stake
                    @if ($d['checkout_abandonment']['session_count'] > count($d['checkout_abandonment']['rows']))
                        · latest {{ count($d['checkout_abandonment']['rows']) }} shown
                    @endif
                </span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
            <table class="etd-table etd-table--abandonment">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Coupon</th>
                        <th class="etd-num">Total</th>
                        <th>Idle</th>
                        <th class="etd-col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($d['checkout_abandonment']['rows'] as $row)
                        <tr>
                            <td><span class="etd-chip">{{ $row['session_label'] }}</span></td>
                            <td>{{ $row['detail'] }}</td>
                            <td class="etd-num">£{{ number_format($row['value'], 2) }}</td>
                            <td>{{ $row['idle'] }}</td>
                            <td class="etd-col-action"><a href="{{ $row['activity_url'] }}" class="etd-link">View session</a></td>
                        </tr>
                    @empty
                        <tr class="etd-table-empty">
                            <td colspan="5" class="etd-table-empty-cell text-slate-400">No checkout abandonment in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <h2 class="etd-section-title"><span class="etd-section-num">03</span> Acquisition &amp; audience</h2>
    <p class="etd-section-note">Where traffic comes from and which device/market needs UX attention.</p>

    <div class="etd-grid-5-7 mb-3">
        <div class="etd-panel" id="device">
            <div class="etd-panel-head">
                <h2 class="etd-panel-title">Device &amp; browser</h2>
                <span class="etd-panel-hint">device_type</span>
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
                <span class="etd-panel-hint">utm_source / utm_medium</span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Medium</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Conv.</th>
                        <th class="etd-num">Revenue</th>
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
                <span class="etd-panel-hint">country / city</span>
            </div>
            <div class="etd-table-scroll etd-table-scroll--narrow etd-table-scroll--fixed">
            <table class="etd-table">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th class="etd-num">Sessions</th>
                        <th class="etd-num">Revenue</th>
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
                <span class="etd-panel-hint">active dwell time, start_time/end_time</span>
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
