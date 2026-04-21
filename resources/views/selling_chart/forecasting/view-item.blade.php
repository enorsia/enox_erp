{{--
    Forecasting View-Item Modal
    — Injected via AJAX by viewChart() in forecasting.js
    — NO Bootstrap needed; uses Tailwind + Alpine.js tabs
--}}
<div id="viewSellingChartItemModal"
     x-data="{ imagePopup: null }"
     onclick="if(event.target===this) window.closeDiscountModal()"
     class="fixed inset-0 z-[9999] flex items-start justify-center bg-black/60 overflow-y-auto p-3 sm:p-5">

    {{-- ── Image Lightbox ── --}}
    <div x-show="imagePopup" x-cloak
         @click="imagePopup = null"
         class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 cursor-zoom-out p-6"
         style="display:none;">
        <button @click="imagePopup = null"
                class="absolute top-4 right-4 z-10 p-2 rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <img :src="imagePopup"
             class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl object-contain cursor-default"
             @click.stop>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-[1400px] my-auto shadow-2xl"
         onclick="event.stopPropagation()">

        <!-- ── HEADER ── -->
        <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 rounded-t-2xl">
            <h5 class="text-base font-semibold text-slate-800 dark:text-slate-100">Product Details</h5>
            <button onclick="window.closeDiscountModal()"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- ── BODY ── -->
        <div class="p-5 space-y-5">

            <!-- Product Info Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-3">
                @php
                    $badge = match(true) {
                        $chartInfo->status == 1 => ['label' => 'Approved',     'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
                        $chartInfo->status == 2 => ['label' => 'Rejected',     'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
                        default                 => ['label' => 'Not Approved', 'cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
                    };
                @endphp

                @foreach ([
                    'Department'            => $chartInfo->department_name,
                    'Season'                => $chartInfo->season_name,
                    'Season Phase'          => $chartInfo->phase_name,
                    'Initial/ Repeat Order' => $chartInfo->initial_repeated_status,
                    'Product Launch Month'  => $chartInfo->product_launch_month,
                    'Product Description'   => $chartInfo->product_description,
                    'Product Category'      => $chartInfo->category_name,
                    'Mini Category'         => $chartInfo->mini_category_name,
                    'Product Code'          => $chartInfo->product_code,
                    'Ecom Sku'              => ($skus['sku'] ?? ''),
                    'Design No'             => $chartInfo->design_no,
                    'Febrication'           => $chartInfo->fabrication,
                ] as $label => $value)
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">{{ $label }}</p>
                        <p class="text-[13px] text-slate-800 dark:text-slate-100 font-medium leading-snug">{{ $value ?: '—' }}</p>
                    </div>
                @endforeach

                <!-- Status badge -->
                <div>
                    <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Status</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge['cls'] }}">
                        {{ $badge['label'] }}
                    </span>
                </div>
            </div>

            <!-- Design / Inspiration Images -->
            @if ($chartInfo->design_image || $chartInfo->inspiration_image)
                <div class="flex flex-wrap gap-4">
                    @if ($chartInfo->design_image)
                        <div class="text-center">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Design Image</p>
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                                 src="{{ cloudflareImage($chartInfo->design_image, 130) }}"
                                 @click="imagePopup = '{{ cloudflareImage($chartInfo->design_image, 1200) }}'"
                                 alt="Design Image">
                        </div>
                    @endif
                    @if ($chartInfo->inspiration_image)
                        <div class="text-center">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Inspiration Image</p>
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                                 src="{{ cloudflareImage($chartInfo->inspiration_image, 130) }}"
                                 @click="imagePopup = '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}'"
                                 alt="Inspiration Image">
                        </div>
                    @endif
                </div>
            @endif

            <!-- Column Toggles -->
            <div class="flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-700/40 rounded-xl px-4 py-3">
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Show / Hide:</span>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" class="toggle-column w-3.5 h-3.5 rounded accent-accent-400" value="commission">
                    <span class="text-[12px] text-slate-600 dark:text-slate-300">Price &amp; Commission</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" class="toggle-column w-3.5 h-3.5 rounded accent-accent-400" value="vat">
                    <span class="text-[12px] text-slate-600 dark:text-slate-300">VAT details</span>
                </label>
            </div>

            <!-- ── PLATFORM TABS (Alpine.js) ── -->
            @php $firstPlatformCode = array_key_first($platform_ncs); @endphp
            <div x-data="{ tab: '{{ $firstPlatformCode }}' }">

                <!-- Tab Strip -->
                <div class="flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto pb-px">
                    @foreach ($platform_ncs as $p_code => $p_name)
                        <button type="button"
                                @click="tab = '{{ $p_code }}'"
                                :class="tab === '{{ $p_code }}'
                                    ? 'border-accent-400 text-accent-400 bg-accent-400/5'
                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
                                class="px-4 py-2 text-[13px] font-medium border-b-2 -mb-px whitespace-nowrap transition-colors">
                            {{ $p_name }}
                        </button>
                    @endforeach
                </div>

                <!-- Tab Panels -->
                @foreach ($platform_ncs as $p_code => $p_name)
                    @php $platform = $platforms->get($p_code); @endphp

                    <div x-show="tab === '{{ $p_code }}'" x-cloak>
                        <!-- Scrollable Table -->
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-[12px] border-collapse" style="min-width: max-content;">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/60">
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Color (Code)</th>
                                        @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                            <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Size Range</th>
                                        @endif
                                        <th class="toogle-item commission px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Price $(FOB)</th>
                                        <th class="toogle-item commission px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Unit Price</th>
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">CSP</th>
                                        <th class="toogle-item commission px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Commission</th>
                                        <th class="toogle-item commission px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Com. VAT</th>
                                        <th class="toogle-item commission px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Selling Price</th>
                                        <th class="toogle-item vat px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">20% Sel. VAT</th>
                                        <th class="toogle-item vat px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">VAT Value £</th>
                                        <th class="toogle-item vat px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">SP + VAT</th>
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">PM %</th>
                                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Net Profit</th>
                                        <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Can Sell</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/40">
                                    @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                        @php
                                            $profit_cal = calculatePlatformProfit($ch_price, $platform);
                                            $canSell    = ($profit_cal['can_sell'] ?? 'No') === 'Yes';
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                                            <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {{ $ch_price->color_name }}
                                                <span class="text-slate-400 font-normal">({{ $ch_price->color_code }})</span>
                                            </td>
                                            @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $ch_price->range }}</td>
                                            @endif
                                            <td class="toogle-item commission px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap" style="display:none">$@pricews($ch_price->price_fob)</td>
                                            <td class="toogle-item commission px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap" style="display:none">@price($ch_price->unit_price)</td>
                                            <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">@price($ch_price->confirm_selling_price)</td>
                                            <td class="toogle-item commission px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap com" style="display:none">@price($profit_cal['commission'])</td>
                                            <td class="toogle-item commission px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap com-vat" style="display:none">@price($profit_cal['commission_vat'])</td>
                                            <td class="toogle-item commission px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap sp" style="display:none">@price($profit_cal['selling_price'])</td>
                                            <td class="toogle-item vat px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap sl-vat" style="display:none">@price($profit_cal['selling_vat'])</td>
                                            <td class="toogle-item vat px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap vat-val" style="display:none">@price($profit_cal['vat_value'])</td>
                                            <td class="toogle-item vat px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap sp-vat" style="display:none">@price($profit_cal['selling_price_and_vat'])</td>
                                            <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap pm">@pricews($profit_cal['profit_margin'])%</td>
                                            <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap np">@price($profit_cal['net_profit'])</td>
                                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full
                                                    {{ $canSell
                                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                        : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                                    {{ $canSell ? '✓' : '✗' }} {{ $profit_cal['can_sell'] ?? 'No' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

            </div>{{-- /Alpine tabs --}}
        </div>{{-- /body --}}
    </div>{{-- /panel --}}
</div>{{-- /overlay --}}
