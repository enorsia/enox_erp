@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div id="analytics-dashboard-content" class="p-5 lg:p-6 space-y-5">

    {{-- ═══ Page Header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-8 h-8 bg-accent-400/15 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </span>
                Dashboard
            </h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5 ml-10">{{ $range['label'] }}</p>
        </div>
        <div class="text-right shrink-0 hidden sm:block">
            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Last Updated</p>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ now()->format('d M Y, H:i') }}</p>
        </div>
    </div>

    {{-- ═══ Filter + Export ═══ --}}
    <div class="an-card p-4" x-data="{
        period: '{{ $filters['period'] ?? 'this_month' }}',
        fromYM: '{{ $filters['from_year_month'] ?? now()->format('Y-m') }}',
        toYM:   '{{ $filters['to_year_month']   ?? now()->format('Y-m') }}',
        submit() {
            const url = new URL(window.location.href);
            url.searchParams.set('period', this.period);
            if (this.period === 'custom') {
                url.searchParams.set('from_year_month', this.fromYM);
                url.searchParams.set('to_year_month', this.toYM);
            } else {
                url.searchParams.delete('from_year_month');
                url.searchParams.delete('to_year_month');
            }
            window.location.href = url.toString();
        },
        exportUrl() {
            const base = '{{ route('admin.sales.analytics.export') }}';
            const url  = new URL(base, window.location.origin);
            url.searchParams.set('period', this.period);
            if (this.period === 'custom') {
                url.searchParams.set('from_year_month', this.fromYM);
                url.searchParams.set('to_year_month',   this.toYM);
            }
            return url.toString();
        }
    }">
        <div class="flex flex-wrap items-end gap-3">

            {{-- Period select dropdown --}}
            <div class="w-52">
                <label class="f-label">Filter by Period</label>
                <select class="f-input custom-select" x-model="period" @change="period !== 'custom' && submit()">
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option value="last_6_months">Last 6 Months</option>
                    <option value="last_1_year">Last 1 Year</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            {{-- Custom pickers (shown inline when custom is selected) --}}
            <div x-show="period === 'custom'" x-collapse class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="f-label">From (Year-Month)</label>
                    <input type="month" x-model="fromYM" class="f-input w-42" />
                </div>
                <div>
                    <label class="f-label">To (Year-Month)</label>
                    <input type="month" x-model="toYM" class="f-input w-42" />
                </div>
                <button type="button" @click="submit()"
                    class="px-5 py-2 bg-accent-400 hover:bg-accent-600 text-white text-sm font-semibold rounded-lg transition-colors">
                    Apply
                </button>
            </div>

            {{-- Export Excel button (right side) --}}
{{--            <div class="sm:ml-auto">--}}
{{--                <a :href="exportUrl()"--}}
{{--                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors">--}}
{{--                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">--}}
{{--                        <path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>--}}
{{--                    </svg>--}}
{{--                    Export Excel--}}
{{--                </a>--}}
{{--            </div>--}}
        </div>
    </div>

    {{-- ═══ KPI Cards ═══ --}}
    <p class="sec-heading">Key Metrics — {{ $range['label'] }}</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">

        <div class="kpi-card" style="background:linear-gradient(135deg,#1D9E75,#0F6E56);color:#fff;">
            <div class="kpi-label">Total Sales</div>
            <div class="kpi-value">{{ number_format($summary['total_sales'], 0) }}</div>
            <div class="kpi-sub">{{ number_format($summary['total_orders']) }} orders</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;">
            <div class="kpi-label">Total Spent</div>
            <div class="kpi-value">{{ number_format($summary['total_spent'], 0) }}</div>
            <div class="kpi-sub">Marketing cost</div>
        </div>

        @php $profitPos = $summary['net_profit'] >= 0; @endphp
        <div class="kpi-card" style="background:linear-gradient(135deg,{{ $profitPos ? '#10b981,#059669' : '#f43f5e,#be123c' }});color:#fff;">
            <div class="kpi-label">Net Profit</div>
            <div class="kpi-value">{{ number_format($summary['net_profit'], 0) }}</div>
            <div class="kpi-sub">ROI {{ $summary['roi'] }}%</div>
        </div>

        {{-- ROAS --}}
        @php $roas = $forecasting['total_roas']; $roasPos = $roas >= 100; @endphp
        <div class="kpi-card" style="background:linear-gradient(135deg,{{ $roasPos ? '#0891b2,#0369a1' : '#f97316,#c2410c' }});color:#fff;">
            <div class="kpi-label">ROAS</div>
            <div class="kpi-value">{{ $roas }}%</div>
            <div class="kpi-sub">Return on Ad Spend</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#f43f5e,#be123c);color:#fff;">
            <div class="kpi-label">Returns</div>
            <div class="kpi-value">{{ number_format($summary['total_returns']) }}</div>
            <div class="kpi-sub">Rate {{ $summary['return_rate'] }}%</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);color:#fff;">
            <div class="kpi-label">Total Budget</div>
            <div class="kpi-value">{{ number_format($summary['total_budget'], 0) }}</div>
            <div class="kpi-sub">
                @if($summary['budget_utilisation'] !== null){{ $summary['budget_utilisation'] }}% used
                @else No budget set @endif
            </div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value">{{ number_format($summary['total_orders']) }}</div>
            <div class="kpi-sub">Qty {{ number_format($summary['total_quantities']) }}</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;">
            <div class="kpi-label">Avg Order Value</div>
            <div class="kpi-value">{{ number_format($summary['avg_order_value'], 0) }}</div>
            <div class="kpi-sub">Per order</div>
        </div>

        {{-- Avg Daily Sales --}}
        <div class="kpi-card" style="background:linear-gradient(135deg,#64748b,#334155);color:#fff;">
            <div class="kpi-label">Avg Daily Sales</div>
            <div class="kpi-value">{{ number_format($forecasting['avg_daily_sales'], 0) }}</div>
            <div class="kpi-sub">{{ $forecasting['actual_days'] }} days tracked</div>
        </div>

        {{-- 30-day Forecast --}}
        <div class="kpi-card" style="background:linear-gradient(135deg,#6366f1,#4338ca);color:#fff;">
            <div class="kpi-label">30-Day Forecast</div>
            <div class="kpi-value">{{ number_format($forecasting['forecast_30_sales'], 0) }}</div>
            <div class="kpi-sub">~{{ $forecasting['forecast_30_orders'] }} orders</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#ec4899,#be185d);color:#fff;">
            <div class="kpi-label">Return Qty</div>
            <div class="kpi-value">{{ number_format($summary['total_return_qty']) }}</div>
            <div class="kpi-sub">Items returned</div>
        </div>

    </div>

    {{-- ═══ Row 1: Monthly Sales Trend + Weekly Trend ═══ --}}
    <p class="sec-heading mt-2">Sales Trends</p>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Monthly Sales & Spend Trend
            </div>
            <p class="chart-subtitle">Revenue vs marketing spend per month</p>
            <div class="mt-4" style="height:260px;"><canvas id="salesTrendChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Weekly Trend
            </div>
            <p class="chart-subtitle">Sales, spend and orders grouped by calendar week</p>
            <div class="mt-4" style="height:260px;"><canvas id="weeklyTrendChart"></canvas></div>
        </div>
    </div>

    {{-- ═══ Row 2: Budget vs Actual + Net Profit ═══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Budget vs Actual Sales
            </div>
            <p class="chart-subtitle">Monthly budget allocation vs actual revenue</p>
            <div class="mt-4" style="height:260px;"><canvas id="budgetVsActualChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Net Profit by Month
            </div>
            <p class="chart-subtitle">Sales minus spend — green = profit, red = loss</p>
            <div class="mt-4" style="height:260px;"><canvas id="netProfitChart"></canvas></div>
        </div>
    </div>

    {{-- ═══ Row 3: Orders vs Returns + ROAS Trend ═══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 15h4"/></svg>
                Orders vs Returns by Month
            </div>
            <p class="chart-subtitle">Monthly order count vs return count</p>
            <div class="mt-4" style="height:260px;"><canvas id="ordersVsReturnsChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 12l3-3 3 3 4-4"/><path stroke-linecap="round" d="M3 20h18"/></svg>
                ROAS Trend by Month
            </div>
            <p class="chart-subtitle">Return on Ad Spend (%) — 100% = break-even, higher = profitable</p>
            <div class="mt-4" style="height:260px;"><canvas id="roasTrendChart"></canvas></div>
        </div>
    </div>

    {{-- ═══ Row 4: Platform Sales + Platform Cost vs Sales ═══ --}}
    <p class="sec-heading mt-2">Platform Analysis</p>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3v9l5 3"/></svg>
                Sales by Platform
            </div>
            <p class="chart-subtitle">Top platforms by total revenue (horizontal bar)</p>
            <div class="mt-4" style="height:280px;"><canvas id="platformSalesChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                Platform Cost vs Sales + ROAS
            </div>
            <p class="chart-subtitle">Spend vs revenue per platform (ROAS on right axis)</p>
            <div class="mt-4" style="height:280px;"><canvas id="platformCostSalesChart"></canvas></div>
        </div>
    </div>

    {{-- ═══ Row 5: Budget Balance + Returns by Platform ═══ --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Budget Balance per Platform
            </div>
            <p class="chart-subtitle">Budget vs spent vs remaining balance per channel</p>
            <div class="mt-4" style="height:280px;"><canvas id="budgetBalanceChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
                Returns by Platform
            </div>
            <p class="chart-subtitle">Platforms with highest return volume</p>
            <div class="mt-4" style="height:280px;"><canvas id="platformReturnsChart"></canvas></div>
        </div>
    </div>

    {{-- ═══ Row 6: Return Reasons + Gender ═══ --}}
    <p class="sec-heading mt-2">Return &amp; Gender Analysis</p>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Return Reasons
            </div>
            <p class="chart-subtitle">Returns by reason category</p>
            <div class="mt-4 flex justify-center" style="height:240px;"><canvas id="returnReasonsChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Orders — Gender Split
            </div>
            <p class="chart-subtitle">Male / Female / Kids order distribution</p>
            <div class="mt-4 flex justify-center" style="height:240px;"><canvas id="genderOrdersChart"></canvas></div>
        </div>

        <div class="an-card p-5">
            <div class="chart-title">
                <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Returns — Gender Split
            </div>
            <p class="chart-subtitle">Male / Female / Kids return distribution</p>
            <div class="mt-4 flex justify-center" style="height:240px;"><canvas id="genderReturnsChart"></canvas></div>
        </div>
    </div>

</div>
@endsection

@push('js')
@php
    $analyticsJson = json_encode([
        'monthlySales'        => $monthlySales,
        'budgetVsActual'      => $budgetVsActual,
        'platformSales'       => $platformSales,
        'platformReturns'     => $platformReturns,
        'returnReasons'       => $returnReasons,
        'genderBreakdown'     => $genderBreakdown,
        'platformCostVsSales' => $platformCostVsSales,
        'weeklyTrend'         => $weeklyTrend,
        'platformBudgets'     => $platformBudgets,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
@endphp
<script>
window.analyticsData = {!! $analyticsJson !!};
</script>
@endpush
