@extends('layouts.app')

@section('title', 'Warehouse Stock IN/OUT Report')

@section('content')
    <div x-data="{ drawerOpen: false, imagePopup: null }" @keydown.escape.window="drawerOpen = false; imagePopup = null">
        <div id="enox_style_stock_report" class="hidden" data-style-stocks='@json($style_stocks)'></div>
        <div id="discounts-page-content"
         data-calculate-url="{{ route('admin.selling_chart.calculate.platform.profit') }}"
         data-save-url="{{ route('admin.selling_chart.save.platform.discount.price') }}"
         data-dep-cats-url="{{ url('admin/selling-chart/get-dep-wise-cats') }}"
    ></div>

        <div class="p-5 lg:p-6 space-y-5">

            @if (!empty($load_error))
                <div
                    class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    {{ $load_error }}
                </div>
            @endif

            {{-- ─── Page Header ─── --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <span class="w-8 h-8 bg-violet-500/15 rounded-lg flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="1.8"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                        </span>
                        Warehouse Stock IN/OUT Report
                    </h1>

                </div>

                @can('ecommerce.wh_stock_in_out.export')
                    <form method="get" action="{{ route('admin.style.stock.index') }}">
                        <button type="submit" name="action" value="export_stock_analysis"
                            class="flex items-center gap-2 px-3.5 py-2 text-[13px] font-semibold rounded-xl bg-accent-400 hover:bg-accent-600 text-white transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" />
                            </svg>
                            Export Report
                        </button>
                    </form>
                @endcan
            </div>

            {{-- ─── Search Filters ─── --}}
            <div class="an-card p-4">
                <div class="flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                    <h2 class="text-[14px] font-semibold text-slate-800 dark:text-slate-100">Search & Filter</h2>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="f-label" for="search_department">Department</label>
                        <select id="search_department" class="f-input custom-select">
                            <option value="">-- Select Department --</option>
                            @foreach ($style_stocks as $deptKey => $department)
                                <option value="{{ $deptKey }}">{{ $department['department_name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="f-label" for="search_category">Category</label>
                        <select id="search_category" class="f-input custom-select" disabled>
                            <option value="">-- Select Category --</option>
                        </select>
                    </div>

                    <div>
                        <label class="f-label" for="search_product">Product Code</label>
                        <input type="text" id="search_product" class="f-input" placeholder="Enter product code">
                    </div>

                    <div>
                        <label class="f-label" for="discount_status">Discount</label>
                        <select id="discount_status" class="f-input custom-select">
                            <option value="1">All items</option>
                            <option value="2">Non discount items</option>
                            <option value="3">Discount items</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" id="search_product_btn"
                            class="flex items-center justify-center gap-2 px-4 py-2 text-[13px] font-semibold rounded-xl bg-accent-400 hover:bg-accent-600 text-white transition-colors flex-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            Search
                        </button>
                        <a href="{{ route('admin.style.stock.index') }}"
                            class="flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                            title="Reset">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            @if (!$style_stocks->isEmpty())
                {{-- ─── Discount Items Summary (collapsible, JS-rendered) ─── --}}
                <div class="an-card overflow-hidden" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M9 14.25l6-6m4.5-3.493V21.75a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18.75V5.25a2.25 2.25 0 012.25-2.25h7.5" />
                            </svg>
                            <span class="text-[14px] font-semibold text-slate-800 dark:text-slate-100">Discount Items
                                Summary</span>
                            <span id="ssr_discount_total_badge"
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"></span>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse x-cloak>
                        <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-4">
                            <p class="text-[12px] text-slate-400 dark:text-slate-500 mb-3">Count of products with discount
                                price,
                                grouped by department and category.</p>
                            <div id="ssr_discount_summary"
                                class="ssr-discount-summary grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ─── Report Table ─── --}}
            @if (!$style_stocks->isEmpty())
                @php
                    $grandTotalStock = 0;
                    $grandTotalSold = 0;
                @endphp

                <div class="ssr-table-wrap shadow-sm">
                    <table class="ssr-table">
                        <thead>
                            <tr>
                                <th class="text-left ps-4">Row Labels</th>
                                <th class="text-right px-4">Sum of Total Stock in London</th>
                                <th class="text-right px-4 pe-4">Sum of Total Stock Out Qty</th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach ($style_stocks as $deptKey => $department)
                                @php
                                    $grandTotalStock += $department['stock'];
                                    $grandTotalSold += $department['sold'];
                                @endphp

                                <tr class="ssr-row ssr-row--dept department-row" data-target="department-{{ $deptKey }}">
                                    <td class="ssr-label-cell ps-4">
                                        <div class="flex items-center gap-2">
                                            <span class="ssr-toggle">
                                                <span class="ssr-chevron">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </span>
                                            </span>
                                            <span>{{ $department['department_name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="text-right px-4">
                                        @include('style_stocks.partials.progress', [
                                            'percent' => $department['stock_percent'],
                                            'value' => $department['stock'],
                                            'level' => 'dept',
                                            'type' => 'stock',
                                        ])
                                    </td>
                                    <td class="text-right px-4 pe-4">
                                        @include('style_stocks.partials.progress', [
                                            'percent' => $department['sold_percent'],
                                            'value' => $department['sold'],
                                            'displayValue' => zeroToString($department['sold']),
                                            'level' => 'dept',
                                            'type' => 'sold',
                                        ])
                                    </td>
                                </tr>

                                @foreach ($department['categories'] as $catKey => $category)
                                    <tr class="ssr-row ssr-row--cat category-row department-{{ $deptKey }} hidden"
                                        data-target="category-{{ $deptKey }}-{{ $catKey }}">
                                        <td class="ssr-label-cell ssr-label-cell--cat">
                                            <div class="flex items-center gap-2">
                                                <span class="ssr-toggle ssr-toggle--cat">
                                                    <span class="ssr-chevron">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </span>
                                                </span>
                                                <span>{{ $category['category_name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="text-right px-4">
                                            @include('style_stocks.partials.progress', [
                                                'percent' => $category['stock_percent'],
                                                'value' => $category['stock'],
                                                'level' => 'category',
                                                'type' => 'stock',
                                            ])
                                        </td>
                                        <td class="text-right px-4 pe-4">
                                            @include('style_stocks.partials.progress', [
                                                'percent' => $category['sold_percent'],
                                                'value' => $category['sold'],
                                                'displayValue' => zeroToString($category['sold']),
                                                'level' => 'category',
                                                'type' => 'sold',
                                            ])
                                        </td>
                                    </tr>

                                    @foreach ($category['products'] as $product)
                                        @php
                                            $productImageFull = !empty($product['image_link'])
                                                ? preg_replace('#/w=\d+$#', '/public', $product['image_link'])
                                                : null;
                                        @endphp
                                        <tr class="ssr-row ssr-row--product product-row category-{{ $deptKey }}-{{ $catKey }} hidden"
                                            data-has-discount="{{ !empty($product['itemPrice']['maxDiscountPrice']) && $product['itemPrice']['maxDiscountPrice'] > 0 ? '1' : '0' }}">
                                            <td class="ssr-label-cell ssr-label-cell--product">
                                                <div class="ssr-product-wrap">
                                                    @if (!empty($product['image_link']))
                                                        <a href="{{ $productImageFull }}"
                                                            data-fancybox="gallery-style-stock-{{ Str::slug($product['item_no'] ?? 'product-' . $loop->index) }}"
                                                            data-caption="{{ $product['item_no'] }}"
                                                            class="block rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 hover:scale-105 hover:shadow-md transition-all duration-150 shrink-0">
                                                            <img src="{{ $product['image_link'] }}" alt="{{ $product['item_no'] }}"
                                                                class="ssr-product-img" loading="lazy">
                                                        </a>
                                                    @endif
                                                    <div>
                                                        <a href="{{ $product['link'] }}" target="_blank"
                                                            class="ssr-product-link">{{ $product['item_no'] }}</a>
                                                        <p class="ssr-product-meta">Ecom. Price: {{ $product['ecom_price'] }}</p>
                                                        @if ($product['itemPrice']['maxDiscountPrice'] > 0)
                                                            <p class="ssr-product-meta">Dis. Price: {{ $product['discount_price'] }}</p>
                                                        @endif
                                                        <p class="ssr-product-meta">FOB Price: {{ $product['fob_price'] }}</p>

                                                        <span data-url="{{ route('admin.style.stock.discounts', $product['style']) }}" type="button" @click="drawerOpen = true"
                                                            class="flex items-center gap-2 text-[12px] transition-colors underline cursor-pointer discount-btn">Apply
                                                            discount</span>

                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right px-4">
                                                @include('style_stocks.partials.progress', [
                                                    'percent' => $product['stock_percent'],
                                                    'value' => $product['stock'],
                                                    'level' => 'product',
                                                    'type' => 'stock',
                                                ])
                                            </td>
                                            <td class="text-right px-4 pe-4">
                                                @include('style_stocks.partials.progress', [
                                                    'percent' => $product['sold_percent'],
                                                    'value' => $product['sold'],
                                                    'displayValue' => zeroToString($product['sold']),
                                                    'level' => 'product',
                                                    'type' => 'sold',
                                                ])
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach

                            @php
                                $grandTotal = $grandTotalStock + $grandTotalSold;
                                $grandStockPercent = $grandTotal > 0 ? round(($grandTotalStock / $grandTotal) * 100) : 0;
                                $grandSoldPercent = $grandTotal > 0 ? round(($grandTotalSold / $grandTotal) * 100) : 0;
                            @endphp

                            <tr class="ssr-row ssr-row--total">
                                <td class="ssr-label-cell ps-4 uppercase text-[12px] tracking-wide">Total Stock</td>
                                <td class="text-right px-4">
                                    @include('style_stocks.partials.progress', [
                                        'percent' => $grandStockPercent,
                                        'value' => $grandTotalStock,
                                        'level' => 'total',
                                        'type' => 'stock',
                                    ])
                                </td>
                                <td class="text-right px-4 pe-4">
                                    @include('style_stocks.partials.progress', [
                                        'percent' => $grandSoldPercent,
                                        'value' => $grandTotalSold,
                                        'displayValue' => zeroToString($grandTotalSold),
                                        'level' => 'total',
                                        'type' => 'sold',
                                    ])
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

            @else
                <div
                    class="flex flex-col items-center justify-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl py-16 px-6 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h2 class="text-[15px] font-semibold text-slate-800 dark:text-slate-100 mb-1">No Results Found</h2>
                    <p class="text-[12px] text-slate-400 dark:text-slate-500">There is no Warehouse Stock IN/OUT Report data
                        available to display.</p>
                </div>
            @endif

        </div>

        {{-- ── Image Lightbox ── --}}
        <div x-show="imagePopup" x-cloak @click="imagePopup = null"
            class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 cursor-zoom-out p-6"
            style="display:none;">
            <button @click="imagePopup = null"
                class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="imagePopup" class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl object-contain cursor-default"
                @click.stop>
        </div>

        {{-- Backdrop --}}
        <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="drawerOpen = false"
            class="fixed inset-0 bg-black/25 dark:bg-black/50 z-[200]" style="display:none;"></div>

        {{-- Drawer Panel --}}
        <div x-show="drawerOpen" x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed top-0 right-0 bottom-0 w-full sm:w-[700px] bg-white dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 flex flex-col z-[201] shadow-2xl"
            style="display:none;">

            {{-- Drawer Head --}}
            <div
                class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex-shrink-0">
                <div class="flex items-center gap-2 text-[15px] font-semibold text-slate-800 dark:text-slate-100 uppercase">Ecom SKU: <span id="setEcomSku"></span></div>
                <button @click="drawerOpen = false"
                    class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 flex items-center justify-center text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 hover:border-red-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Drawer Body --}}
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5" id="discountContent"></div>
            </div>
        </div>
    </div>
@endsection
