{{-- Expandable edit panel — general info + per-platform calculation forms --}}
@php
    $ecommerceSku = ($ecommerceProduct['sku'] ?? '') ?: '—';
    $badge = match(true) {
        $chartInfo->status == 1 => ['label' => 'Approved',     'cls' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
        $chartInfo->status == 2 => ['label' => 'Rejected',     'cls' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'],
        default                 => ['label' => 'Not Approved', 'cls' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
    };
    $platformKeys = array_keys($platform_ncs);
    $platformDefaults = collect($platformKeys)->mapWithKeys(fn ($k) => [$k => false])->toArray();
@endphp

<div class="discount-edit-panel border-t border-slate-100 dark:border-slate-700"
     x-data="{ showDetails: false, openPlatforms: @js($platformDefaults) }">

    {{-- Toggle: General / Product Details --}}
    <button type="button" @click="showDetails = !showDetails"
            class="w-full flex items-center justify-between px-4 py-2.5 text-left bg-slate-50/80 dark:bg-slate-700/30 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
        <span class="flex items-center gap-2 text-[12px] font-semibold text-slate-600 dark:text-slate-300">
            <svg class="w-3.5 h-3.5 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Product Details
        </span>
        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="showDetails && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- General Information --}}
    <div x-show="showDetails" x-collapse class="px-4 py-3 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-2.5">
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
                'Ecom SKU'         => $ecommerceSku,
                'Design No'        => $chartInfo->design_no,
                'Fabrication'      => $chartInfo->fabrication,
            ] as $label => $value)
                <div>
                    <p class="text-[9px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">{{ $label }}</p>
                    <p class="text-[12px] text-slate-700 dark:text-slate-200 font-medium leading-snug truncate" title="{{ $value }}">{{ $value ?: '—' }}</p>
                </div>
            @endforeach
            <div>
                <p class="text-[9px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-0.5">Status</p>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badge['cls'] }}">{{ $badge['label'] }}</span>
            </div>
        </div>

        @if ($chartInfo->design_image || $chartInfo->inspiration_image)
            <div class="flex flex-wrap gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                @if ($chartInfo->design_image)
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-slate-400 uppercase mb-1">Design</p>
                        <img class="w-16 h-16 rounded-lg object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                             src="{{ cloudflareImage($chartInfo->design_image, 130) }}"
                             @click="$dispatch('discount-image-popup', '{{ cloudflareImage($chartInfo->design_image, 1200) }}')"
                             alt="Design">
                    </div>
                @endif
                @if ($chartInfo->inspiration_image)
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-slate-400 uppercase mb-1">Inspiration</p>
                        <img class="w-16 h-16 rounded-lg object-cover border border-slate-200 dark:border-slate-700 cursor-zoom-in hover:opacity-90 transition-opacity"
                             src="{{ cloudflareImage($chartInfo->inspiration_image, 130) }}"
                             @click="$dispatch('discount-image-popup', '{{ cloudflareImage($chartInfo->inspiration_image, 1200) }}')"
                             alt="Inspiration">
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Column Toggles --}}
    <div class="flex flex-wrap items-center gap-2.5 px-4 py-2 bg-slate-50/60 dark:bg-slate-700/20 border-b border-slate-100 dark:border-slate-700">
        <span class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Columns:</span>
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" class="toggle-column w-3 h-3 rounded accent-accent-400" value="commission">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">Price &amp; Commission</span>
        </label>
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" class="toggle-column w-3 h-3 rounded accent-accent-400" value="vat">
            <span class="text-[11px] text-slate-500 dark:text-slate-400">VAT details</span>
        </label>
    </div>

    {{-- Platform Checkboxes --}}
    <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
        <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Platforms</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($platform_ncs as $p_code => $p_name)
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border cursor-pointer select-none transition-colors text-[11px] font-medium"
                       :class="openPlatforms['{{ $p_code }}']
                           ? 'border-accent-300 bg-accent-50 text-accent-600 dark:border-accent-600 dark:bg-accent-800/30 dark:text-accent-300'
                           : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:border-slate-300'">
                    <input type="checkbox" class="w-3 h-3 rounded accent-accent-400"
                           x-model="openPlatforms['{{ $p_code }}']">
                    {{ $p_name }}
                </label>
            @endforeach
        </div>
    </div>

    {{-- Platform Calculation Panels --}}
    <div class="p-4 space-y-4">
        @foreach ($platform_ncs as $p_code => $p_name)
            @php $platform = $platforms->get($p_code); @endphp

            <div x-show="openPlatforms['{{ $p_code }}']" x-collapse
                 class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-700/40 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">{{ $p_name }}</span>
                </div>

                <form class="pp-form p-3"
                      action="{{ route('admin.selling_chart.save.platform.discount.price') }}"
                      method="POST"
                      data-chart-id="{{ $chartInfo->id }}">
                    @csrf
                    <input type="hidden" name="platform_id"   class="platform_id"   value="{{ $platform->id }}" />
                    <input type="hidden" name="department_id" class="department_id" value="{{ $chartInfo->department_id }}" />

                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 mb-3">
                        <table class="w-full text-[11px] border-collapse discount-calc-table" style="min-width: max-content;">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/60">
                                    <th class="px-2 py-2 text-center text-[9px] font-semibold text-slate-500 uppercase border-b border-slate-200 dark:border-slate-700 w-8">✓</th>
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Color</th>
                                    @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                        <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Range</th>
                                    @endif
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-24">Discount</th>
                                    <th class="px-2 py-2 text-center text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-14">Status</th>
                                    <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">FOB</th>
                                    <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Unit</th>
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">CSP</th>
                                    <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Com.</th>
                                    <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Com VAT</th>
                                    <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Sell</th>
                                    <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">VAT</th>
                                    <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">VAT £</th>
                                    <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">SP+VAT</th>
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">PM%</th>
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">NP</th>
                                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-36">Action</th>
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
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20">
                                        <input type="hidden" name="ch_price_id[{{ $ch_price->id }}]" class="ch_price_id" value="{{ $ch_price->id }}" />

                                        @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                                            <td class="px-2 py-1.5 text-center">
                                                <input type="checkbox" name="sl_price_id[]" value="{{ $ch_price->id }}"
                                                       class="w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer">
                                            </td>
                                            <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {{ $ch_price->color_name }}<span class="text-slate-400 font-normal"> ({{ $ch_price->color_code }})</span>
                                            </td>
                                            <td class="px-2 py-1.5 text-slate-500 whitespace-nowrap">{{ $ch_price->range }}</td>
                                            <td class="px-2 py-1.5">
                                                <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                                       data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                                       class="w-full px-1.5 py-0.5 text-center text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-red-500 placeholder-slate-300 focus:outline-none focus:border-accent-400 discount_price discount_price{{ $ch_price->id }}"
                                                       placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                                            </td>
                                            <td class="px-2 py-1.5 text-center">
                                                @can('general.discounts.approve')
                                                    @if ($d_price)
                                                        <input type="checkbox" role="switch" name="statuses[{{ $ch_price->id }}]"
                                                               class="status{{ $ch_price->id }} w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer"
                                                               {{ $d_price?->status ? 'checked' : '' }}>
                                                    @endif
                                                @else
                                                    @if ($d_price)
                                                        @if ($d_price->status == 1)
                                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">OK</span>
                                                        @else
                                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Pend</span>
                                                        @endif
                                                    @endif
                                                @endcan
                                            </td>
                                        @else
                                            @if ($loop->index == 0)
                                                <td class="px-2 py-1.5 text-center" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                    <input type="checkbox" name="sl_price_id[]" value="{{ $ch_price->id }}"
                                                           class="w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer">
                                                </td>
                                            @endif
                                            <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                                                {{ $ch_price->color_name }}<span class="text-slate-400 font-normal"> ({{ $ch_price->color_code }})</span>
                                            </td>
                                            @if ($loop->index == 0)
                                                <td class="px-2 py-1.5" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                    <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                                           data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                                           class="w-full px-1.5 py-0.5 text-center text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-red-500 placeholder-slate-300 focus:outline-none focus:border-accent-400 discount_price discount_price{{ $ch_price->id }}"
                                                           placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                                                </td>
                                                <td class="px-2 py-1.5 text-center" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                    @can('general.discounts.approve')
                                                        @if ($d_price)
                                                            <input type="checkbox" role="switch" name="statuses[{{ $ch_price->id }}]"
                                                                   class="status{{ $ch_price->id }} w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer"
                                                                   {{ $d_price?->status ? 'checked' : '' }}>
                                                        @endif
                                                    @else
                                                        @if ($d_price)
                                                            @if ($d_price->status == 1)
                                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">OK</span>
                                                            @else
                                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Pend</span>
                                                            @endif
                                                        @endif
                                                    @endcan
                                                </td>
                                            @endif
                                        @endif

                                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none">$@pricews($ch_price->price_fob)</td>
                                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none">@price($ch_price->unit_price)</td>
                                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">@price($ch_price->confirm_selling_price)</td>
                                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com" style="display:none">@price($profit_cal['commission'])</td>
                                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com-vat" style="display:none">@price($profit_cal['commission_vat'])</td>
                                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap sp" style="display:none">@price($profit_cal['selling_price'])</td>
                                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sl-vat" style="display:none">@price($profit_cal['selling_vat'])</td>
                                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap vat-val" style="display:none">@price($profit_cal['vat_value'])</td>
                                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sp-vat" style="display:none">@price($profit_cal['selling_price_and_vat'])</td>
                                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap pm">@pricews($profit_cal['profit_margin'])%</td>
                                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap np">@price($profit_cal['net_profit'])</td>

                                        @if ($loop->index == 0)
                                            <td class="px-2 py-1.5" rowspan="{{ count($chartInfo->sellingChartPrices) }}">
                                                <select name="save_type" class="save_type w-full px-1.5 py-1 text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400">
                                                    <option value="1">Save</option>
                                                    @can('general.discounts.sent_mail')
                                                        <option value="2">Save &amp; Approval</option>
                                                        <option value="3">Save &amp; Executor</option>
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
                                    class="submit-btn flex items-center gap-1.5 px-4 py-1.5 text-[12px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                Save {{ $p_name }}
                            </button>
                        </div>
                    @endcan
                </form>
            </div>
        @endforeach
    </div>
</div>
