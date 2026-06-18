@extends('layouts.app')

@section('title', 'Daily Returns')

@section('content')
    @php $returnUrl = urlencode(request()->fullUrl()); @endphp
    <div id="daily-returns-page-content"></div>

    <div x-data="{
    drawerOpen: false,
    exportOpen: false,
    exportCols: @js(\App\Exports\DailyReturnExport::allColumns()),
    selectedCols: @js(\App\Exports\DailyReturnExport::allColumns()),
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
            <form method="get" action="{{ route('admin.daily-returns.index') }}" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Platform</p>
                        <select name="sale_platform_id" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200" data-placeholder="All Platforms">
                            <option value="">All Platforms</option>
                            @foreach($filterData['salePlatforms'] as $p)
                                <option value="{{ $p['id'] }}" {{ request('sale_platform_id') == $p['id'] ? 'selected' : '' }}>{!! $p['label'] !!}</option>
                            @endforeach
                        </select>
                    </div>
                    <hr class="border-slate-100 dark:border-slate-700"/>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Return Reason</p>
                        <select name="return_reason_type_id" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200" data-placeholder="All Reasons">
                            <option value="">All Reasons</option>
                            @foreach($filterData['reasonTypes'] as $reason)
                                <option value="{{ $reason->id }}" {{ request('return_reason_type_id') == $reason->id ? 'selected' : '' }}>{{ $reason->name }}</option>
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
                    <a href="{{ route('admin.daily-returns.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
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
                        <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Daily Returns</h3>
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
                    <a :href="'{{ route('admin.daily-returns.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
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
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Daily Returns</h1>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Records grouped by date — newest first</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="exportOpen = true"
                            class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 transition-colors font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>

                    <button @click="drawerOpen = true"
                            class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                        Filters
                        @if($activeFilterCount > 0)<span class="bg-accent-400 text-white text-[9px] font-bold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>@endif
                    </button>
                    @can('general.daily_return.create')
                        <a href="{{ route('admin.daily-returns.create') }}?return_url={{ $returnUrl }}"
                           class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add Daily Return
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Active filter tags --}}
            @if(!empty($activeFilterTags))
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($activeFilterTags as $tag)
                        <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                            <span class="font-semibold">{{ $tag['label'] }}:</span> {{ $tag['value'] }}
                            <a href="{{ request()->fullUrlWithQuery([$tag['param']=>null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px]">&times;</a>
                        </div>
                    @endforeach
                    <a href="{{ route('admin.daily-returns.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
                    </a>
                </div>
            @endif

            {{-- ── DATE-WISE GROUPS ── --}}
            {{-- ── DATE-WISE GROUPS ── --}}
            @if ($dailyReturns->isEmpty())
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                    </div>
                    <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No daily return records found.</p>
                    @can('general.daily_return.create')
                        <a href="{{ route('admin.daily-returns.create') }}?return_url={{ $returnUrl }}"
                           class="inline-flex items-center gap-2 mt-4 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add First Entry
                        </a>
                    @endcan
                </div>
            @else
                <div class="space-y-6" data-restore-scroll>
                    @foreach ($dateGroups as $dg)
                        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">

                            {{-- Date header --}}
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 bg-gradient-to-r from-slate-50/80 to-white dark:from-slate-800/80 dark:to-slate-800 border-b border-slate-200 dark:border-slate-700">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-accent-100 dark:bg-accent-900/30 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4 text-accent-600 dark:text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-[15px] font-bold text-slate-800 dark:text-slate-100">{{ $dg['dateFormatted'] }}</p>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{{ count($dg['entries']) }} return {{ Str::plural('entry', count($dg['entries'])) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg border border-blue-100 dark:border-blue-800/30">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                            {{ number_format($dg['totalReturns']) }} returns
                        </span>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 rounded-lg border border-amber-100 dark:border-amber-800/30">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            {{ number_format($dg['totalReturnQty']) }} qty
                        </span>
                                    @can('general.daily_return.edit')
                                        <a href="{{ route('admin.daily-returns.edit', $dg['entries']->first()->id) }}?return_url={{ $returnUrl }}"
                                           data-preserve-scroll
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400 hover:text-accent-600 dark:hover:text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/20 rounded-lg transition-colors border border-slate-200 dark:border-slate-600 hover:border-accent-200">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                                            Edit Date
                                        </a>
                                    @endcan
                                </div>
                            </div>

                            {{-- Platform groups with tree structure --}}
                            <div class="p-5 space-y-5">
                                @foreach ($dg['rootGroups'] as $rootGroup)
                                    <div>
                                        {{-- Root platform header --}}
                                        <div class="flex items-center gap-3 mb-3">
                                            <div class="w-2 h-2 rounded-full bg-accent-500 dark:bg-accent-400 shrink-0"></div>
                                            <span class="text-[13px] font-bold text-slate-700 dark:text-slate-200">{{ $rootGroup['name'] }}</span>
                                            <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700/60"></div>
                                            <span class="inline-flex items-center gap-3 text-[11px] font-medium text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                        {{ number_format($rootGroup['totals']['returns']) }}
                                    </span>
                                    <span class="text-slate-300 dark:text-slate-600">·</span>
                                    <span class="inline-flex items-center gap-1">
                                        <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                        {{ number_format($rootGroup['totals']['qty']) }}
                                    </span>
                                </span>
                                        </div>

                                        {{-- Render tree rows --}}
                                        <div class="flex flex-col space-y-0.5">
                                            @foreach ($rootGroup['rows'] as $row)
                                                @if ($row['type'] === 'platform')
                                                    @php $pad = ($row['depth'] - 1) * 20 + 20; @endphp
                                                    <div class="dr-row flex items-center gap-2 py-1.5 px-2 rounded-md transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30
                                            {{ $row['isSub'] ? 'dr-child hidden' : '' }}
                                            {{ $row['hasContent'] ? 'cursor-pointer' : '' }}
                                            {{ $row['showBorder'] ? 'mt-1 pt-2 border-t border-slate-100 dark:border-slate-700/50' : '' }}"
                                                         style="padding-left: {{ $pad }}px;"
                                                         data-dr-key="{{ $row['key'] }}"
                                                         data-dr-parent="{{ $row['parent'] }}"
                                                         @if($row['hasContent']) onclick="toggleDrRow(event, '{{ $row['key'] }}')" @endif>
                                                        @if ($row['hasContent'])
                                                            <svg class="w-3 h-3 text-slate-400 dr-toggle-icon shrink-0 transition-transform duration-200" style="transform: rotate(-90deg);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 9l-7 7-7-7"/></svg>
                                                        @else
                                                            <span class="w-3 shrink-0"></span>
                                                        @endif
                                                        <div class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600 shrink-0"></div>
                                                        <span class="text-[12px] font-medium text-slate-600 dark:text-slate-300 {{ $row['isSub'] ? 'text-[11px]' : 'text-[12px]' }}">{{ $row['name'] }}</span>
                                                    </div>
                                                @else
                                                    @php $return = $row['entry']; $pad = $row['depth'] * 20 + 20; @endphp
                                                    <div class="dr-row {{ $row['alwaysVisible'] ? '' : 'dr-child hidden' }}"
                                                         data-dr-parent="{{ $row['parent'] }}"
                                                         style="padding-left: {{ $pad }}px;">
                                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 py-1.5 px-3 rounded-lg hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors group text-[11px] border border-transparent hover:border-slate-200 dark:hover:border-slate-700/50">
                                                <span class="inline-flex items-center gap-1.5 font-semibold text-rose-600 dark:text-rose-400 shrink-0 min-w-[130px]">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                                    {{ $return->returnReasonType->name ?? '—' }}
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Returns <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ number_format($return->number_of_returns) }}</strong>
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Qty <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ number_format($return->number_of_return_quantities) }}</strong>
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Amount <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">£{{ number_format($return->return_amount, 2) }}</strong>
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Male <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ number_format($return->number_of_male_returns ?? 0) }}</strong>
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Female <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ number_format($return->number_of_female_returns ?? 0) }}</strong>
                                                </span>
                                                            <span class="text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                    Kids <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ number_format($return->number_of_kids_returns ?? 0) }}</strong>
                                                </span>
                                                            <div class="flex-1"></div>
                                                            <div class="flex gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                @can('general.daily_return.show')
                                                                    <a href="{{ route('admin.daily-returns.show', $return->id) }}" class="p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="View">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                                                    </a>
                                                                @endcan
                                                                @can('general.daily_return.delete')
                                                                    <button onclick="deleteData({{ $return->id }})" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition-colors" title="Delete">
                                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                                    </button>
                                                                    <form id="delete-form-{{ $return->id }}" method="POST" action="{{ route('admin.daily-returns.destroy', $return->id) }}" style="display:none;">
                                                                        @csrf @method('DELETE')
                                                                    </form>
                                                                @endcan
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @include('layouts.pagination', ['paginator' => $dailyReturns])
        </div>
    </div>

    <script>
        function toggleDrRow(event, key) {
            const row = event.currentTarget;
            const icon = row.querySelector('.dr-toggle-icon');
            const isExpanded = icon.style.transform === 'rotate(0deg)';

            // Toggle this row's icon
            icon.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';

            // Toggle all direct children of this row
            const children = document.querySelectorAll(`.dr-row[data-dr-parent="${key}"]`);
            children.forEach(child => {
                if (isExpanded) {
                    child.classList.add('hidden');
                } else {
                    child.classList.remove('hidden');
                }
            });
        }
    </script>
@endsection