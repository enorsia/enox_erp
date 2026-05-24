@extends('layouts.app')

@section('title', 'Sale Tracking')

@section('content')
<div id="sale-tracking-page"></div>

<div x-data="{
    drawerOpen: false,
    dateRange: '{{ request('date_range', '') }}',
    get isCustom() { return this.dateRange === 'custom'; },
    setRange(val) {
        this.dateRange = val;
        if (val !== 'custom') {
            if (window._fpFrom) window._fpFrom.clear();
            else { const el = document.getElementById('filter-date-from'); if (el) el.value = ''; }
            if (window._fpTo)   window._fpTo.clear();
            else { const el = document.getElementById('filter-date-to');   if (el) el.value = ''; }
        }
    }
}" @keydown.escape.window="drawerOpen = false">

    {{-- ── FILTER DRAWER BACKDROP ── --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"  x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="drawerOpen = false"
         class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

    {{-- ── FILTER DRAWER ── --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"  x-transition:leave-start="translate-x-0"   x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
         style="display:none;">

        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
            </div>
            <button @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="get" action="{{ route('admin.ads-performance.index') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Platform</p>
                    <select name="sale_platform_id" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200" data-placeholder="All Platforms">
                        <option value="">All Platforms</option>
                        @foreach($salePlatforms as $p)
                            <option value="{{ $p['id'] }}" {{ request('sale_platform_id') == $p['id'] ? 'selected' : '' }}>{!! $p['label'] !!}</option>
                        @endforeach
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date Range</p>
                    <input type="hidden" name="date_range" :value="dateRange" />
                    <div class="grid grid-cols-2 gap-1.5 mb-3">
                        @foreach([
                            'last_month'    => 'Last Month',
                            'last_3_months' => 'Last 3 Months',
                            'last_6_months' => 'Last 6 Months',
                            'last_year'     => 'Last 1 Year',
                            'custom'        => 'Custom Range',
                        ] as $val => $label)
                        <button type="button"
                                @click="setRange('{{ $val }}')"
                                :class="dateRange === '{{ $val }}'
                                    ? 'bg-accent-400 text-white border-accent-400 font-semibold shadow-sm'
                                    : 'bg-slate-50 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-600'"
                                class="px-2.5 py-2 text-[12px] rounded-lg border transition-colors text-center {{ $val === 'custom' ? 'col-span-2' : '' }}">
                            {{ $label }}
                        </button>
                        @endforeach
                        <button type="button"
                                @click="setRange('')"
                                :class="dateRange === ''
                                    ? 'bg-accent-400 text-white border-accent-400 font-semibold shadow-sm'
                                    : 'bg-slate-50 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-600'"
                                class="col-span-2 px-2.5 py-2 text-[12px] rounded-lg border transition-colors text-center">
                            All Time
                        </button>
                    </div>

                    {{-- Custom date range — flatpickr-powered date pickers --}}
                    <div x-show="isCustom" x-transition style="display:none;">
                        <div class="rounded-xl border border-slate-200 dark:border-slate-600 overflow-hidden bg-white dark:bg-slate-800 shadow-sm">

                            {{-- Start Date --}}
                            <div class="px-3.5 pt-3 pb-2.5">
                                <div class="flex items-center gap-1.5 mb-2">
                                    <div class="w-5 h-5 rounded-md bg-accent-400/15 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-3 h-3 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500">Start Date</span>
                                </div>
                                <input type="text" id="filter-date-from" name="date_from"
                                       data-default="{{ request('date_from') }}"
                                       value="{{ request('date_from') }}"
                                       placeholder="Select start date…"
                                       readonly
                                       class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 focus:ring-2 focus:ring-accent-400/20 transition cursor-pointer"/>
                            </div>

                            {{-- Divider arrow --}}
                            <div class="flex items-center gap-2.5 px-3.5 py-0.5">
                                <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700"></div>
                                <div class="w-6 h-6 rounded-full border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                                <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700"></div>
                            </div>

                            {{-- End Date --}}
                            <div class="px-3.5 pt-2.5 pb-3">
                                <div class="flex items-center gap-1.5 mb-2">
                                    <div class="w-5 h-5 rounded-md bg-accent-400/15 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-3 h-3 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500">End Date</span>
                                </div>
                                <input type="text" id="filter-date-to" name="date_to"
                                       data-default="{{ request('date_to') }}"
                                       value="{{ request('date_to') }}"
                                       placeholder="Select end date…"
                                       readonly
                                       class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 focus:ring-2 focus:ring-accent-400/20 transition cursor-pointer"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                <a href="{{ route('admin.ads-performance.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
                <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- ── PAGE CONTENT ── --}}
    <div class="p-5 lg:p-6">

        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Sale Tracking</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Monthly ad spend & revenue — grouped by month, newest first</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">

                {{-- Export --}}
                <a href="{{ route('admin.ads-performance.export') }}?{{ http_build_query(request()->except('page')) }}"
                   class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </a>

                {{-- Filters --}}
                @php
                    $hasDateFilter = request('date_range') || request('date_from') || request('date_to');
                    $af = collect([request('sale_platform_id')])->filter()->count() + ($hasDateFilter ? 1 : 0);
                @endphp
                <button @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $af > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($af > 0)<span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $af }}</span>@endif
                </button>

                @can('general.sale_tracking.create')
                    <a href="{{ route('admin.ads-performance.create') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add Records
                    </a>
                @endcan
            </div>
        </div>

        {{-- Active filter tags --}}
        @if(request('sale_platform_id') || request('date_range') || request('date_from') || request('date_to'))
        <div class="flex flex-wrap gap-2 mb-4">
            @if(request('sale_platform_id'))
                @php $pLabel = collect($salePlatforms)->firstWhere('id', request('sale_platform_id'))['label'] ?? request('sale_platform_id'); @endphp
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">Platform:</span> {!! strip_tags($pLabel) !!}
                    <a href="{{ request()->fullUrlWithQuery(['sale_platform_id' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            @if(request('date_range') && request('date_range') !== 'custom')
                @php $rangeLabels = ['last_month' => 'Last Month', 'last_3_months' => 'Last 3 Months', 'last_6_months' => 'Last 6 Months', 'last_year' => 'Last 1 Year']; @endphp
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">Range:</span> {{ $rangeLabels[request('date_range')] ?? request('date_range') }}
                    <a href="{{ request()->fullUrlWithQuery(['date_range' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            @if(request('date_from'))
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">From:</span> {{ request('date_from') }}
                    <a href="{{ request()->fullUrlWithQuery(['date_from' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            @if(request('date_to'))
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">To:</span> {{ request('date_to') }}
                    <a href="{{ request()->fullUrlWithQuery(['date_to' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            <a href="{{ route('admin.ads-performance.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
            </a>
        </div>
        @endif

        {{-- ── MONTH GROUPS ── --}}
        @if($records->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No sale tracking records found.</p>
                @can('general.sale_tracking.create')
                    <a href="{{ route('admin.ads-performance.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add First Records
                    </a>
                @endcan
            </div>
        @else
            <div class="space-y-6">
            @foreach($monthGroups as $mg)

            {{-- ══ MONTH GROUP CARD ══ --}}
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden">

                {{-- Month header --}}
                <div class="flex items-center justify-between flex-wrap gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/80">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-accent-400/15 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                        </div>
                        <div>
                            <p class="text-[15px] font-bold text-slate-800 dark:text-slate-100">{{ $mg['monthFormatted'] }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ count($mg['platformCards']) }} platform {{ Str::plural('entry', count($mg['platformCards'])) }}</p>
                        </div>
                    </div>
                    {{-- Month totals summary + edit button --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 px-2.5 py-1 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                            Rev £{{ number_format($mg['totalRevenue'], 2) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 px-2.5 py-1 rounded-full">
                            Ads Tax £{{ number_format($mg['totalCost'], 2) }}
                        </span>
                        @if($mg['totalReturn'] > 0)
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 px-2.5 py-1 rounded-full">
                                Return £{{ number_format($mg['totalReturn'], 2) }}
                            </span>
                        @endif
                        @if($mg['totalNetRev'] >= 0)
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-[#E6F3F0] dark:bg-accent-900/30 text-[#003D2B] dark:text-accent-300 px-2.5 py-1 rounded-full">
                                Net £{{ number_format($mg['totalNetRev'], 2) }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 px-2.5 py-1 rounded-full">
                                Net £{{ number_format($mg['totalNetRev'], 2) }}
                            </span>
                        @endif
                        @if($mg['roi'] !== null)
                            <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-[#FFDDC0] dark:bg-orange-900/30 text-[#7A3B00] dark:text-orange-300 px-2.5 py-1 rounded-full">
                                ROI {{ number_format($mg['roi']) }}%
                            </span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 px-2.5 py-1 rounded-full">
                            {{ number_format($mg['totalOrders']) }} orders
                        </span>
                        @can('general.sale_tracking.edit')
                            <a href="{{ route('admin.ads-performance.edit', $mg['entries']->first()->id) }}"
                               class="flex items-center gap-1.5 text-[12px] font-semibold px-2.5 py-1 rounded-full bg-accent-400/10 text-accent-600 dark:text-accent-300 hover:bg-accent-400/20 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                Edit Month
                            </a>
                        @endcan
                    </div>
                </div>

                {{-- Platform cards grid --}}
                <div class="p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    @foreach($mg['platformCards'] as $rec)
                    @php
                        $recRevenue    = $rec->computed_revenue ?? 0;
                        $recNetCost    = $rec->computed_net_cost ?? 0;
                        $recAdsTax     = (float) ($rec->ads_tax_payments ?? 0);
                        $recOrders     = $rec->computed_orders ?? 0;
                        $recProducts   = $rec->computed_products ?? 0;
                    @endphp
                    <div class="group bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 rounded-xl p-3.5 hover:border-accent-200 dark:hover:border-accent-700 hover:shadow-sm transition-all">

                        {{-- Card top: platform avatar + actions --}}
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-accent-400/20 flex items-center justify-center text-accent-600 dark:text-accent-400 text-[12px] font-bold flex-shrink-0">
                                    {{ strtoupper(substr($rec->salePlatform?->name ?? 'P', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-bold text-slate-800 dark:text-slate-100 truncate leading-tight">{{ $rec->salePlatform?->name ?? '—' }}</p>
                                    @if($rec->salePlatform?->parent)
                                        <p class="text-[10px] text-slate-400 truncate leading-tight mt-0.5">{{ $rec->salePlatform->parent->name }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                @can('general.sale_tracking.edit')
                                    <a href="{{ route('admin.ads-performance.edit', $rec->id) }}"
                                       class="w-6 h-6 rounded-md border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-blue-600 dark:text-blue-400 hover:bg-blue-100 transition-colors" title="Edit month">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                    </a>
                                @endcan
                                @can('general.sale_tracking.delete')
                                    <button onclick="deleteSaleTracking({{ $rec->id }})"
                                            class="w-6 h-6 rounded-md border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-500 dark:text-red-400 hover:bg-red-100 transition-colors" title="Delete">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                    <form id="delete-st-{{ $rec->id }}" method="POST"
                                                  action="{{ route('admin.ads-performance.destroy', $rec->id) }}" style="display:none;">
                                                @csrf @method('DELETE')
                                            </form>
                                @endcan
                            </div>
                        </div>

                        {{-- Revenue & Cost metrics --}}
                        <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                            <div class="bg-emerald-50 dark:bg-emerald-900/10 rounded-lg px-2.5 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[1px] text-emerald-600/70 dark:text-emerald-400/70">Revenue</p>
                                <p class="text-[13px] font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">£{{ number_format($recRevenue, 2) }}</p>
                            </div>
                            <div class="bg-amber-50 dark:bg-amber-900/10 rounded-lg px-2.5 py-1.5">
                                <p class="text-[9px] font-semibold uppercase tracking-[1px] text-amber-600/70 dark:text-amber-400/70">Net Cost</p>
                                <p class="text-[13px] font-bold text-amber-700 dark:text-amber-400 tabular-nums">£{{ number_format($recNetCost, 2) }}</p>
                            </div>
                            @if($recAdsTax > 0)
                            <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg px-2.5 py-1.5 col-span-2">
                                <p class="text-[9px] font-semibold uppercase tracking-[1px] text-slate-500/70 dark:text-slate-400/70">Ads Tax</p>
                                <p class="text-[13px] font-bold text-slate-700 dark:text-slate-300 tabular-nums">£{{ number_format($recAdsTax, 2) }}</p>
                            </div>
                            @endif
                        </div>

                        {{-- Reach / clicks / orders mini stats --}}
                        @php
                            $platform = $rec->salePlatform;
                            $statsToShow = [];
                            // Orders and Products come from DailySale
                            if ($recOrders > 0 || $recProducts > 0) {
                                if ($recOrders > 0)   $statsToShow[] = ['label' => 'Orders',   'val' => $recOrders];
                                if ($recProducts > 0) $statsToShow[] = ['label' => 'Products', 'val' => $recProducts];
                            }
                            if ($platform?->track_reach           ?? true) $statsToShow[] = ['label' => 'Reach',      'val' => $rec->reach];
                            if ($platform?->track_impressions      ?? true) $statsToShow[] = ['label' => 'Impressions','val' => $rec->impressions];
                            if ($platform?->track_clicks           ?? true) $statsToShow[] = ['label' => 'Clicks',     'val' => $rec->clicks];
                            if ($platform?->track_sessions         ?? true) $statsToShow[] = ['label' => 'Sessions',   'val' => $rec->sessions];
                            if ($platform?->track_engaged_sessions ?? true) $statsToShow[] = ['label' => 'Eng.Sess',   'val' => $rec->engaged_sessions];
                            if ($platform?->track_users            ?? true) $statsToShow[] = ['label' => 'Users',      'val' => $rec->users];
                        @endphp
                        @if(count($statsToShow) > 0)
                        <div class="grid gap-1 pt-2 border-t border-slate-100 dark:border-slate-700"
                             style="grid-template-columns: repeat({{ min(count($statsToShow), 4) }}, minmax(0, 1fr));">
                            @foreach($statsToShow as $stat)
                            <div class="text-center">
                                <p class="text-[9px] text-slate-400 uppercase tracking-[1px]">{{ $stat['label'] }}</p>
                                <p class="text-[12px] font-bold text-slate-700 dark:text-slate-300 tabular-nums">{{ $stat['val'] !== null ? number_format($stat['val']) : '—' }}</p>
                            </div>
                            @endforeach
                        </div>
                        @endif

                    </div>
                    @endforeach
                    </div>
                </div>

            </div>
            @endforeach
            </div>
        @endif

        @include('layouts.pagination', ['paginator' => $records])
    </div>
</div>
@endsection

@push('js')
<script>
function deleteSaleTracking(id) {
    if (confirm('Delete this sale tracking record? This cannot be undone.')) {
        document.getElementById('delete-st-' + id).submit();
    }
}
</script>
@endpush
