@extends('layouts.app')
@section('title', 'Selling Chart')

@section('content')
    <div id="chart-page-content"
         data-dep-cats-url="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
         data-view-url="{{ route('admin.selling_chart.view.single.chart', ':id') }}"
         data-calc-url="{{ route('admin.selling_chart.calculate.platform.profit') }}"></div>

    <div x-data="{ drawerOpen: false, imagePopup: null }" @keydown.escape.window="drawerOpen = false; imagePopup = null" x-on:set-image-popup.window="imagePopup = $event.detail; console.debug('Alpine received set-image-popup', $event.detail)">

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
            <img x-bind:src="imagePopup" alt="Zoomed image" class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl object-contain cursor-default" @click.stop>
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
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span class="inline-flex w-1.5 h-8 bg-gradient-to-b from-accent-400 to-accent-600 rounded-full"></span>
                        Selling Chart
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 ml-3.5">Manage selling chart entries and pricing data</p>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    @can('general.chart.import')
                        <a href="{{ route('admin.selling_chart.upload.sheet') }}"
                            class="group inline-flex items-center gap-2 px-4 py-2.5 text-[13px] rounded-lg bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 text-white font-semibold shadow-lg shadow-cyan-500/30 hover:shadow-cyan-600/40 hover:scale-105 transition-all duration-200">
                            <svg class="w-4 h-4 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            Import Excel
                        </a>
                    @endcan
                    @can('general.chart.create')
                        <a href="{{ route('admin.selling_chart.create') }}"
                            class="group inline-flex items-center gap-2 px-4 py-2.5 text-[13px] rounded-lg border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-accent-400 dark:hover:border-accent-500 hover:shadow-lg hover:scale-105 transition-all duration-200 font-semibold">
                            <svg class="w-4 h-4 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create New
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
                    <div class="relative group">
                        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-accent-400 pointer-events-none transition-colors duration-200" fill="none"
                            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="M21 21l-4.35-4.35" />
                        </svg>
                        <input type="text" name="name" placeholder="Search design no..." value="{{ request('name') }}"
                            class="w-full pl-10 pr-4 py-2.5 text-[13px] border-2 border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 focus:ring-4 focus:ring-accent-400/10 hover:border-slate-300 dark:hover:border-slate-500 transition-all duration-200" />
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
                    class="group relative flex items-center gap-2.5 px-4 py-2.5 text-[13px] border-2 rounded-xl font-semibold transition-all duration-200 {{ $activeFilterCount > 0 ? 'border-accent-300 bg-gradient-to-r from-accent-50 to-accent-100 dark:from-accent-900/30 dark:to-accent-800/30 text-accent-700 dark:text-accent-300 shadow-lg shadow-accent-400/20 hover:shadow-accent-400/30 hover:scale-105' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-slate-300 dark:hover:border-slate-500 hover:shadow-md' }}">
                    <svg class="w-4 h-4 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2" />
                    </svg>
                    Filters
                    @if($activeFilterCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 bg-gradient-to-br from-accent-400 to-accent-600 text-white text-[10px] font-bold min-w-[20px] h-5 rounded-full flex items-center justify-center px-1.5 shadow-lg shadow-accent-500/40 animate-pulse">{{ $activeFilterCount }}</span>
                    @endif
                </button>
            </div>

            {{-- Active Filter Action Buttons --}}
            @if(request()->except(['page']))
                <div class="mb-4" id="filter_dropdown">
                    <form method="get" action="{{ route('admin.selling_chart.index') }}" class="flex flex-wrap items-center gap-2.5">
                        @foreach (request()->except(['action', 'page']) as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        @can('general.chart.bulk_edit')
                            <button type="submit" value="bulkEdit" name="action"
                                class="group inline-flex items-center gap-2 px-4 py-2 text-[12px] rounded-lg border-2 border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 hover:border-slate-400 dark:hover:border-slate-500 hover:shadow-md hover:scale-105 transition-all duration-200 font-medium">
                                <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Bulk Edit
                            </button>
                        @endcan
                        @can('general.chart.export')
                            <button type="submit" value="excel" name="action"
                                class="group inline-flex items-center gap-2 px-4 py-2 text-[12px] rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold shadow-lg shadow-blue-500/30 hover:shadow-blue-600/40 hover:scale-105 transition-all duration-200">
                                <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Export Excel
                            </button>
                            <button type="submit" value="mismatch_excel" name="action"
                                class="group inline-flex items-center gap-2 px-4 py-2 text-[12px] rounded-lg bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-semibold shadow-lg shadow-rose-500/30 hover:shadow-rose-600/40 hover:scale-105 transition-all duration-200">
                                <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                Price Mismatch RPT
                            </button>
                        @endcan
                    </form>
                </div>
            @endif

            {{-- ── Summary Stats (Collapsible) ── --}}
            <!-- Keep stats collapsed on first load -->
            <div x-data="{ statsOpen: false }" class="mb-4">
                {{-- Collapse Toggle Button --}}
                <button @click="statsOpen = !statsOpen"
                    class="w-full flex items-center justify-between px-4 py-3 mb-3 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-700 border border-slate-200 dark:border-slate-600 rounded-xl hover:shadow-md transition-all duration-300 group">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-accent-400 to-accent-600 flex items-center justify-center shadow-lg shadow-accent-400/30 group-hover:shadow-accent-400/50 transition-all duration-300 group-hover:scale-110">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="text-left">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Summary Statistics</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Department & category breakdown</p>
                        </div>
                    </div>
                    <svg :class="statsOpen ? 'rotate-180' : ''" class="w-5 h-5 text-slate-400 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Stats Grid --}}
                <div x-show="statsOpen" x-collapse x-cloak>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                        @foreach ($deparment_total_colors as $index => $dtc)
                            @php
                                $gradients = [
                                    'from-blue-500 to-blue-600',
                                    'from-purple-500 to-purple-600',
                                    'from-pink-500 to-pink-600',
                                    'from-orange-500 to-orange-600',
                                    'from-teal-500 to-teal-600',
                                    'from-indigo-500 to-indigo-600',
                                ];
                                $gradient = $gradients[$index % count($gradients)];
                            @endphp
                            <div class="group relative bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 hover:shadow-xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                                {{-- Gradient Corner Accent --}}
                                <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br {{ $gradient }} opacity-10 rounded-bl-full group-hover:scale-150 transition-transform duration-500"></div>

                                <div class="relative">
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br {{ $gradient }} flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                        </div>
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">{{ $dtc['department_name'] }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach ($dtc['mini_categories'] as $mini_tc)
                                            <div class="flex justify-between items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-200">
                                                <span class="text-[11px] text-slate-600 dark:text-slate-300 truncate">{{ $mini_tc['mini_category_name'] }}</span>
                                                <span class="inline-flex items-center justify-center min-w-[28px] h-6 px-2 rounded-md bg-gradient-to-br {{ $gradient }} text-white text-[11px] font-bold shadow-sm">{{ $mini_tc['count'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if (!empty($mini_total_styles) && !$mini_total_styles->isEmpty())
                            <div class="group relative bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 hover:shadow-xl hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                                <div class="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-emerald-500 to-emerald-600 opacity-10 rounded-bl-full group-hover:scale-150 transition-transform duration-500"></div>

                                <div class="relative">
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform duration-300">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                            </svg>
                                        </div>
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">Style Count</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach ($mini_total_styles as $mini_tc)
                                            <div class="flex justify-between items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors duration-200">
                                                <span class="text-[11px] text-slate-600 dark:text-slate-300 truncate">{{ $mini_tc?->miniCategory?->name }}</span>
                                                <span class="inline-flex items-center justify-center min-w-[28px] h-6 px-2 rounded-md bg-gradient-to-br from-emerald-500 to-emerald-600 text-white text-[11px] font-bold shadow-sm">{{ $mini_tc->total_count }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Combine Total Colors and Total Quantity into one card when both available --}}
                        @if(isset($totalColors) && isset($totalQuantity))
                            <div class="group relative bg-gradient-to-br from-cyan-500 to-violet-600 rounded-xl p-4 hover:shadow-2xl hover:shadow-cyan-500/30 hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                <div class="relative">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                                </svg>
                                            </div>
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/90">Totals</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <div class="w-full p-3 rounded-lg bg-white/10 backdrop-blur-sm">
                                            <p class="text-[10px] text-white/80 uppercase tracking-wide">Total Colors</p>
                                            <p class="text-2xl font-bold text-white mt-1">{{ $totalColors }}</p>
                                        </div>
                                        <div class="w-full p-3 rounded-lg bg-white/10 backdrop-blur-sm">
                                            <p class="text-[10px] text-white/80 uppercase tracking-wide">Total Quantity</p>
                                            <p class="text-2xl font-bold text-white mt-1">{{ $totalQuantity }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            @isset($totalColors)
                                <div class="group relative bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-xl p-4 hover:shadow-2xl hover:shadow-cyan-500/30 hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                                    <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    <div class="relative">
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                                </svg>
                                            </div>
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/90">Total Colors</p>
                                        </div>
                                        <p class="text-3xl font-bold text-white mt-2">{{ $totalColors }}</p>
                                        <div class="mt-1 h-1 w-12 bg-white/30 rounded-full group-hover:w-full transition-all duration-500"></div>
                                    </div>
                                </div>
                            @endisset

                            @isset($totalQuantity)
                                <div class="group relative bg-gradient-to-br from-violet-500 to-violet-600 rounded-xl p-4 hover:shadow-2xl hover:shadow-violet-500/30 hover:scale-[1.02] hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                                    <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    <div class="relative">
                                        <div class="flex items-center gap-2 mb-2">
                                            <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                </svg>
                                            </div>
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-white/90">Total Quantity</p>
                                        </div>
                                        <p class="text-3xl font-bold text-white mt-2">{{ $totalQuantity }}</p>
                                        <div class="mt-1 h-1 w-12 bg-white/30 rounded-full group-hover:w-full transition-all duration-500"></div>
                                    </div>
                                </div>
                            @endisset
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Chart Entry Cards ── --}}
            <div class="flex flex-col gap-4">
                @if (!$chartInfos->isEmpty())
                    @foreach ($chartInfos as $chartInfo)
                        @php $ecommerceProduct = $ecommerceMap[$chartInfo->design_no] ?? null; @endphp

                        <div class="group relative bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden hover:shadow-2xl hover:shadow-accent-400/10 hover:border-accent-300 dark:hover:border-accent-500/60 transition-all duration-300 hover:-translate-y-0.5">
                            {{-- Gradient Top Border --}}
                            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-accent-400 via-blue-500 to-purple-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                            {{-- Card Header: #SL, Images, Design No, Status, Actions --}}
                            <div class="flex items-start gap-3 p-4 pb-3">

                                {{-- Images --}}
                                <div class="flex gap-2 shrink-0">
                                    <div class="flex flex-col items-center gap-1">
                                        @if ($chartInfo->design_image)
                                            <div class="relative group/img overflow-hidden rounded-lg">
                                                <img class="w-14 h-14 rounded-lg object-cover border-2 border-slate-200 dark:border-slate-600 cursor-zoom-in transition-all duration-300 group-hover/img:scale-110 group-hover/img:border-accent-400"
                                                    src="{{ cloudflareImage($chartInfo->design_image, 80) }}"
                                                    x-on:click.prevent="imagePopup = '{{ cloudflareImage($chartInfo->design_image, 1200) }}'"
                                                    data-large="{{ cloudflareImage($chartInfo->design_image, 1200) }}"
                                                    alt="Design Image" title="Design Image" loading="lazy">
                                                <button type="button" aria-label="Open image" data-large="{{ cloudflareImage($chartInfo->design_image, 1200) }}" x-on:click.prevent="imagePopup = '{{ cloudflareImage($chartInfo->design_image, 1200) }}'"
                                                    class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover/img:opacity-100 transition-opacity duration-300 rounded-lg flex items-center justify-center text-white">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @else
                                            <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600 border-2 border-slate-200 dark:border-slate-600 flex items-center justify-center group-hover:border-accent-300 transition-colors duration-300">
                                                <svg class="w-5 h-5 text-slate-300 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 font-medium">Design</span>
                                    </div>
                                    @if ($chartInfo->inspiration_image)
                                        <div class="hidden sm:flex flex-col items-center gap-1">
                                            <div class="relative group/img overflow-hidden rounded-lg">
                                                <img class="w-14 h-14 rounded-lg object-cover border-2 border-slate-200 dark:border-slate-600 cursor-zoom-in transition-all duration-300 group-hover/img:scale-110 group-hover/img:border-purple-400"
                                                    src="{{ cloudflareImage($chartInfo->inspiration_image, 80) }}"
                                                    x-on:click.prevent="imagePopup = '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}'"
                                                    data-large="{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}"
                                                    alt="Inspiration Image" title="Inspiration Image" loading="lazy">
                                                <button type="button" aria-label="Open image" data-large="{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}" x-on:click.prevent="imagePopup = '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}'"
                                                    class="absolute inset-0 bg-gradient-to-t from-purple-900/40 to-transparent opacity-0 group-hover/img:opacity-100 transition-opacity duration-300 rounded-lg flex items-center justify-center text-white">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <span class="text-[9px] text-slate-400 dark:text-slate-500 font-medium">Inspiration</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Title & Key Meta --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                            </svg>
                                            {{ $start + $loop->index }}
                                        </span>
                                        <button type="button" onclick="viewChart({{ $chartInfo->id }})"
                                            class="inline-flex items-center gap-1.5 text-[15px] font-bold text-accent-500 hover:text-accent-600 leading-tight group/title transition-all duration-200">
                                            <span class="relative">
                                                {{ $chartInfo->design_no }}
                                                <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-accent-400 group-hover/title:w-full transition-all duration-300"></span>
                                            </span>
                                            <svg class="w-4 h-4 opacity-0 group-hover/title:opacity-100 -translate-x-1 group-hover/title:translate-x-0 transition-all duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                        @if ($chartInfo->status == 1)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800 shadow-sm">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Approved
                                            </span>
                                        @elseif($chartInfo->status == 2)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-900/30 dark:to-rose-900/30 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800 shadow-sm">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                </svg>
                                                Rejected
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 shadow-sm">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                </svg>
                                                Not Approved
                                            </span>
                                        @endif
                                        @if ($ecommerceProduct && ($ecommerceProduct['sku'] ?? ''))
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-medium bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                                </svg>
                                                SKU: {{ $ecommerceProduct['sku'] }}
                                            </span>
                                        @endif
                                    </div>
                                    {{-- Key info row (matches backup table: Department, Season, Season Phase, Product Category, Mini Category) --}}
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            <span class="text-slate-500 dark:text-slate-400">Department:</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $chartInfo->department_name ?: '—' }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-slate-500 dark:text-slate-400">Season:</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $chartInfo->season_name ?: '—' }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <span class="text-slate-500 dark:text-slate-400">Phase:</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $chartInfo->phase_name ?: '—' }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                            </svg>
                                            <span class="text-slate-500 dark:text-slate-400">Category:</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $chartInfo->category_name ?: '—' }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-pink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                            </svg>
                                            <span class="text-slate-500 dark:text-slate-400">Mini:</span>
                                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $chartInfo->mini_category_name ?: '—' }}</span>
                                        </span>
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @can('general.chart.show')
                                        <button type="button" onclick="viewChart({{ $chartInfo->id }})"
                                            class="group/btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 border-accent-200 dark:border-accent-700 bg-accent-50 dark:bg-accent-900/20 text-accent-500 hover:bg-accent-500 hover:border-accent-500 hover:text-white hover:shadow-lg hover:shadow-accent-500/30 hover:scale-110 transition-all duration-200"
                                            title="View">
                                            <svg class="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>
                                    @endcan
                                    @if ($chartInfo->status == 0)
                                        @can('general.chart.edit')
                                            <a href="{{ route('admin.selling_chart.edit', $chartInfo->id) }}"
                                                class="group/btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 text-blue-500 hover:bg-blue-500 hover:border-blue-500 hover:text-white hover:shadow-lg hover:shadow-blue-500/30 hover:scale-110 transition-all duration-200"
                                                title="Edit">
                                                <svg class="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                        @endcan
                                        @can('general.chart.delete')
                                            <button type="button" onclick="deleteData({{ $chartInfo->id }})"
                                                class="group/btn inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-500 hover:border-red-500 hover:text-white hover:shadow-lg hover:shadow-red-500/30 hover:scale-110 transition-all duration-200"
                                                title="Delete">
                                                <svg class="w-4 h-4 group-hover/btn:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
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
                            <div class="px-4 pb-3 border-t border-slate-100 dark:border-slate-700/60 pt-3 bg-gradient-to-b from-slate-50/50 to-transparent dark:from-slate-700/20">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="w-1 h-4 bg-gradient-to-b from-slate-400 to-slate-300 rounded-full"></div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Product Details</p>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3 text-[11px]">
                                    <div class="group/detail p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-md hover:shadow-blue-100 dark:hover:shadow-blue-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/40 dark:to-blue-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Initial/Repeat</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $chartInfo->initial_repeated_status ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="group/detail p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-green-300 dark:hover:border-green-600 hover:shadow-md hover:shadow-green-100 dark:hover:shadow-green-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-green-100 to-green-200 dark:from-green-900/40 dark:to-green-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Launch Month</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $chartInfo->product_launch_month ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="group/detail p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-purple-300 dark:hover:border-purple-600 hover:shadow-md hover:shadow-purple-100 dark:hover:shadow-purple-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/40 dark:to-purple-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Product Code</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $chartInfo->product_code ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="group/detail p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-indigo-300 dark:hover:border-indigo-600 hover:shadow-md hover:shadow-indigo-100 dark:hover:shadow-indigo-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-100 to-indigo-200 dark:from-indigo-900/40 dark:to-indigo-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Ecom SKU</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $ecommerceProduct['sku'] ?? '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="group/detail p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-orange-300 dark:hover:border-orange-600 hover:shadow-md hover:shadow-orange-100 dark:hover:shadow-orange-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-orange-100 to-orange-200 dark:from-orange-900/40 dark:to-orange-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Fabrication</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $chartInfo->fabrication ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="group/detail col-span-2 sm:col-span-3 lg:col-span-4 xl:col-span-1 p-2.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-pink-300 dark:hover:border-pink-600 hover:shadow-md hover:shadow-pink-100 dark:hover:shadow-pink-900/20 transition-all duration-200">
                                        <div class="flex items-start gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-pink-100 to-pink-200 dark:from-pink-900/40 dark:to-pink-800/40 flex items-center justify-center shrink-0 group-hover/detail:scale-110 transition-transform duration-200">
                                                <svg class="w-3.5 h-3.5 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h7"/>
                                                </svg>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="text-slate-400 dark:text-slate-500 block text-[9px] uppercase tracking-wide mb-0.5 font-semibold">Description</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200 block truncate">{{ $chartInfo->product_description ?: '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ── Color & Pricing Rows (matches backup: Color Code, Color Name, Range, PO Order Qty + all pricing cols) ── --}}
                            @if ($chartInfo->selling_chart_prices_count)
                                <div class="border-t border-slate-100 dark:border-slate-700 p-4 space-y-4">
                                    @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                        {{-- Color Box: header + body --}}
                                        <div class="rounded-xl border-2 border-slate-200 dark:border-slate-600 overflow-hidden shadow-sm hover:shadow-md hover:border-accent-300 dark:hover:border-accent-600 transition-all duration-200">

                                            {{-- Color Header (light + dark friendly) --}}
                                            <div class="flex flex-wrap items-center gap-3 px-4 py-3 bg-gradient-to-r from-slate-50 to-slate-100 dark:from-slate-800 dark:to-slate-900 border-b-2 border-slate-200 dark:border-slate-600">
                                                <div class="flex items-center gap-2">
                                                    <div class="relative">
                                                        <div class="w-3 h-3 rounded-full bg-accent-400 shadow-lg shadow-accent-400/50 animate-pulse"></div>
                                                        <div class="absolute inset-0 w-3 h-3 rounded-full bg-accent-400 animate-ping opacity-75"></div>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-600 dark:text-slate-300 block font-semibold">Color Name</span>
                                                        <span class="text-[14px] font-extrabold text-slate-800 dark:text-white">{{ $ch_price->color_name ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 pl-3 border-l-2 border-slate-600">
                                                    <div class="w-7 h-7 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 flex items-center justify-center">
                                                        <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-500 dark:text-slate-300 block font-semibold">Color Code</span>
                                                        <span class="text-[11px] font-mono font-bold text-blue-700 dark:text-blue-200">{{ $ch_price->color_code ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 pl-3 border-l-2 border-slate-600">
                                                    <div class="w-7 h-7 rounded-lg bg-purple-500/10 dark:bg-purple-500/20 flex items-center justify-center">
                                                        <svg class="w-3.5 h-3.5 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-500 dark:text-slate-300 block font-semibold">Range</span>
                                                        <span class="text-[11px] font-bold text-purple-700 dark:text-purple-200">{{ $ch_price->range ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 pl-3 border-l-2 border-slate-600">
                                                    <div class="w-7 h-7 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20 flex items-center justify-center">
                                                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] uppercase tracking-wider text-slate-500 dark:text-slate-300 block font-semibold">PO Order Qty</span>
                                                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-200">{{ $ch_price->po_order_qty ?? 0 }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Color Body: Pricing --}}
                                            <div class="p-4 bg-white dark:bg-slate-800 space-y-4">

                                            {{-- Original Pricing (matches backup: Price $(FOB), Unit Price, Confirm Selling Price, 20% Selling VAT, VAT Value £, Profit Margin%, Net Profit) --}}
                                            <div class="mb-4">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="w-1 h-5 bg-gradient-to-b from-slate-500 to-slate-400 rounded-full"></div>
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300">Original Pricing</p>
                                                    <div class="flex-1 h-px bg-gradient-to-r from-slate-200 to-transparent dark:from-slate-600"></div>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-700/50 dark:to-slate-600/50 border border-slate-200 dark:border-slate-700 px-3 py-2.5 hover:shadow-lg hover:shadow-slate-200/50 dark:hover:shadow-slate-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-slate-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">Price $ (FOB)</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">$ {{ $ch_price->price_fob ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 border border-blue-200 dark:border-blue-700 px-3 py-2.5 hover:shadow-lg hover:shadow-blue-200/50 dark:hover:shadow-blue-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-blue-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-blue-500 dark:text-blue-400 font-semibold">Unit Price</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-blue-700 dark:text-blue-200">£ {{ $ch_price->unit_price ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/30 border border-emerald-200 dark:border-emerald-700 px-3 py-2.5 hover:shadow-lg hover:shadow-emerald-200/50 dark:hover:shadow-emerald-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-emerald-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-emerald-500 dark:text-emerald-400 font-semibold">Confirm Price</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-emerald-700 dark:text-emerald-200">£ {{ $ch_price->confirm_selling_price ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 border border-purple-200 dark:border-purple-700 px-3 py-2.5 hover:shadow-lg hover:shadow-purple-200/50 dark:hover:shadow-purple-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-purple-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-purple-500 dark:text-purple-400 font-semibold">20% VAT</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-purple-700 dark:text-purple-200">£ {{ $ch_price->vat_price ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/30 border border-amber-200 dark:border-amber-700 px-3 py-2.5 hover:shadow-lg hover:shadow-amber-200/50 dark:hover:shadow-amber-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-amber-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-amber-500 dark:text-amber-400 font-semibold">VAT Value</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-amber-700 dark:text-amber-200">£ {{ $ch_price->vat_value ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-cyan-50 to-cyan-100 dark:from-cyan-900/30 dark:to-cyan-800/30 border border-cyan-200 dark:border-cyan-700 px-3 py-2.5 hover:shadow-lg hover:shadow-cyan-200/50 dark:hover:shadow-cyan-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-cyan-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                                                </svg>
                                                                <p class="text-[10px] text-cyan-500 dark:text-cyan-400 font-semibold">Profit %</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-cyan-700 dark:text-cyan-200">{{ $ch_price->profit_margin ?? 0 }}%</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/price relative rounded-xl bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 border border-green-200 dark:border-green-700 px-3 py-2.5 hover:shadow-lg hover:shadow-green-200/50 dark:hover:shadow-green-900/30 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-green-300/20 to-transparent rounded-bl-full group-hover/price:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-green-500 dark:text-green-400 font-semibold">Net Profit</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-green-700 dark:text-green-200">£ {{ $ch_price->net_profit ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Discount Pricing (matches backup: Discount%, Discount Selling Price, 20% Selling VAT Deduct, Discount VAT Value £, Discount Profit Margin%, Discount Net Profit) --}}
                                            <div>
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="w-1 h-5 bg-gradient-to-b from-rose-500 to-rose-400 rounded-full"></div>
                                                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-600 dark:text-rose-400">Discount Pricing</p>
                                                    <div class="flex-1 h-px bg-gradient-to-r from-rose-200 to-transparent dark:from-rose-600"></div>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gradient-to-r from-rose-100 to-red-100 dark:from-rose-900/40 dark:to-red-900/40 text-rose-700 dark:text-rose-300 text-[9px] font-bold border border-rose-200 dark:border-rose-800">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                                                        </svg>
                                                        Special Offer
                                                    </span>
                                                </div>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-rose-50 to-rose-100 dark:from-rose-900/30 dark:to-rose-800/30 border-2 border-rose-300 dark:border-rose-700 px-3 py-2.5 hover:shadow-lg hover:shadow-rose-300/50 dark:hover:shadow-rose-900/40 hover:-translate-y-0.5 hover:border-rose-400 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-rose-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-rose-600 dark:text-rose-400 font-bold">Discount %</p>
                                                            </div>
                                                            <p class="text-[14px] font-extrabold text-rose-700 dark:text-rose-300">{{ $ch_price->discount ?? 0 }}%</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border border-blue-300 dark:border-blue-700 px-3 py-2.5 hover:shadow-lg hover:shadow-blue-300/50 dark:hover:shadow-blue-900/40 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-blue-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold">Discount Price</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-blue-700 dark:text-blue-300">£ {{ $ch_price->discount_selling_price ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-purple-50 to-violet-50 dark:from-purple-900/30 dark:to-violet-900/30 border border-purple-300 dark:border-purple-700 px-3 py-2.5 hover:shadow-lg hover:shadow-purple-300/50 dark:hover:shadow-purple-900/40 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-purple-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-purple-600 dark:text-purple-400 font-semibold">20% VAT Deduct</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-purple-700 dark:text-purple-300">£ {{ $ch_price->discount_vat_price ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-amber-50 to-yellow-50 dark:from-amber-900/30 dark:to-yellow-900/30 border border-amber-300 dark:border-amber-700 px-3 py-2.5 hover:shadow-lg hover:shadow-amber-300/50 dark:hover:shadow-amber-900/40 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-amber-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">VAT Value</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-amber-700 dark:text-amber-300">£ {{ $ch_price->discount_vat_value ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-cyan-50 to-sky-50 dark:from-cyan-900/30 dark:to-sky-900/30 border border-cyan-300 dark:border-cyan-700 px-3 py-2.5 hover:shadow-lg hover:shadow-cyan-300/50 dark:hover:shadow-cyan-900/40 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-cyan-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                                                </svg>
                                                                <p class="text-[10px] text-cyan-600 dark:text-cyan-400 font-semibold">Profit %</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-cyan-700 dark:text-cyan-300">{{ $ch_price->discount_profit_margin ?? 0 }}%</p>
                                                        </div>
                                                    </div>
                                                    <div class="group/discount relative rounded-xl bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/30 dark:to-green-900/30 border border-emerald-300 dark:border-emerald-700 px-3 py-2.5 hover:shadow-lg hover:shadow-emerald-300/50 dark:hover:shadow-emerald-900/40 hover:-translate-y-0.5 transition-all duration-200 overflow-hidden">
                                                        <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-br from-emerald-400/20 to-transparent rounded-bl-full group-hover/discount:scale-150 transition-transform duration-500"></div>
                                                        <div class="relative">
                                                            <div class="flex items-center gap-1.5 mb-1">
                                                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                                </svg>
                                                                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Net Profit</p>
                                                            </div>
                                                            <p class="text-[13px] font-bold text-emerald-700 dark:text-emerald-300">£ {{ $ch_price->discount_net_profit ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            </div>{{-- end color body --}}
                                        </div>{{-- end color box --}}
                                    @endforeach
                                </div>
                            @endif

                        </div>{{-- end card --}}
                    @endforeach
                @else
                    <div class="relative bg-gradient-to-br from-white to-slate-50 dark:from-slate-800 dark:to-slate-700 border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-2xl p-16 text-center overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-accent-50/30 to-transparent dark:from-accent-900/10 pointer-events-none"></div>
                        <div class="relative">
                            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-600 mb-4 shadow-lg">
                                <svg class="w-10 h-10 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No Results Found</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">We couldn't find any selling charts matching your criteria.<br>Try adjusting your filters or search terms.</p>
                            <a href="{{ route('admin.selling_chart.index') }}"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-[13px] rounded-lg bg-gradient-to-r from-accent-400 to-accent-600 hover:from-accent-500 hover:to-accent-700 text-white font-semibold shadow-lg shadow-accent-500/30 hover:shadow-accent-600/40 hover:scale-105 transition-all duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Clear Filters
                            </a>
                        </div>
                    </div>
                @endif
            </div>{{-- end cards list --}}

            {{-- ── Pagination ── --}}
            @if($chartInfos->hasPages())
                <div class="mt-6 flex justify-center">
                    <div class="inline-flex items-center gap-1.5 flex-wrap bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-1.5 shadow-sm">
                        @if($chartInfos->onFirstPage())
                            <span class="px-4 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed font-medium">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Prev
                            </span>
                        @else
                            <a href="{{ $chartInfos->previousPageUrl() }}"
                                class="group inline-flex items-center px-4 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-accent-50 dark:hover:bg-accent-900/20 transition-all duration-200 font-medium">
                                <svg class="w-4 h-4 mr-1 group-hover:-translate-x-0.5 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Prev
                            </a>
                        @endif

                        @foreach ($chartInfos->getUrlRange(1, $chartInfos->lastPage()) as $page => $url)
                            @if ($page == $chartInfos->currentPage())
                                <span class="min-w-[36px] h-9 flex items-center justify-center rounded-lg bg-gradient-to-br from-accent-400 to-accent-600 text-white text-[13px] font-bold shadow-lg shadow-accent-500/30">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}"
                                    class="min-w-[36px] h-9 flex items-center justify-center rounded-lg text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-accent-500 dark:hover:text-accent-400 transition-all duration-200 font-medium hover:shadow-md hover:scale-105">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($chartInfos->hasMorePages())
                            <a href="{{ $chartInfos->nextPageUrl() }}"
                                class="group inline-flex items-center px-4 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-accent-50 dark:hover:bg-accent-900/20 transition-all duration-200 font-medium">
                                Next
                                <svg class="w-4 h-4 ml-1 group-hover:translate-x-0.5 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @else
                            <span class="px-4 py-2 text-[13px] text-slate-300 dark:text-slate-600 cursor-not-allowed font-medium">
                                Next
                                <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="setViewSellingChartItemModal"></div>
@endsection
