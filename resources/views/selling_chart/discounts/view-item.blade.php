{{--
    Discount View-Item Modal
    — Injected via AJAX by viewChart() in discounts.js
    — NO Bootstrap needed; uses Tailwind + Alpine.js tabs
--}}
<div id="viewSellingChartItemModal"
     onclick="if(event.target===this) window.closeDiscountModal()"
     class="fixed inset-0 z-[9999] flex items-start justify-center bg-black/60 overflow-y-auto p-3 sm:p-5">

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
                    'Department'       => $chartInfo->department_name,
                    'Season'           => $chartInfo->season_name,
                    'Season Phase'     => $chartInfo->phase_name,
                    'Initial / Repeat' => $chartInfo->initial_repeated_status,
                    'Launch Month'     => $chartInfo->product_launch_month,
                    'Description'      => $chartInfo->product_description,
                    'Product Category' => $chartInfo->category_name,
                    'Mini Category'    => $chartInfo->mini_category_name,
                    'Product Code'     => $chartInfo->product_code,
                    'Ecom SKU'         => ($skus['sku'] ?? ''),
                    'Design No'        => $chartInfo->design_no,
                    'Fabrication'      => $chartInfo->fabrication,
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
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700"
                                 src="{{ cloudflareImage($chartInfo->design_image, 130) }}" alt="Design">
                        </div>
                    @endif
                    @if ($chartInfo->inspiration_image)
                        <div class="text-center">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1">Inspiration Image</p>
                            <img class="w-28 h-28 rounded-xl object-cover border border-slate-200 dark:border-slate-700"
                                 src="{{ cloudflareImage($chartInfo->inspiration_image, 130) }}" alt="Inspiration">
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
                        <form class="pp-form"
                              action="{{ route('admin.selling_chart.save.platform.discount.price') }}"
                              method="POST">
                            @csrf
                            <input type="hidden" name="platform_id"   class="platform_id"   value="{{ $platform->id }}" />
                            <input type="hidden" name="department_id" class="department_id" value="{{ $chartInfo->department_id }}" />

                            <!-- Scrollable Table -->
                            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 mb-4">
                                <table class="w-full text-[12px] border-collapse" style="min-width: max-content;">
                                    <thead>
                                        <tr class="bg-slate-50 dark:bg-slate-800/60">
                                            <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider border-b border-slate-200 dark:border-slate-700 w-10">✓</th>
                                            <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Color (Code)</th>
                                            @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Size Range</th>
                                            @endif
                                            <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-28">Discount</th>
                                            <th class="px-3 py-2.5 text-center text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-16">Status</th>
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
                                            <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-44">Save Type</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/40">
                                        @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                            @php
                                                $d_price    = $ch_price?->discounts->where('platform_id', $platform->id)->first();
                                                $h_ch_price = clone $ch_price;
                                                if ($d_price) { $h_ch_price->confirm_selling_price = $d_price->price; }
                                                $profit_cal = calculatePlatformProfit($h_ch_price, $platform);
                                            @endphp
                                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition-colors">
                                                <input type="hidden" name="ch_price_id[{{ $ch_price->id }}]" class="ch_price_id" value="{{ $ch_price->id }}" />

                                                @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                                    {{-- Per-row inputs for dept 1928 & 1929 --}}
                                                    <td class="px-3 py-2.5 text-center">
                                                        <input type="checkbox" name="sl_price_id[]" value="{{ $ch_price->id }}"
                                                               class="w-4 h-4 rounded accent-accent-400 cursor-pointer">
                                                    </td>
                                                    <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                        {{ $ch_price->color_name }}
                                                        <span class="text-slate-400 font-normal">({{ $ch_price->color_code }})</span>
                                                    </td>
                                                    <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $ch_price->range }}</td>
                                                    <td class="px-3 py-2.5">
                                                        <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                                               data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                                               class="w-full px-2 py-1 text-center text-[12px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-red-500 dark:text-red-400 placeholder-slate-300 focus:outline-none focus:border-accent-400 transition-colors discount_price discount_price{{ $ch_price->id }}"
                                                               placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                                                    </td>
                                                    <td class="px-3 py-2.5 text-center">
                                                        @can('general.discounts.approve')
                                                            @if ($d_price)
                                                                <input type="checkbox" role="switch" name="statuses[{{ $ch_price->id }}]"
                                                                       class="status{{ $ch_price->id }} w-4 h-4 rounded accent-accent-400 cursor-pointer"
                                                                       {{ $d_price?->status ? 'checked' : '' }}>
                                                            @endif
                                                        @else
                                                            @if ($d_price)
                                                                @if ($d_price->status == 1)
                                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Approved</span>
                                                                @else
                                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Pending</span>
                                                                @endif
                                                            @endif
                                                        @endcan
                                                    </td>
                                                @else
                                                    {{-- Grouped: shared checkbox + discount + status (rowspan) --}}
                                                    @if ($loop->index == 0)
                                                        <td class="px-3 py-2.5 text-center" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                            <input type="checkbox" name="sl_price_id[]" value="{{ $ch_price->id }}"
                                                                   class="w-4 h-4 rounded accent-accent-400 cursor-pointer">
                                                        </td>
                                                    @endif
                                                    <td class="px-3 py-2.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                        {{ $ch_price->color_name }}
                                                        <span class="text-slate-400 font-normal">({{ $ch_price->color_code }})</span>
                                                    </td>
                                                    @if ($loop->index == 0)
                                                        <td class="px-3 py-2.5" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                            <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                                                   data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                                                   class="w-full px-2 py-1 text-center text-[12px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-red-500 dark:text-red-400 placeholder-slate-300 focus:outline-none focus:border-accent-400 transition-colors discount_price discount_price{{ $ch_price->id }}"
                                                                   placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                                                        </td>
                                                        <td class="px-3 py-2.5 text-center" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                            @can('general.discounts.approve')
                                                                @if ($d_price)
                                                                    <input type="checkbox" role="switch" name="statuses[{{ $ch_price->id }}]"
                                                                           class="status{{ $ch_price->id }} w-4 h-4 rounded accent-accent-400 cursor-pointer"
                                                                           {{ $d_price?->status ? 'checked' : '' }}>
                                                                @endif
                                                            @else
                                                                @if ($d_price)
                                                                    @if ($d_price->status == 1)
                                                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Approved</span>
                                                                    @else
                                                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Pending</span>
                                                                    @endif
                                                                @endif
                                                            @endcan
                                                        </td>
                                                    @endif
                                                @endif

                                                {{-- Calculated columns --}}
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

                                                {{-- Save Type — first row only, spans all --}}
                                                @if ($loop->index == 0)
                                                    <td class="px-3 py-2.5" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                        <select name="save_type" class="save_type w-full px-2 py-1.5 text-[12px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400 transition-colors">
                                                            <option value="1">Save</option>
                                                            @can('general.discounts.sent_mail')
                                                                <option value="2">Save &amp; Send for Approval</option>
                                                                <option value="3">Save &amp; Send to Executor</option>
                                                            @endcan
                                                        </select>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @can('general.discounts.update')
                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="submit-btn flex items-center gap-2 px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Save
                                    </button>
                                </div>
                            @endcan
                        </form>
                    </div>
                @endforeach

            </div>{{-- /Alpine tabs --}}
        </div>{{-- /body --}}
    </div>{{-- /panel --}}
</div>{{-- /overlay --}}
