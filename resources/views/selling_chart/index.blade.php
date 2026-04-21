@extends('layouts.app')
@section('title', 'Selling Chart')

@section('content')
    <div id="chart-page-content"
         data-dep-cats-url="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
         data-view-url="{{ route('admin.selling_chart.view.single.chart', ':id') }}"
         data-calc-url="{{ route('admin.selling_chart.calculate.platform.profit') }}"></div>

    <div x-data="{ drawerOpen: false, imagePopup: null }" @keydown.escape.window="drawerOpen = false; imagePopup = null">

        {{-- Image Popup Lightbox --}}
        <div x-show="imagePopup" x-cloak @click="imagePopup = null"
            class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 cursor-zoom-out p-6"
            style="display:none;">
            <button @click="imagePopup = null"
                class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="imagePopup" class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl object-contain cursor-default" @click.stop>
        </div>

        {{-- Drawer Backdrop --}}
        <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="drawerOpen = false"
            class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

        {{-- Filter Drawer --}}
        <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed top-0 right-0 bottom-0 w-full sm:w-[360px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
            style="display:none;">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2" />
                    </svg>
                    Filters
                </div>
                <button @click="drawerOpen = false"
                    class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="get" action="{{ route('admin.selling_chart.index') }}" class="flex-1 flex flex-col overflow-hidden">
                <input type="hidden" name="advance_search" id="advance_search" value="1">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Search</p>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none"
                                stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="7" />
                                <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                            </svg>
                            <input type="text" id="search_id" name="name" placeholder="Search design no..." value="{{ request('name') }}"
                                class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors" />
                        </div>
                    </div>
                    <hr class="border-slate-100 dark:border-slate-700" />
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Department</p>
                        <select id="department_select" name="department_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Department">
                            <option value="">All Departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Product Category</p>
                        <select id="product_category" name="product_category_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Category">
                            <option value="">All Categories</option>
                            @if (request('department_id'))
                                @foreach ($selling_chart_cats->where('lookup_id', request('department_id')) as $selling_chart_cat)
                                    <option value="{{ $selling_chart_cat->id }}" {{ request('product_category_id') == $selling_chart_cat->id ? 'selected' : '' }}>
                                        {{ $selling_chart_cat->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Mini Category</p>
                        <select id="product_mini_category" name="mini_category"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Mini Category">
                            <option value="">All Mini Categories</option>
                            @foreach ($selling_chart_types as $selling_chart_type)
                                <option value="{{ $selling_chart_type->id }}" {{ request('mini_category') == $selling_chart_type->id ? 'selected' : '' }}>
                                    {{ $selling_chart_type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Season</p>
                        <select id="season_id" name="season_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Season">
                            <option value="">Select Season</option>
                            @foreach ($seasons as $season)
                                <option value="{{ $season->id }}" {{ request('season_id') == $season->id ? 'selected' : '' }}>{{ $season->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Season Phase</p>
                        <select id="Season_Phase" name="season_phase_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Season Phase">
                            <option value="">Select Season Phase</option>
                            @foreach ($seasons_phases as $seasons_phase)
                                <option value="{{ $seasons_phase->id }}" {{ request('season_phase_id') == $seasons_phase->id ? 'selected' : '' }}>{{ $seasons_phase->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Initial/Repeat</p>
                        <select id="Repeat_Order" name="initial_repeat_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select Initial/ Repeat Order">
                            <option value="">Select Initial/ Repeat Order</option>
                            @foreach ($initialRepeats as $initialRepeat)
                                <option value="{{ $initialRepeat->id }}" {{ $initialRepeat->id == request('initial_repeat_id') ? 'selected' : '' }}>{{ $initialRepeat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Fabrication</p>
                        <select id="fabrication" name="fabrication_id"
                            class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                            data-choices data-placeholder="Select a fabrication">
                            <option value="">Select a fabrication</option>
                            @foreach ($fabrics as $fabric)
                                <option value="{{ $fabric->id }}" {{ request('fabrication_id') == $fabric->id ? 'selected' : '' }}>{{ $fabric->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                    <a href="{{ route('admin.selling_chart.index') }}"
                        class="flex-1 py-2.5 text-[13px] text-center border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors font-medium">
                        Reset
                    </a>
                    <button type="submit"
                        class="flex-[2] py-2.5 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Main Content ── --}}
        <div class="p-5 lg:p-6">

            {{-- Page Header --}}
            <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Selling Chart</h1>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage selling chart entries and pricing data</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @can('general.chart.import')
                        <a href="{{ route('admin.selling_chart.upload.sheet') }}"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white font-semibold transition-colors">
                            <i class="bi bi-upload"></i> Import Excel
                        </a>
                    @endcan
                    @can('general.chart.create')
                        <a href="{{ route('admin.selling_chart.create') }}"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-semibold">
                            <i class="bi bi-plus-lg"></i> Create
                        </a>
                    @endcan
                </div>
            </div>

            {{-- Search Bar + Filter Trigger --}}
            <div class="flex flex-wrap items-center gap-2.5 mb-4">
                <form method="get" action="{{ route('admin.selling_chart.index') }}" class="flex-1 min-w-[180px]">
                    @foreach (['department_id', 'product_category_id', 'mini_category', 'season_id', 'season_phase_id', 'initial_repeat_id', 'fabrication_id'] as $param)
                        @if(request($param))
                            <input type="hidden" name="{{ $param }}" value="{{ request($param) }}">
                        @endif
                    @endforeach
                    <input type="hidden" name="advance_search" value="{{ request('advance_search', 1) }}">
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none"
                            stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                        </svg>
                        <input type="text" name="name" placeholder="Search design no..." value="{{ request('name') }}"
                            class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors" />
                    </div>
                </form>
                @php
                    $activeFilterCount = collect([
                        request('department_id'),
                        request('product_category_id'),
                        request('mini_category'),
                        request('season_id'),
                        request('season_phase_id'),
                        request('initial_repeat_id'),
                        request('fabrication_id'),
                    ])->filter()->count();
                @endphp
                <button type="button" @click="drawerOpen = true"
                    class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2" />
                    </svg>
                    Filters
                    @if($activeFilterCount > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>
                    @endif
                </button>
            </div>

            {{-- Active Filter Action Buttons --}}
            @if(request()->except(['page']))
                <div class="mb-4" id="filter_dropdown">
                    <form method="get" action="{{ route('admin.selling_chart.index') }}" class="flex flex-wrap items-center gap-2">
                        @foreach (request()->except(['action', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        @can('general.chart.bulk_edit')
                            <button type="submit" value="bulkEdit" name="action"
                                class="px-3 py-1.5 text-[12px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                Bulk Edit
                            </button>
                        @endcan
                        @can('general.chart.export')
                            <button type="submit" value="excel" name="action"
                                class="px-3 py-1.5 text-[12px] rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-colors">
                                Export Excel
                            </button>
                            <button type="submit" value="mismatch_excel" name="action"
                                class="px-3 py-1.5 text-[12px] rounded-lg bg-rose-500 hover:bg-rose-600 text-white transition-colors">
                                Price Mismatch RPT
                            </button>
                        @endcan
                    </form>
                </div>
            @endif

            {{-- ── Summary Stats ── --}}
            <div class="mb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                @foreach ($deparment_total_colors as $dtc)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">{{ $dtc['department_name'] }}</p>
                        <div class="space-y-0.5">
                            @foreach ($dtc['mini_categories'] as $mini_tc)
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 flex justify-between gap-2">
                                    <span class="truncate">{{ $mini_tc['mini_category_name'] }}</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-100 shrink-0">{{ $mini_tc['count'] }}</span>
                                </p>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if (!empty($mini_total_styles) && !$mini_total_styles->isEmpty())
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Style Count</p>
                        <div class="space-y-0.5">
                            @foreach ($mini_total_styles as $mini_tc)
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 flex justify-between gap-2">
                                    <span class="truncate">{{ $mini_tc?->miniCategory?->name }}</span>
                                    <span class="font-bold text-slate-800 dark:text-slate-100 shrink-0">{{ $mini_tc->total_count }}</span>
                                </p>
                            @endforeach
                        </div>
                    </div>
                @endif
                @isset($totalColors)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Total Colors</p>
                        <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $totalColors }}</p>
                    </div>
                @endisset
                @isset($totalQuantity)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Total Quantity</p>
                        <p class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $totalQuantity }}</p>
                    </div>
                @endisset
            </div>

            {{-- ── Chart Entry Cards ── --}}
            <div class="flex flex-col gap-4">
                @if (!$chartInfos->isEmpty())
                    @foreach ($chartInfos as $chartInfo)
                        @php $ecommerceProduct = $ecommerceMap[$chartInfo->design_no] ?? null; @endphp

                        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden hover:border-accent-300 dark:hover:border-accent-600/60 transition-[border-color]">

                            {{-- Card Header: #SL, Images, Design No, Status, Actions --}}
                            <div class="flex items-start gap-3 p-4 pb-3">

                                {{-- Images --}}
                                <div class="flex gap-2 shrink-0">
                                    <div class="flex flex-col items-center gap-0.5">
                                        @if ($chartInfo->design_image)
                                            <img class="w-14 h-14 rounded-lg object-cover border border-slate-200 dark:border-slate-600 cursor-zoom-in hover:opacity-85 transition-opacity"
                                                src="{{ cloudflareImage($chartInfo->design_image, 80) }}"
                                                @click="imagePopup = '{{ cloudflareImage($chartInfo->design_image, 1200) }}'"
                                                alt="Design Image" title="Design Image">
                                        @else
                                            <div class="w-14 h-14 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500">Design Image</span>
                                    </div>
                                    @if ($chartInfo->inspiration_image)
                                        <div class="hidden sm:flex flex-col items-center gap-0.5">
                                            <img class="w-14 h-14 rounded-lg object-cover border border-slate-200 dark:border-slate-600 cursor-zoom-in hover:opacity-85 transition-opacity"
                                                src="{{ cloudflareImage($chartInfo->inspiration_image, 80) }}"
                                                @click="imagePopup = '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}'"
                                                alt="Inspiration Image" title="Inspiration Image">
                                            <span class="text-[9px] text-slate-400 dark:text-slate-500">Insp. Image</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Title & Key Meta --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">#{{ $start + $loop->index }}</span>
                                        <button type="button" onclick="viewChart({{ $chartInfo->id }})"
                                            class="text-[14px] font-bold text-accent-400 hover:text-accent-600 hover:underline leading-tight">
                                            {{ $chartInfo->design_no }}
                                        </button>
                                        @if ($chartInfo->status == 1)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">Approved</span>
                                        @elseif($chartInfo->status == 2)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Rejected</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">Not Approved</span>
                                        @endif
                                        @if ($ecommerceProduct && ($ecommerceProduct['sku'] ?? ''))
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">Ecom SKU: {{ $ecommerceProduct['sku'] }}</span>
                                        @endif
                                    </div>
                                    {{-- Key info row (matches backup table: Department, Season, Season Phase, Product Category, Mini Category) --}}
                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px]">
                                        <span class="text-slate-400 dark:text-slate-500">Department: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->department_name ?: '—' }}</span></span>
                                        <span class="text-slate-400 dark:text-slate-500">Season: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->season_name ?: '—' }}</span></span>
                                        <span class="text-slate-400 dark:text-slate-500">Season Phase: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->phase_name ?: '—' }}</span></span>
                                        <span class="text-slate-400 dark:text-slate-500">Product Category: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->category_name ?: '—' }}</span></span>
                                        <span class="text-slate-400 dark:text-slate-500">Mini Category: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->mini_category_name ?: '—' }}</span></span>
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-1 shrink-0">
                                    @can('general.chart.show')
                                        <button type="button" onclick="viewChart({{ $chartInfo->id }})"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-accent-200 dark:border-accent-700 bg-accent-50 dark:bg-accent-900/20 text-accent-500 hover:bg-accent-400 hover:text-white transition-colors"
                                            title="View">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                    @endcan
                                    @if ($chartInfo->status == 0)
                                        @can('general.chart.edit')
                                            <a href="{{ route('admin.selling_chart.edit', $chartInfo->id) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 text-blue-500 hover:bg-blue-500 hover:text-white transition-colors"
                                                title="Edit">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                        @endcan
                                        @can('general.chart.delete')
                                            <button type="button" onclick="deleteData({{ $chartInfo->id }})"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-500 hover:text-white transition-colors"
                                                title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                            <form id="delete-form-{{ $chartInfo->id }}" method="POST"
                                                action="{{ route('admin.selling_chart.destroy', $chartInfo->id) }}"
                                                style="display:none;">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </div>

                            {{-- ── Product Details Grid (matches backup: Initial/Repeat Order, Product Launch Month, Product Code, Ecom SKU, Fabrication, Product Description) ── --}}
                            <div class="px-4 pb-3 border-t border-slate-100 dark:border-slate-700/60 pt-3">
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-2">Product Details</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-x-4 gap-y-3 text-[11px]">
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Initial / Repeat Order</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->initial_repeated_status ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Product Launch Month</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->product_launch_month ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Product Code</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->product_code ?: '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Ecom SKU</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $ecommerceProduct['sku'] ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Fabrication</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->fabrication ?: '—' }}</span>
                                    </div>
                                    <div class="col-span-2 sm:col-span-3 lg:col-span-4 xl:col-span-1">
                                        <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase tracking-wide mb-0.5">Product Description</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $chartInfo->product_description ?: '—' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- ── Color & Pricing Rows (matches backup: Color Code, Color Name, Range, PO Order Qty + all pricing cols) ── --}}
                            @if ($chartInfo->selling_chart_prices_count)
                                <div class="border-t border-slate-100 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60">
                                    @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                        <div class="p-4 pt-3">

                                            {{-- Color info header --}}
                                            <div class="flex flex-wrap items-start gap-3 mb-3">
                                                <div class="flex items-center gap-1.5">
                                                    <div class="w-2 h-2 rounded-full bg-accent-400 shrink-0 mt-[3px]"></div>
                                                    <div>
                                                        <span class="text-[9px] uppercase tracking-wide text-slate-400 dark:text-slate-500 block">Color Name</span>
                                                        <span class="text-[12px] font-bold text-slate-800 dark:text-slate-100">{{ $ch_price->color_name ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="border-l border-slate-200 dark:border-slate-600 pl-3">
                                                    <span class="text-[9px] uppercase tracking-wide text-slate-400 dark:text-slate-500 block">Color Code</span>
                                                    <span class="text-[11px] font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $ch_price->color_code ?: '—' }}</span>
                                                </div>
                                                <div class="border-l border-slate-200 dark:border-slate-600 pl-3">
                                                    <span class="text-[9px] uppercase tracking-wide text-slate-400 dark:text-slate-500 block">Range</span>
                                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ $ch_price->range ?: '—' }}</span>
                                                </div>
                                                <div class="border-l border-slate-200 dark:border-slate-600 pl-3">
                                                    <span class="text-[9px] uppercase tracking-wide text-slate-400 dark:text-slate-500 block">PO Order Qty</span>
                                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">{{ $ch_price->po_order_qty ?? 0 }}</span>
                                                </div>
                                            </div>

                                            {{-- Original Pricing (matches backup: Price $(FOB), Unit Price, Confirm Selling Price, 20% Selling VAT, VAT Value £, Profit Margin%, Net Profit) --}}
                                            <div class="mb-3">
                                                <p class="text-[9px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-1.5">Original Pricing</p>
                                                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Price $ (FOB)</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">$ {{ $ch_price->price_fob ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Unit Price</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">£ {{ $ch_price->unit_price ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Confirm Selling Price</p>
                                                        <p class="text-[12px] font-semibold text-slate-800 dark:text-slate-100">£ {{ $ch_price->confirm_selling_price ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">20% Selling VAT</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">£ {{ $ch_price->vat_price ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Vat Value £</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">£ {{ $ch_price->vat_value ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Profit Margin %</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">{{ $ch_price->profit_margin ?? 0 }}%</p>
                                                    </div>
                                                    <div class="rounded-lg bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700 px-2.5 py-2">
                                                        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-0.5">Net Profit</p>
                                                        <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">£ {{ $ch_price->net_profit ?? 0 }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Discount Pricing (matches backup: Discount%, Discount Selling Price, 20% Selling VAT Deduct, Discount VAT Value £, Discount Profit Margin%, Discount Net Profit) --}}
                                            <div>
                                                <p class="text-[9px] font-semibold uppercase tracking-widest text-blue-400 dark:text-blue-500 mb-1.5">Discount Pricing</p>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">Discount %</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">{{ $ch_price->discount ?? 0 }}%</p>
                                                    </div>
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">Discount Selling Price</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">£ {{ $ch_price->discount_selling_price ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">20% Selling VAT Deduct</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">£ {{ $ch_price->discount_vat_price ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">Discount Vat Value £</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">£ {{ $ch_price->discount_vat_value ?? 0 }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">Discount Profit Margin %</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">{{ $ch_price->discount_profit_margin ?? 0 }}%</p>
                                                    </div>
                                                    <div class="rounded-lg bg-blue-50/60 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2.5 py-2">
                                                        <p class="text-[10px] text-blue-400 dark:text-blue-500 mb-0.5">Discount Net Profit</p>
                                                        <p class="text-[12px] font-semibold text-blue-600 dark:text-blue-300">£ {{ $ch_price->discount_net_profit ?? 0 }}</p>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    @endforeach
                                </div>
                            @endif

                        </div>{{-- end card --}}
                    @endforeach
                @else
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-12 text-center">
                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No results found</p>
                    </div>
                @endif
            </div>{{-- end cards list --}}

            {{-- ── Pagination ── --}}
            @if($chartInfos->hasPages())
                <div class="mt-5 flex justify-center">
                    <div class="flex items-center gap-1 flex-wrap">
                        @if($chartInfos->onFirstPage())
                            <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">← Prev</span>
                        @else
                            <a href="{{ $chartInfos->previousPageUrl() }}" class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">← Prev</a>
                        @endif
                        @foreach ($chartInfos->getUrlRange(1, $chartInfos->lastPage()) as $page => $url)
                            @if ($page == $chartInfos->currentPage())
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-accent-400 text-white text-[13px] font-semibold">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center rounded-lg text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if($chartInfos->hasMorePages())
                            <a href="{{ $chartInfos->nextPageUrl() }}" class="px-3 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-400 transition-colors">Next →</a>
                        @else
                            <span class="px-3 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed">Next →</span>
                        @endif
                    </div>
                </div>
            @endif

        </div>{{-- end p-5 lg:p-6 --}}
    </div>{{-- end x-data --}}

    <div class="setViewSellingChartItemModal"></div>
@endsection
