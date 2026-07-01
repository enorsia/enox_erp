@extends('layouts.app')

@section('title', 'Sales Report Export')

@section('content')
<div x-data="{
        drawerOpen: false,
        exportOpen: false,
        period: '{{ $filters['period'] ?? 'this_month' }}',
        fromYM: '{{ $filters['from_year_month'] ?? now()->format('Y-m') }}',
        toYM:   '{{ $filters['to_year_month']   ?? now()->format('Y-m') }}',
        tables: { daily_report: true, return_breakdown: true, weekly_breakdown: true },
        submitPeriod() {
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
                url.searchParams.set('to_year_month', this.toYM);
            }
            const selected = Object.keys(this.tables).filter(k => this.tables[k]);
            if (selected.length > 0) url.searchParams.set('tables', selected.join(','));
            return url.toString();
        },
        atLeastOneSelected() { return Object.values(this.tables).some(v => v); }
     }"
     @keydown.escape.window="drawerOpen = false; exportOpen = false">

    @include('sales.partials.report_filter_drawer')

    {{-- Export modal --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Sales</h3>
                        <p class="text-[11px] text-slate-400">Choose which sections to include</p>
                    </div>
                </div>
                <button type="button" @click="exportOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-3">
                @foreach([
                    ['key' => 'daily_report', 'label' => 'Daily Report', 'desc' => 'Day-by-day sales, spend, ROAS & platform breakdown', 'tone' => 'emerald'],
                    ['key' => 'return_breakdown', 'label' => 'Return Breakdown', 'desc' => 'Returns by reason per platform & gender', 'tone' => 'rose'],
                    ['key' => 'weekly_breakdown', 'label' => 'Weekly Breakdown', 'desc' => 'Weekly sales, spend, orders & returns', 'tone' => 'amber'],
                ] as $section)
                    <label class="flex items-start gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
                        <input type="checkbox" x-model="tables.{{ $section['key'] }}" class="mt-0.5 w-4 h-4 rounded border-slate-300 cursor-pointer">
                        <div>
                            <div class="text-[13px] font-semibold text-slate-700 dark:text-slate-200">{{ $section['label'] }}</div>
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $section['desc'] }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button type="button" @click="exportOpen = false" class="flex-1 py-2.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 text-slate-500 font-medium">Cancel</button>
                <a :href="exportUrl()" :class="atLeastOneSelected() ? '' : 'pointer-events-none opacity-40'"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold text-center">Export Excel</a>
            </div>
        </div>
    </div>

    <div class="p-5 lg:p-6 space-y-5">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="w-8 h-8 bg-emerald-400/15 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </span>
                    Sales Report
                </h1>
                <p class="text-sm text-slate-400 mt-0.5 ml-10">{{ $range['label'] }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap sm:justify-end">
                <button type="button" @click="exportOpen = true"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 font-medium hover:bg-emerald-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </button>
                <button type="button" @click="drawerOpen = true"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $active_filter_count > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 hover:bg-slate-50' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($active_filter_count > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $active_filter_count }}</span>
                    @endif
                </button>
            </div>
        </div>

        {{-- Period filter --}}
        <div class="an-card p-5">
            <p class="sec-heading mb-4">Filter by Period</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-52">
                    <label class="f-label">Period</label>
                    <select class="f-input custom-select" x-model="period" @change="period !== 'custom' && submitPeriod()">
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="last_3_months">Last 3 Months</option>
                        <option value="last_6_months">Last 6 Months</option>
                        <option value="last_1_year">Last 1 Year</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div x-show="period === 'custom'" x-collapse class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="f-label">From (Year-Month)</label>
                        <input type="month" x-model="fromYM" class="f-input w-42" />
                    </div>
                    <div>
                        <label class="f-label">To (Year-Month)</label>
                        <input type="month" x-model="toYM" class="f-input w-42" />
                    </div>
                    <button type="button" @click="submitPeriod()" class="px-5 py-2 bg-accent-400 hover:bg-accent-600 text-white text-sm font-semibold rounded-lg">Apply</button>
                </div>
            </div>
        </div>

        {{-- KPI stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach($stats as $stat)
                <div class="sr-stat-card sr-stat-{{ $stat['tone'] }}">
                    <p class="sr-stat-label">{{ $stat['label'] }}</p>
                    <p class="sr-stat-value">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Active filter tags --}}
        @if(count($active_filter_tags) > 0)
            <div class="flex flex-wrap gap-2">
                @foreach($active_filter_tags as $tag)
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">{{ $tag['label'] }}:</span> {{ $tag['value'] }}
                        <a href="{{ $tag['url'] }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                    </div>
                @endforeach
                <a href="{{ $reset_report_url }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 transition-colors">Clear all</a>
            </div>
        @endif

        {{-- How to use --}}
        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/40 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="text-[12px] text-blue-700 dark:text-blue-400">
                <span class="font-semibold">How to use:</span>
                Select a period above, open <strong>Filters</strong> to narrow by week, platform, return reason, gender, or date range.
                Switch between <strong>Totals</strong>, <strong>Weekly</strong>, <strong>All Data</strong>, and <strong>Return Breakdown</strong> — the same sections exported to Excel.
            </div>
        </div>

        {{-- Report data viewer --}}
        <div class="an-card overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="sec-heading mb-1">Report Data</p>
                        <h2 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">{{ $range['label'] }}</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Showing {{ $visible_count }} rows
                            @if($active_filter_count > 0)
                                <span class="text-accent-600">· {{ $active_filter_count }} filter{{ $active_filter_count > 1 ? 's' : '' }} active</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($view_tabs as $tab)
                            <a href="{{ $tab['url'] }}"
                               class="sr-view-pill {{ $tab['active'] ? 'active' : '' }}">
                                {{ $tab['label'] }}
                                <span class="sr-view-count">{{ $row_counts[$tab['key']] ?? 0 }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="sr-table-wrap">
                @if($view === 'weekly')
                    @include('sales.partials.report_table_weekly')
                @elseif($view === 'daily')
                    @include('sales.partials.report_table_daily')
                @elseif($view === 'returns')
                    @include('sales.partials.report_table_returns')
                @else
                    @include('sales.partials.report_table_summary')
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
