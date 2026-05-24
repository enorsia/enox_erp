@extends('layouts.app')

@section('title', 'Platforms')

@section('content')
<div id="sale-platform-page-content"></div>

{{-- ── Alpine Root ── --}}
<div x-data="{
    drawerOpen: false,
    exportOpen: false,
    exportCols: @js(\App\Exports\SalePlatformExport::allColumns()),
    selectedCols: @js(\App\Exports\SalePlatformExport::allColumns()),
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
        <form method="get" action="{{ route('admin.sale-platforms.index') }}" class="flex-1 flex flex-col overflow-hidden">
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Search</p>
                    <div class="relative">
                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or slug…"
                               class="w-full pl-8 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"/>
                    </div>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Type</p>
                    <select name="type" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Types">
                        <option value="">All Types</option>
                        @foreach($channel_lists as $key => $item)
                            <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $item)) }}</option>
                        @endforeach
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Status</p>
                    <select name="is_active" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All Status">
                        <option value="">All Status</option>
                        <option value="1" {{ request('is_active') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Show in Analytics</p>
                    <select name="show_in_analytics" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All">
                        <option value="">All</option>
                        <option value="1" {{ request('show_in_analytics') == '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ request('show_in_analytics') == '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <hr class="border-slate-100 dark:border-slate-700"/>
                <div>
                    <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Show in Sale Tracking</p>
                    <select name="show_in_sale_tracking" class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors" data-placeholder="All">
                        <option value="">All</option>
                        <option value="1" {{ request('show_in_sale_tracking') == '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ request('show_in_sale_tracking') == '0' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                <a href="{{ route('admin.sale-platforms.index') }}" class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">Reset</a>
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
                    <h3 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100">Export Platforms</h3>
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
                    </div>
                </div>
                @php $exportLabels = \App\Exports\SalePlatformExport::columnLabels(); $exportCols = \App\Exports\SalePlatformExport::allColumns(); @endphp
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
                <a :href="'{{ route('admin.sale-platforms.export') }}?' + new URLSearchParams(Object.assign({}, Object.fromEntries(new URLSearchParams('{{ http_build_query(request()->except('page')) }}')), {columns: selectedCols.join(',')})).toString()"
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
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Platforms</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage all platforms and their hierarchy</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="exportOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-emerald-200 dark:border-emerald-700 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
                @php $activeFilters = collect([request('search'), request('type'), request('is_active'), request('show_in_analytics'), request('show_in_sale_tracking')])->filter(fn($v) => $v !== null && $v !== '')->count(); @endphp
                <button type="button" @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilters > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                    Filters
                    @if($activeFilters > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilters }}</span>
                    @endif
                </button>
                @can('general.sale_platform.create')
                    <a href="{{ route('admin.sale-platforms.create') }}"
                       class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Create Sale Platform
                    </a>
                @endcan
            </div>
        </div>

        {{-- ── STATS ROW ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Total</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Active</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['active'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Inactive</p>
                <p class="text-2xl font-bold text-red-500 dark:text-red-400">{{ $stats['inactive'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Types</p>
                <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $stats['types']->count() }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-blue-400 dark:text-blue-500 font-medium mb-1">In Analytics</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['show_in_analytics'] }}</p>
            </div>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3.5">
                <p class="text-[11px] uppercase tracking-wider text-violet-400 dark:text-violet-500 font-medium mb-1">In Sale Tracking</p>
                <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ $stats['show_in_sale_tracking'] }}</p>
            </div>
        </div>

        {{-- ── ACTIVE FILTER TAGS ── --}}
        @if(request('search') || request('type') || (request('is_active') !== null && request('is_active') !== '') || (request('show_in_analytics') !== null && request('show_in_analytics') !== '') || (request('show_in_sale_tracking') !== null && request('show_in_sale_tracking') !== ''))
            <div class="flex flex-wrap gap-2 mb-4">
                @if(request('search'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Search:</span> {{ request('search') }}
                        <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('type'))
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Type:</span> {{ ucfirst(str_replace('_', ' ', request('type'))) }}
                        <a href="{{ request()->fullUrlWithQuery(['type' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('is_active') !== null && request('is_active') !== '')
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Status:</span> {{ request('is_active') == '1' ? 'Active' : 'Inactive' }}
                        <a href="{{ request()->fullUrlWithQuery(['is_active' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('show_in_analytics') !== null && request('show_in_analytics') !== '')
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Analytics:</span> {{ request('show_in_analytics') == '1' ? 'Yes' : 'No' }}
                        <a href="{{ request()->fullUrlWithQuery(['show_in_analytics' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                @if(request('show_in_sale_tracking') !== null && request('show_in_sale_tracking') !== '')
                    <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                        <span class="font-semibold">Sale Tracking:</span> {{ request('show_in_sale_tracking') == '1' ? 'Yes' : 'No' }}
                        <a href="{{ request()->fullUrlWithQuery(['show_in_sale_tracking' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                    </div>
                @endif
                <a href="{{ route('admin.sale-platforms.index') }}" class="flex items-center gap-1 text-[11px] text-slate-400 hover:text-red-500 px-2 py-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg> Clear all
                </a>
            </div>
        @endif

        {{-- ── FILTER NOTICE ── --}}
        @if ($is_filtered)
            <div class="flex items-center gap-2 mb-4 px-3 py-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-lg text-[12px] text-amber-700 dark:text-amber-400">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                Filtered results — hierarchy view disabled. <a href="{{ route('admin.sale-platforms.index') }}" class="underline underline-offset-2 ml-1">Clear filters</a> to see full tree.
            </div>
        @endif

        {{-- ── PLATFORM TREE / LIST ── --}}
        <div class="flex flex-col gap-0" id="platform-tree-container">
            @forelse ($flat_list as $platform)
                @php
                    $depth       = $platform->depth ?? 0;
                    $hasChildren = $platform->has_children ?? false;
                    $isActive    = $platform->is_active;
                    $typeColors  = [
                        'channel'     => 'badge-blue',
                        'sub_channel' => 'badge-purple',
                        'marketplace' => 'badge-orange',
                        'region'      => 'badge-teal',
                    ];
                    $typeColor  = $typeColors[$platform->type] ?? 'badge-blue';
                    $platformId = 'platform_' . $platform->id;
                @endphp

                <div class="relative flex items-stretch platform-item
                        @if($depth === 0) mt-3 @else mt-0 @endif
                        @if($depth > 0) platform-child hidden @else platform-parent @endif"
                     data-platform-id="{{ $platformId }}"
                     data-depth="{{ $depth }}"
                     data-parent-id="@if($depth > 0){{ $platform->parent_id }}@endif"
                     style="padding-left: {{ $depth * 24 }}px;">

                    @if ($depth > 0)
                        <div class="absolute left-0 top-0 bottom-0 flex"
                             style="left: {{ ($depth - 1) * 24 + 10 }}px; width: 14px;">
                            <div class="w-px bg-slate-200 dark:bg-slate-700 @if ($platform->is_last_child) h-5 @else h-full @endif self-start"></div>
                            <div class="w-3.5 h-px bg-slate-200 dark:bg-slate-700 mt-5 shrink-0"></div>
                        </div>
                    @endif

                    <div class="flex-1 min-w-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
                             @if($depth === 0) rounded-xl shadow-sm @else rounded-lg @endif
                             p-3.5 grid grid-cols-[auto_1fr_auto] gap-3 items-center mb-px platform-card
                             @if($hasChildren) cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors @endif"
                         @if($hasChildren) onclick="togglePlatformCollapse(event, '{{ $platformId }}')" @endif>

                        <div class="flex flex-col items-center gap-0.5">
                            @if ($hasChildren)
                                <div class="collapse-toggle-icon w-7 h-7 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center transition-transform duration-200" style="transform: rotate(-90deg);">
                                    <svg class="w-3.5 h-3.5 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            @elseif($depth === 0)
                                <div class="w-7 h-7 rounded-lg bg-accent-50 dark:bg-accent-900/30 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5 text-accent-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                                </div>
                            @elseif ($depth === 1)
                                <div class="w-6 h-6 rounded-md bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                </div>
                            @else
                                <div class="w-5 h-5 rounded flex items-center justify-center">
                                    <div class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                                <span class="font-semibold text-slate-800 dark:text-slate-100 @if($depth === 0) text-[14px] @elseif($depth === 1) text-[13px] @else text-[12px] @endif">{{ $platform->name }}</span>
                                <span class="badge-custom {{ $typeColor }} text-[10px]">{{ ucfirst(str_replace('_', ' ', $platform->type)) }}</span>
                                @if ($isActive)
                                    <span class="badge-custom badge-green text-[10px]">Active</span>
                                @else
                                    <span class="badge-custom badge-red text-[10px]">Inactive</span>
                                @endif
                                @if ($hasChildren)
                                    <span class="inline-flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500 bg-slate-100 dark:bg-slate-700/60 px-1.5 py-0.5 rounded">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                                        {{ $platform->children_count }} {{ Str::plural('child', $platform->children_count) }}
                                    </span>
                                @endif
                            </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">{{ $platform->slug }}</span>
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500">Sort: {{ $platform->sort_order }}</span>
                                    @if ($platform->show_in_analytics)
                                        <span class="inline-flex items-center gap-1 text-[10px] text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800/40">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                            Analytics
                                        </span>
                                    @endif
                                    @if ($platform->show_in_sale_tracking)
                                        <span class="inline-flex items-center gap-1 text-[10px] text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-900/20 px-1.5 py-0.5 rounded border border-violet-100 dark:border-violet-800/40">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                                            Sale Tracking
                                        </span>
                                    @endif
                                    @if (!empty($platform->ancestor_names))
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500 truncate max-w-[200px]">{{ implode(' › ', $platform->ancestor_names) }}</span>
                                    @endif
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ $platform->created_at?->diffForHumans() }}</span>
                                </div>
                        </div>

                        <div class="flex gap-1 flex-shrink-0">
                            @can('general.sale_platform.show')
                                <a href="{{ route('admin.sale-platforms.show', $platform->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors" title="View">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            @endcan
                            @can('general.sale_platform.edit')
                                <a href="{{ route('admin.sale-platforms.edit', $platform->id) }}" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors" title="Edit">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            @endcan
                            @can('general.sale_platform.delete')
                                <button onclick="deleteData({{ $platform->id }})" class="w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 dark:text-slate-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                <form id="delete-form-{{ $platform->id }}" method="POST" action="{{ route('admin.sale-platforms.destroy', $platform->id) }}" style="display:none;">
                                    @csrf @method('DELETE')
                                </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                    <p class="text-sm text-slate-400 dark:text-slate-500">No platforms found.</p>
                    @can('general.sale_platform.create')
                        <a href="{{ route('admin.sale-platforms.create') }}" class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 text-sm rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">Create your first platform</a>
                    @endcan
                </div>
            @endforelse
        </div>

        @if ($is_filtered)
            @include('layouts.pagination', ['paginator' => $platforms])
        @endif

    </div>
</div>

@push('scripts')
<script>
    function deleteData(id) {
        if (confirm('Are you sure you want to delete this sale platform?')) {
            document.getElementById('delete-form-' + id).submit();
        }
    }
</script>
@endpush
@endsection

