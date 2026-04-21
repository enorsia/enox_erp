@extends('layouts.app')

@section('title', 'Discounts')

@section('content')
    {{-- Page identifier + data attributes for JS URLs --}}
    <div id="discounts-page-content"
         data-calculate-url="{{ route('admin.selling_chart.calculate.platform.profit') }}"
         data-view-url="{{ route('admin.selling_chart.view.single.chart', ':id') }}"
         data-dep-cats-url="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
    ></div>

    {{-- ── FILTER DRAWER (Alpine) ── --}}
    <div x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">

        {{-- Backdrop --}}
        <div x-show="drawerOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="drawerOpen = false"
             class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]"
             style="display:none;"></div>

        {{-- Drawer Panel --}}
        <div x-show="drawerOpen"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed top-0 right-0 bottom-0 w-full sm:w-[340px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
             style="display:none;">

            {{-- Drawer Head --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/>
                    </svg>
                    Filters
                </div>
                <button @click="drawerOpen = false"
                        class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Drawer Body --}}
            <form method="get" action="{{ route('admin.selling_chart.discounts') }}" class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                    {{-- Search --}}
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Search</p>
                        <div class="relative">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                            </svg>
                            <input type="text" name="name" placeholder="Search design no..."
                                   value="{{ request('name') }}"
                                   class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors"/>
                        </div>
                    </div>

                    <hr class="border-slate-100 dark:border-slate-700"/>

                    {{-- Department --}}
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Department</p>
                        <select id="department_select" name="department_id"
                                class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                                data-placeholder="Select Department">
                            <option value="">All Departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <hr class="border-slate-100 dark:border-slate-700"/>

                    {{-- Product Category --}}
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Product Category</p>
                        <select id="product_category" name="product_category_id"
                                class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                                data-placeholder="Select Category">
                            <option value="">All Categories</option>
                            @if (request('department_id'))
                                @foreach ($selling_chart_cats->where('lookup_id', request('department_id')) as $cat)
                                    <option value="{{ $cat->id }}" {{ request('product_category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <hr class="border-slate-100 dark:border-slate-700"/>

                    {{-- Mini Category --}}
                    <div>
                        <p class="text-[10px] font-semibold tracking-[1.2px] uppercase text-slate-400 dark:text-slate-500 mb-2">Mini Category</p>
                        <select id="product_mini_category" name="mini_category"
                                class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors"
                                data-placeholder="Select Mini Category">
                            <option value="">All Mini Categories</option>
                            @foreach ($selling_chart_types as $type)
                                <option value="{{ $type->id }}" {{ request('mini_category') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                {{-- Drawer Footer --}}
                <div class="flex gap-2.5 px-5 py-4 border-t border-slate-200 dark:border-slate-700 flex-shrink-0">
                    <a href="{{ route('admin.selling_chart.discounts') }}"
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

        {{-- ── PAGE CONTENT ── --}}
        <div class="p-5 lg:p-6">

            {{-- Page Header --}}
            <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Discounts</h1>
                    <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Manage selling chart discounts across platforms</p>
                </div>
            </div>

            {{-- ── TOOLBAR ── --}}
            <div class="flex flex-wrap items-center gap-2.5 mb-3">

                {{-- Inline search (quick) --}}
                <form method="get" action="{{ route('admin.selling_chart.discounts') }}" class="flex-1 min-w-[180px]">
                    @if(request('department_id'))
                        <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                    @endif
                    @if(request('product_category_id'))
                        <input type="hidden" name="product_category_id" value="{{ request('product_category_id') }}">
                    @endif
                    @if(request('mini_category'))
                        <input type="hidden" name="mini_category" value="{{ request('mini_category') }}">
                    @endif
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="name" placeholder="Search design no..."
                               value="{{ request('name') }}"
                               class="w-full pl-9 pr-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-accent-400 transition-colors"/>
                    </div>
                </form>

                {{-- Filter Drawer Button --}}
                @php
                    $activeFilterCount = collect([
                        request('department_id'),
                        request('product_category_id'),
                        request('mini_category'),
                    ])->filter()->count();
                @endphp
                <button type="button" @click="drawerOpen = true"
                        class="flex items-center gap-2 px-3.5 py-2 text-[13px] border rounded-lg transition-colors {{ $activeFilterCount > 0 ? 'border-accent-200 bg-accent-400/10 text-accent-600 dark:text-accent-200' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/>
                    </svg>
                    Filters
                    @if($activeFilterCount > 0)
                        <span class="bg-accent-400 text-white text-[9px] font-semibold min-w-[16px] h-4 rounded-full flex items-center justify-center px-1">{{ $activeFilterCount }}</span>
                    @endif
                </button>
            </div>

            {{-- ── ACTIVE FILTER TAGS ── --}}
            @if(request('name') || request('department_id') || request('product_category_id') || request('mini_category'))
                <div class="flex flex-wrap gap-2 mb-4">
                    @if(request('name'))
                        <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                            <span class="font-semibold">Search:</span> {{ request('name') }}
                            <a href="{{ request()->fullUrlWithQuery(['name' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                        </div>
                    @endif
                    @if(request('department_id'))
                        @php $deptName = $departments->firstWhere('id', request('department_id'))?->name ?? request('department_id'); @endphp
                        <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                            <span class="font-semibold">Dept:</span> {{ $deptName }}
                            <a href="{{ request()->fullUrlWithQuery(['department_id' => null, 'product_category_id' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                        </div>
                    @endif
                    @if(request('product_category_id'))
                        @php $catName = $selling_chart_cats->firstWhere('id', request('product_category_id'))?->name ?? request('product_category_id'); @endphp
                        <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                            <span class="font-semibold">Category:</span> {{ $catName }}
                            <a href="{{ request()->fullUrlWithQuery(['product_category_id' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                        </div>
                    @endif
                    @if(request('mini_category'))
                        @php $miniName = $selling_chart_types->firstWhere('id', request('mini_category'))?->name ?? request('mini_category'); @endphp
                        <div class="flex items-center gap-1.5 bg-accent-50 dark:bg-accent-800/40 text-accent-600 dark:text-accent-200 text-[11px] font-medium px-3 py-1 rounded-full border border-accent-100 dark:border-accent-700">
                            <span class="font-semibold">Mini Cat:</span> {{ $miniName }}
                            <a href="{{ request()->fullUrlWithQuery(['mini_category' => null]) }}" class="ml-0.5 opacity-60 hover:opacity-100 text-[13px] leading-none">&times;</a>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li class="text-sm text-red-600 dark:text-red-400">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ── DISCOUNT CARDS ── --}}
            <div class="flex flex-col gap-3">
                @if (!$chartInfos->isEmpty())
                    @foreach ($chartInfos as $chartInfo)
                        @php
                            $ecommerceProduct = $ecommerceMap[$chartInfo->design_no] ?? null;
                        @endphp

                        <div class="order-card bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden transition-[border-color] duration-200 hover:border-accent-200 dark:hover:border-accent-600/60">

                            {{-- ── CARD HEADER ── --}}
                            <div class="p-4 flex items-start gap-3">

                                {{-- Design Image --}}
                                @if ($chartInfo->design_image)
                                    <img class="w-14 h-14 rounded-xl object-cover flex-shrink-0 border border-slate-100 dark:border-slate-700"
                                         src="{{ cloudflareImage($chartInfo->design_image, 112) }}" alt="Design">
                                @else
                                    <div class="w-14 h-14 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 border border-slate-200 dark:border-slate-600">
                                        <svg class="w-5 h-5 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">#{{ $start + $loop->index }}</span>
                                        @can('general.discounts.show')
                                            <button type="button" onclick="viewChart({{ $chartInfo->id }}, 3)"
                                                    class="text-[13px] font-semibold text-accent-400 hover:text-accent-600 transition-colors font-mono">
                                                {{ $chartInfo->design_no }}
                                            </button>
                                        @else
                                            <span class="text-[13px] font-semibold text-slate-800 dark:text-slate-100 font-mono">{{ $chartInfo->design_no }}</span>
                                        @endcan
                                        @if ($ecommerceProduct)
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-medium">
                                                SKU: {{ $ecommerceProduct['sku'] ?? '' }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                            <span class="text-slate-300 dark:text-slate-600">Dept</span>
                                            <span class="text-slate-600 dark:text-slate-300 font-medium ml-1">{{ $chartInfo->department_name }}</span>
                                        </span>
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                            <span class="text-slate-300 dark:text-slate-600">Cat</span>
                                            <span class="text-slate-600 dark:text-slate-300 font-medium ml-1">{{ $chartInfo->category_name }}</span>
                                        </span>
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                            <span class="text-slate-300 dark:text-slate-600">Mini</span>
                                            <span class="text-slate-600 dark:text-slate-300 font-medium ml-1">{{ $chartInfo->mini_category_name }}</span>
                                        </span>
                                    </div>
                                </div>

                                {{-- View button --}}
                                @can('general.discounts.show')
                                    <button type="button" onclick="viewChart({{ $chartInfo->id }}, 3)"
                                            class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-[11px] rounded-lg border border-accent-200 dark:border-accent-700 bg-accent-50 dark:bg-accent-800/30 text-accent-500 dark:text-accent-300 hover:bg-accent-400 hover:text-white hover:border-accent-400 transition-colors font-semibold whitespace-nowrap">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        View
                                    </button>
                                @endcan
                            </div>

                            {{-- ── PLATFORM PRICES ── --}}
                            @if ($chartInfo->selling_chart_prices_count)
                                <div class="border-t border-slate-100 dark:border-slate-700 p-4 pt-3">
                                    {{-- Each color/range row --}}
                                    @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                        <div class="{{ !$loop->first ? 'mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60' : '' }}">
                                            {{-- Color/Range label --}}
                                            <div class="flex items-center gap-1.5 mb-2.5">
                                                <div class="w-1.5 h-1.5 rounded-full bg-accent-400 flex-shrink-0"></div>
                                                <b class="text-[14px]">Color/Style : </b>
                                                <span class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">{{ $ch_price->color_name }}</span>
                                                @if ($ch_price->range)
                                                    <span class="text-[11px] text-slate-400 dark:text-slate-500">/ {{ $ch_price->range }}</span>
                                                @endif
                                            </div>

                                            {{-- Platform price cards --}}
                                            <div class="selling-chart-grid">
                                                @foreach ($platform_ncs as $p_code => $p_name)
                                                    @php
                                                        $platform  = $platforms->get($p_code);
                                                        $d_price   = $ch_price?->discounts
                                                                        ->where('status', 1)
                                                                        ->where('platform_id', $platform->id)
                                                                        ->first();
                                                        $cal_val   = calculatePlatformProfit($ch_price, $platform);
                                                        if ($d_price) {
                                                            $dch_price = clone $ch_price;
                                                            $dch_price->confirm_selling_price = $d_price->price;
                                                            $dis_val   = calculatePlatformProfit($dch_price, $platform);
                                                        }
                                                    @endphp
                                                    <div class="rounded-lg border {{ $d_price ? 'border-blue-200 dark:border-blue-800/60 bg-blue-50/50 dark:bg-blue-900/10' : 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/30' }} p-2.5">
                                                        {{-- Platform name --}}
                                                        <p class="text-[9px] font-semibold tracking-[0.8px] uppercase {{ $d_price ? 'text-blue-500 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500' }} mb-1.5 truncate">{{ $p_name }}</p>

                                                        {{-- Original --}}
                                                        <div>
                                                            <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200">@price($ch_price->confirm_selling_price)</p>
                                                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                                <span title="Profit Margin">@pricews($cal_val['profit_margin'])%</span>
                                                                <span class="mx-0.5 opacity-40">·</span>
                                                                <span title="Net Profit">@price($cal_val['net_profit'])</span>
                                                            </p>
                                                        </div>

                                                        @if ($d_price)
                                                            {{-- Divider --}}
                                                            <div class="my-1.5 border-t border-blue-200 dark:border-blue-800/50"></div>
                                                            {{-- Discount --}}
                                                            <div>
                                                                <p class="text-[11px] font-semibold text-blue-600 dark:text-blue-400">@price($d_price->price)</p>
                                                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                                                                    <span title="Discount PM">@pricews($dis_val['profit_margin'])%</span>
                                                                    <span class="mx-0.5 opacity-40">·</span>
                                                                    <span title="Discount NP">@price($dis_val['net_profit'])</span>
                                                                </p>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="border-t border-slate-100 dark:border-slate-700 px-4 py-3">
                                    <p class="text-[12px] text-slate-400 dark:text-slate-500">No price data available.</p>
                                </div>
                            @endif

                        </div>{{-- /card --}}
                    @endforeach

                @else
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No results found</p>
                        <p class="text-[12px] text-slate-300 dark:text-slate-600 mt-1">Try adjusting your filters</p>
                    </div>
                @endif
            </div>

            {{-- ── PAGINATION ── --}}
            @if($chartInfos->hasPages())
                <div class="mt-5 flex justify-center">
                    <div class="flex items-center gap-1">
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

        </div>{{-- /page content --}}
    </div>{{-- /alpine root --}}

    {{-- Modal container — viewChart() injects here --}}
    <div class="setViewSellingChartItemModal"></div>

@endsection
