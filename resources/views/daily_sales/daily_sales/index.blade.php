@extends('layouts.app')

@section('title', 'Daily Sales')

@section('content')
<div id="daily-sales-page-content"></div>

{{-- ── Alpine Root ── --}}
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

    {{-- ── FILTER DRAWER PANEL ── --}}
    <div x-show="drawerOpen"
         x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
         style="display:none;">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
            <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                Filters
            </div>
            <button @click="drawerOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="get" action="{{ route('admin.daily-sales.index') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Platform</p>
                    <select name="sale_platform_id" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Platforms">
                        <option value="">All Platforms</option>
                        @foreach($salePlatforms as $platform)
                            <option value="{{ $platform['id'] }}" {{ request('sale_platform_id') == $platform['id'] ? 'selected' : '' }}>{!! $platform['label'] !!}</option>
                        @endforeach
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date From</p>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Date To</p>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                <a href="{{ route('admin.daily-sales.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
                <button type="submit" class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Apply Filters</button>
            </div>
        </form>
    </div>

    {{-- ── EXPORT MODAL ── --}}
    <div x-show="exportOpen" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="exportOpen = false"></div>
        <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 dark:border-slate-700"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Daily Sales</h3>
                </div>
                <button @click="exportOpen = false" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-[12px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Select Columns</p>
                    <div class="flex gap-2">
                        <button type="button" @click="toggleAll(true)" class="text-[11px] text-accent-500 hover:text-accent-700 font-medium">All</button>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <button type="button" @click="toggleAll(false)" class="text-[11px] text-slate-400 hover:text-slate-600 font-medium">None</button>
                    </div>
                </div>
                @php $exportLabels = \App\Exports\DailySaleExport::columnLabels(); $exportCols = \App\Exports\DailySaleExport::allColumns(); @endphp
                <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto">
                    @foreach($exportCols as $col)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
                            <input type="checkbox" :checked="selectedCols.includes('{{ $col }}')"
                                   @change="selectedCols.includes('{{ $col }}') ? selectedCols.splice(selectedCols.indexOf('{{ $col }}'), 1) : selectedCols.push('{{ $col }}')"
                                   class="w-3.5 h-3.5 rounded text-accent-400 border-slate-300 focus:ring-accent-400">
                            <span class="text-[12px] text-slate-600 dark:text-slate-300">{{ $exportLabels[$col] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-2.5 px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                <button @click="exportOpen = false" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 transition-colors font-medium">Cancel</button>
                <a :href="'{{ route('admin.daily-sales.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
                   class="flex-[2] py-2.5 text-[13px] rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-semibold transition-colors text-center flex items-center justify-center gap-2">
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
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all daily sales records</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="exportOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                @php $activeFilters = collect([request('sale_platform_id'), request('date_from'), request('date_to')])->filter()->count(); @endphp
                <button type="button" @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilters > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($activeFilters > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilters }}</span>
                    @endif
                </button>
                @can('general.daily_sale.create')
                    <a href="{{ route('admin.daily-sales.create') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Add Daily Sale
                    </a>
                @endcan
            </div>
        </div>

        {{-- ── ACTIVE FILTER TAGS ── --}}
        @if(request('sale_platform_id') || request('date_from') || request('date_to'))
            <div class="flex flex-wrap gap-2 mb-4">
                @if(request('sale_platform_id'))
                    @php $platformLabel = collect($salePlatforms)->firstWhere('id', request('sale_platform_id'))['label'] ?? request('sale_platform_id'); @endphp
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Platform:</span> {!! strip_tags($platformLabel) !!}
                        <a href="{{ request()->fullUrlWithQuery(['sale_platform_id' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('date_from'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">From:</span> {{ request('date_from') }}
                        <a href="{{ request()->fullUrlWithQuery(['date_from' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('date_to'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">To:</span> {{ request('date_to') }}
                        <a href="{{ request()->fullUrlWithQuery(['date_to' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                <a href="{{ route('admin.daily-sales.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
                </a>
            </div>
        @endif

        {{-- ── PREMIUM CARD VIEW ── --}}
        @if ($dailySales->isEmpty())
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
                <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                <p class="text-sm text-slate-400 dark:text-slate-500">No daily sales records found.</p>
            </div>
        @else
            @foreach ($viewGroups as $yearGroup)
                {{-- ── Year Section ── --}}
                <div class="mb-8">
                    {{-- Year Header --}}
                    <div class="flex items-center gap-3 mb-4">
                        <div class="ds-year-badge">
                            <svg class="w-3.5 h-3.5 opacity-80" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
                            {{ $yearGroup['year'] }}
                        </div>
                        <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400 shrink-0">
                            <span>Sales: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($yearGroup['yearTotalSales'], 2) }}</strong></span>
                            <span>Orders: <strong class="text-slate-700 dark:text-slate-200">{{ number_format($yearGroup['yearTotalOrders']) }}</strong></span>
                        </div>
                    </div>

                    {{-- Month Groups --}}
                    @foreach ($yearGroup['monthGroups'] as $monthGroup)
                        <div class="ml-3 mb-5">
                            {{-- Month Header --}}
                            <div class="flex items-center gap-2.5 mb-3">
                                <span class="ds-month-pill">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                                    {{ $monthGroup['monthName'] }} {{ $monthGroup['year'] }}
                                </span>
                                <div class="flex-1 h-px bg-slate-100 dark:bg-slate-700/70"></div>
                                <div class="flex items-center gap-3 text-[10px] text-slate-400 dark:text-slate-500 shrink-0">
                                    <span>Sales: <strong class="text-slate-600 dark:text-slate-300">{{ number_format($monthGroup['monthTotalSales'], 2) }}</strong></span>
                                    <span class="hidden sm:inline">Spent: <strong class="text-slate-600 dark:text-slate-300">{{ number_format($monthGroup['monthTotalSpent'], 2) }}</strong></span>
                                    <span>Orders: <strong class="text-slate-600 dark:text-slate-300">{{ number_format($monthGroup['monthTotalOrders']) }}</strong></span>
                                </div>
                            </div>

                            {{-- Platform Groups --}}
                            @foreach ($monthGroup['platformGroups'] as $platformGroup)
                                @if($platformGroup['parentPlatform'])
                                    <div class="ds-platform-group-header">{{ $platformGroup['parentPlatform']['name'] }}</div>
                                @endif

                                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 @if($platformGroup['parentPlatform']) ml-4 @endif mb-3">
                                    @foreach ($platformGroup['sales'] as $sale)
                                        <div class="ds-sale-card">
                                            <div class="flex items-start justify-between gap-2 mb-3">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <div class="w-8 h-8 rounded-lg bg-accent-50 dark:bg-accent-900/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/></svg>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-[13px] font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $sale->salePlatform->name ?? 'N/A' }}</p>
                                                        <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ $sale->date ? $sale->date->format('d M Y') : 'N/A' }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex gap-1 shrink-0">
                                                    @can('general.daily_sale.show')
                                                        <a href="{{ route('admin.daily-sales.show', $sale->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="View">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        </a>
                                                    @endcan
                                                    @can('general.daily_sale.edit')
                                                        <a href="{{ route('admin.daily-sales.edit', $sale->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors" title="Edit">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                        </a>
                                                    @endcan
                                                    @can('general.daily_sale.delete')
                                                        <button onclick="deleteData({{ $sale->id }})" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 hover:border-red-200 transition-colors" title="Delete">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        </button>
                                                        <form id="delete-form-{{ $sale->id }}" method="POST" action="{{ route('admin.daily-sales.destroy', $sale->id) }}" style="display:none;">
                                                            @csrf @method('DELETE')
                                                        </form>
                                                    @endcan
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-1.5">
                                                <div class="ds-metric-tile success">
                                                    <span class="ds-metric-label">Sales (£)</span>
                                                    <span class="ds-metric-value">{{ number_format($sale->sales, 2) }}</span>
                                                </div>
                                                <div class="ds-metric-tile">
                                                    <span class="ds-metric-label">Spent (£)</span>
                                                    <span class="ds-metric-value">{{ number_format($sale->spent, 2) }}</span>
                                                </div>
                                                <div class="ds-metric-tile accent">
                                                    <span class="ds-metric-label">Orders</span>
                                                    <span class="ds-metric-value">{{ number_format($sale->number_of_orders) }}</span>
                                                </div>
                                                <div class="ds-metric-tile">
                                                    <span class="ds-metric-label">Qty</span>
                                                    <span class="ds-metric-value">{{ number_format($sale->number_of_quantities) }}</span>
                                                </div>
                                            </div>

                                            @if(($sale->number_of_male_orders ?? 0) + ($sale->number_of_female_orders ?? 0) + ($sale->number_of_kids_orders ?? 0) > 0)
                                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                                                    @if($sale->number_of_male_orders)
                                                        <span class="text-[10px] text-slate-400">M: <strong class="text-slate-600 dark:text-slate-300">{{ $sale->number_of_male_orders }}</strong></span>
                                                    @endif
                                                    @if($sale->number_of_female_orders)
                                                        <span class="text-[10px] text-slate-400">F: <strong class="text-slate-600 dark:text-slate-300">{{ $sale->number_of_female_orders }}</strong></span>
                                                    @endif
                                                    @if($sale->number_of_kids_orders)
                                                        <span class="text-[10px] text-slate-400">K: <strong class="text-slate-600 dark:text-slate-300">{{ $sale->number_of_kids_orders }}</strong></span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        @include('layouts.pagination', ['paginator' => $dailySales])
    </div>
</div>
@endsection

