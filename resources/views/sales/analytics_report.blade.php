@extends('layouts.app')

@section('title', 'Sales Report Export')

@section('content')
<div class="p-5 lg:p-6 space-y-5"
     x-data="{
         period:   '{{ $filters['period'] ?? 'this_month' }}',
         fromYM:   '{{ $filters['from_year_month'] ?? now()->format('Y-m') }}',
         toYM:     '{{ $filters['to_year_month']   ?? now()->format('Y-m') }}',
         exportOpen: false,
         tables: { daily_report: true, return_breakdown: true, weekly_breakdown: true },

         submit() {
             const url = new URL(window.location.href);
             url.searchParams.set('period', this.period);
             if (this.period === 'custom') {
                 url.searchParams.set('from_year_month', this.fromYM);
                 url.searchParams.set('to_year_month',   this.toYM);
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
             const selected = Object.keys(this.tables).filter(k => this.tables[k]);
             if (selected.length > 0) {
                 url.searchParams.set('tables', selected.join(','));
             }
             return url.toString();
         },

         atLeastOneSelected() {
             return Object.values(this.tables).some(v => v);
         }
     }"
     @keydown.escape.window="exportOpen = false">

    {{-- ── EXPORT MODAL ── --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Sales</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500">Choose which sections to include</p>
                    </div>
                </div>
                <button @click="exportOpen = false"
                        class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal Body: Checkboxes --}}
            <div class="px-6 py-5 space-y-3">
                <p class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">Select Sections to Export</p>

                {{-- Daily Report --}}
                <label class="flex items-start gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
                       :class="tables.daily_report ? 'border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-900/10' : ''">
                    <input type="checkbox" x-model="tables.daily_report"
                           class="mt-0.5 w-4 h-4 rounded text-emerald-500 border-slate-300 dark:border-slate-600 focus:ring-emerald-400 focus:ring-offset-0 cursor-pointer">
                    <div>
                        <div class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2V7"/></svg>
                            Daily Report
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Day-by-day sales, spend, ROAS &amp; platform breakdown</p>
                    </div>
                </label>

                {{-- Return Breakdown --}}
                <label class="flex items-start gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
                       :class="tables.return_breakdown ? 'border-rose-300 dark:border-rose-700 bg-rose-50/50 dark:bg-rose-900/10' : ''">
                    <input type="checkbox" x-model="tables.return_breakdown"
                           class="mt-0.5 w-4 h-4 rounded text-rose-500 border-slate-300 dark:border-slate-600 focus:ring-rose-400 focus:ring-offset-0 cursor-pointer">
                    <div>
                        <div class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 14l-4-4 4-4M15 10h5M3 10h1M6 18l6-6-6-6"/></svg>
                            Return Breakdown
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Returns by reason category per platform &amp; gender</p>
                    </div>
                </label>

                {{-- Weekly Breakdown --}}
                <label class="flex items-start gap-3.5 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
                       :class="tables.weekly_breakdown ? 'border-amber-300 dark:border-amber-700 bg-amber-50/50 dark:bg-amber-900/10' : ''">
                    <input type="checkbox" x-model="tables.weekly_breakdown"
                           class="mt-0.5 w-4 h-4 rounded text-amber-500 border-slate-300 dark:border-slate-600 focus:ring-amber-400 focus:ring-offset-0 cursor-pointer">
                    <div>
                        <div class="text-[13px] font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            Weekly Breakdown
                        </div>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Weekly aggregated sales, spend, orders &amp; returns per platform</p>
                    </div>
                </label>

                {{-- Warning if none selected --}}
                <div x-show="!atLeastOneSelected()" class="flex items-center gap-2 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-lg text-[12px] text-amber-700 dark:text-amber-400">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    Please select at least one section.
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="exportOpen = false"
                        class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">
                    Cancel
                </button>
                <a :href="exportUrl()"
                   :class="atLeastOneSelected() ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-40'"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition-colors text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    {{-- ── Page Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span class="w-8 h-8 bg-emerald-400/15 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </span>
                Sales Report
            </h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5 ml-10">Select a period, choose report sections, then export to Excel</p>
        </div>
        <div class="text-right shrink-0 hidden sm:block">
            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Current Date</p>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ now()->format('d M Y') }}</p>
        </div>
    </div>

    {{-- ── Filter + Export ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 shadow-sm">
        <p class="text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">Filter by Period</p>
        <div class="flex flex-wrap items-end gap-3">

            {{-- Period select --}}
            <div class="w-52">
                <label class="f-label">Period</label>
                <select class="f-input custom-select" x-model="period" @change="period !== 'custom' && submit()">
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option value="last_6_months">Last 6 Months</option>
                    <option value="last_1_year">Last 1 Year</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            {{-- Custom date pickers --}}
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

            {{-- Export Excel button --}}
            <div class="sm:ml-auto">
                <button type="button" @click="exportOpen = true"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export Excel
                </button>
            </div>

        </div>
    </div>

    {{-- ── Info Cards ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-800/50 rounded-xl p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2V7"/></svg>
            </div>
            <div>
                <p class="text-[12px] font-semibold text-emerald-700 dark:text-emerald-400">Daily Report</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Sales · Spend · ROAS · Platform columns</p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-rose-200 dark:border-rose-800/50 rounded-xl p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 14l-4-4 4-4M15 6h-5M15 18h-5"/></svg>
            </div>
            <div>
                <p class="text-[12px] font-semibold text-rose-600 dark:text-rose-400">Return Breakdown</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Return reasons · By platform · By gender</p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-800/50 rounded-xl p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </div>
            <div>
                <p class="text-[12px] font-semibold text-amber-600 dark:text-amber-400">Weekly Breakdown</p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Weekly totals · Sales vs Spend vs Returns</p>
            </div>
        </div>
    </div>

    {{-- ── Instructions ── --}}
    <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/40 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-4 h-4 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div class="text-[12px] text-blue-700 dark:text-blue-400">
            <span class="font-semibold">How to use:</span>
            Select your desired period using the filter above, then click <strong>Export Excel</strong>.
            A popup will let you choose which report sections to include —
            <em>Daily Report</em>, <em>Return Breakdown</em>, and/or <em>Weekly Breakdown</em> — before downloading the file.
        </div>
    </div>

</div>
@endsection

