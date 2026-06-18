@extends('layouts.app')

@section('title', 'Daily Sales')

@section('content')
@php $return_url = urlencode(request()->fullUrl()); @endphp
<div id="daily-sales-page-content"></div>

<div x-data="{
    drawerOpen: false,
    exportOpen: false,
    exportCols: @js(\App\Exports\DailySaleExport::allColumns()),
    selectedCols: @js(\App\Exports\DailySaleExport::allColumns()),
    toggleAll(checked) { this.selectedCols = checked ? [...this.exportCols] : []; }
}" @keydown.escape.window="drawerOpen = false; exportOpen = false">

    {{-- ── FILTER DRAWER BACKDROP ── --}}
    <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="drawerOpen = false"
         class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

    {{-- ── FILTER DRAWER ── --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
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
        <form method="get" action="{{ route('admin.daily-sales.index') }}" class="flex-1 flex flex-col overflow-hidden">
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
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date From</p>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400"/>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date To</p>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400"/>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                <a href="{{ route('admin.daily-sales.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
                <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- ── EXPORT MODAL ── --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Daily Sales</h3>
                </div>
                <button @click="exportOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[12px] font-semibold text-slate-500 uppercase tracking-wider">Select Columns</p>
                    <div class="flex gap-2">
                        <button type="button" @click="toggleAll(true)" class="text-[11px] text-accent-500 hover:text-accent-700 font-medium">All</button>
                    </div>
                </div>
                @php $exportLabels = \App\Exports\DailySaleExport::columnLabels(); $exportCols = \App\Exports\DailySaleExport::allColumns(); @endphp
                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                    @foreach($exportCols as $col)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer">
                            <input type="checkbox" :checked="selectedCols.includes('{{ $col }}')"
                                   @change="selectedCols.includes('{{ $col }}') ? selectedCols.splice(selectedCols.indexOf('{{ $col }}'), 1) : selectedCols.push('{{ $col }}')"
                                   class="w-3.5 h-3.5 rounded text-accent-400 border-slate-300">
                            <span class="text-[12px] text-slate-600 dark:text-slate-300">{{ $exportLabels[$col] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="exportOpen = false" class="flex-1 py-2.5 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 font-medium">Cancel</button>
                <a :href="'{{ route('admin.daily-sales.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold text-center flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    {{-- ── PAGE CONTENT ── --}}
    <div class="p-5 lg:p-6">

        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Daily Sales</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Records grouped by date — newest first</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button @click="exportOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                @php $af = collect([request('sale_platform_id'), request('date_from'), request('date_to')])->filter()->count(); @endphp
                <button @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $af > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($af > 0)<span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $af }}</span>@endif
                </button>
                @can('general.daily_sale.create')
                    <a href="{{ route('admin.daily-sales.create') }}?return_url={{ $return_url }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add Daily Sale
                    </a>
                @endcan
            </div>
        </div>

        {{-- Active filter tags --}}
        @if(request('sale_platform_id') || request('date_from') || request('date_to'))
        <div class="flex flex-wrap gap-2 mb-4">
            @if(request('sale_platform_id'))
                @php $pLabel = collect($salePlatforms)->firstWhere('id', request('sale_platform_id'))['label'] ?? request('sale_platform_id'); @endphp
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">Platform:</span> {!! strip_tags($pLabel) !!}
                    <a href="{{ request()->fullUrlWithQuery(['sale_platform_id'=>null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            @if(request('date_from'))
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">From:</span> {{ request('date_from') }}
                    <a href="{{ request()->fullUrlWithQuery(['date_from'=>null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            @if(request('date_to'))
                <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                    <span class="font-semibold">To:</span> {{ request('date_to') }}
                    <a href="{{ request()->fullUrlWithQuery(['date_to'=>null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                </div>
            @endif
            <a href="{{ route('admin.daily-sales.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
            </a>
        </div>
        @endif

        {{-- ── DATE-WISE GROUPS ── --}}
        @if ($dailySales->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                </div>
                <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No daily sales records found.</p>
                @can('general.daily_sale.create')
                    <a href="{{ route('admin.daily-sales.create') }}?return_url={{ $return_url }}"
                       class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add First Entry
                    </a>
                @endcan
            </div>
        @else
            <div class="space-y-6" data-restore-scroll>
            @foreach ($dateGroups as $dg)

            {{-- ══ DATE GROUP CARD ══ --}}
            <div class="ds-date-card">

                {{-- Date header --}}
                <div class="ds-date-header">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="ds-date-icon-wrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                        </div>
                        <div>
                            <p class="text-[15px] font-bold text-slate-800 dark:text-slate-100">{{ $dg['dateFormatted'] }}</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ count($dg['entries']) }} platform {{ Str::plural('entry', count($dg['entries'])) }}</p>
                        </div>
                    </div>
                    {{-- Totals summary pills --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="ds-total-pill green">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75"/></svg>
                            Sales : £{{ number_format($dg['totalSales'], 2) }}
                        </span>
                        <span class="ds-total-pill amber">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                            Spent : £{{ number_format($dg['totalSpent'], 2) }}
                        </span>
                        <span class="ds-total-pill blue">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                            Orders : {{ number_format($dg['totalOrders']) }}
                        </span>
                        @can('general.daily_sale.edit')
                            <a href="{{ route('admin.daily-sales.edit', $dg['entries']->first()->id) }}?return_url={{ $return_url }}"
                               data-preserve-scroll
                               class="ds-edit-date-btn">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                Edit Date
                            </a>
                        @endcan
                    </div>
                </div>

                {{-- Platform groups (3-level hierarchy: root → mid → cards) --}}
                <div class="p-4 space-y-5">
                @foreach ($dg['rootGroups'] as $rg)

                    <div>
                        {{-- ── Root platform header ── --}}
                        <div class="flex items-center gap-2 mb-3">
                            <div class="ds-group-dot root"></div>
                            <span class="ds-group-label root">{{ $rg['rootName'] }}</span>
                            <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700/60 ml-1"></div>
                        </div>

                        @foreach ($rg['subGroups'] as $sg)
                        <div class="{{ $sg['subName'] ? 'ml-5 mb-4' : 'mb-3' }}">

                            @if($sg['subName'])
                            {{-- ── Level-2 sub-group header ── --}}
                            <div class="flex items-center gap-2 mb-2.5">
                                <div class="ds-group-dot child"></div>
                                <span class="ds-group-label child">{{ $sg['subName'] }}</span>
                                <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700/60 ml-1"></div>
                            </div>
                            @endif

                            {{-- Platform entry cards --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 {{ $sg['subName'] ? 'ml-5' : '' }}">
                            @foreach ($sg['entries'] as $sale)
                                @php
                                    $hasGender = (($sale->number_of_male_orders ?? 0) + ($sale->number_of_female_orders ?? 0) + ($sale->number_of_kids_orders ?? 0)) > 0;
                                    $roi = $sale->spent > 0 ? round((($sale->sales - $sale->spent) / $sale->spent) * 100, 1) : null;
                                @endphp
                                <div class="ds-sale-card group">
                                    {{-- Card top: platform name + actions --}}
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="ds-platform-avatar">
                                                {{ strtoupper(substr($sale->salePlatform->name ?? 'P', 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[13px] font-bold text-slate-800 dark:text-slate-100 truncate leading-tight">{{ $sale->salePlatform->name ?? 'N/A' }}</p>
                                                @if($sale->salePlatform?->parent)
                                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate leading-tight mt-0.5">
                                                        <span class="inline-block w-2 h-px bg-slate-300 dark:bg-slate-600 align-middle mr-1"></span>{{ $sale->salePlatform->parent->name }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                            @can('general.daily_sale.show')
                                                <a href="{{ route('admin.daily-sales.show', $sale->id) }}" class="ds-action-btn" title="View">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                            @endcan
                                            @can('general.daily_sale.delete')
                                                <button onclick="deleteData({{ $sale->id }})" class="ds-action-btn danger" title="Delete">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                                <form id="delete-form-{{ $sale->id }}" method="POST" action="{{ route('admin.daily-sales.destroy', $sale->id) }}" style="display:none;">
                                                    @csrf @method('DELETE')
                                                </form>
                                            @endcan
                                        </div>
                                    </div>

                                    {{-- Main metrics --}}
                                    <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                                        <div class="ds-metric-cell sales-cell">
                                            <span class="ds-metric-lbl">Sales</span>
                                            <span class="ds-metric-val">£{{ number_format($sale->sales, 2) }}</span>
                                        </div>
                                        <div class="ds-metric-cell spent-cell">
                                            <span class="ds-metric-lbl">Spent</span>
                                            <span class="ds-metric-val">£{{ number_format($sale->spent, 2) }}</span>
                                        </div>
                                        <div class="ds-metric-cell orders-cell">
                                            <span class="ds-metric-lbl">Orders</span>
                                            <span class="ds-metric-val">{{ number_format($sale->number_of_orders) }}</span>
                                        </div>
                                        <div class="ds-metric-cell qty-cell">
                                            <span class="ds-metric-lbl">Qty</span>
                                            <span class="ds-metric-val">{{ number_format($sale->number_of_quantities) }}</span>
                                        </div>
                                    </div>

                                    {{-- ROI badge --}}
                                    @if($roi !== null)
                                    <div class="flex items-center gap-1.5 mt-2 mb-1.5">
                                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $roi >= 0 ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400' : 'bg-red-50 dark:bg-red-900/20 text-red-500 dark:text-red-400' }}">
                                            ROI {{ $roi >= 0 ? '+' : '' }}{{ $roi }}%
                                        </span>
                                    </div>
                                    @endif

                                    {{-- Gender breakdown --}}
                                    @if($hasGender)
                                    <div class="ds-gender-strip">
                                        <div class="ds-gender-item male">
                                            <span>Male</span>
                                            <span>{{ number_format($sale->number_of_male_orders ?? 0) }}</span>
                                        </div>
                                        <div class="ds-gender-item female">
                                            <span>Female</span>
                                            <span>{{ number_format($sale->number_of_female_orders ?? 0) }}</span>
                                        </div>
                                        <div class="ds-gender-item kids">
                                            <span>Kids</span>
                                            <span>{{ number_format($sale->number_of_kids_orders ?? 0) }}</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>

                @endforeach
                </div>

            </div>
            @endforeach
            </div>
        @endif

        @include('layouts.pagination', ['paginator' => $dailySales])
    </div>
</div>
@endsection

