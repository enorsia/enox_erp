@php
    $isGirlsBoys = in_array($chartInfo->department_id, [1928, 1929]);
    $isMenWomen  = in_array($chartInfo->department_id, [1926, 1927]);
    $rowCount    = count($chartInfo->sellingChartPrices);
    $firstPrice  = $chartInfo->sellingChartPrices->first();
    $firstDiscount = $firstPrice?->discounts->where('platform_id', $platform->id)->first();
    $groupCostBasis = $firstDiscount?->cost_basis ?? 'unit';
    $groupShipping  = $firstDiscount?->shipping_cost ?? $defaultShippingCost;
    $groupStatus    = $firstDiscount?->status ?? 0;
@endphp

<div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 mb-3">
    <table class="w-full text-[11px] border-collapse discount-calc-table" style="min-width: max-content;">
        <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/60">
                <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Color</th>
                @if ($isGirlsBoys)
                    <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Range</th>
                @endif
                <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-24">Discount</th>
                {{-- <th class="px-2 py-2 text-center text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-14">Status</th> --}}
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none" title="FOB $ converted to £">FOB ($→£)</th>
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Unit</th>
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Shipping</th>
                <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">CSP</th>
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Com.</th>
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Com VAT</th>
                <th class="toogle-item commission px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">Sell</th>
                <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">VAT</th>
                <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">VAT £</th>
                <th class="toogle-item vat px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700" style="display:none">SP+VAT</th>
                <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">PM%</th>
                <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">NP</th>
                {{-- <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700">Dis.(%)</th> --}}
                {{-- <th class="px-2 py-2 text-left text-[9px] font-semibold text-slate-500 uppercase whitespace-nowrap border-b border-slate-200 dark:border-slate-700 w-36">Action</th> --}}
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/40">
            @foreach ($chartInfo->sellingChartPrices as $ch_price)
                @php
                    $d_price    = $ch_price?->discounts->where('platform_id', $platform->id)->first();
                    $h_ch_price = clone $ch_price;
                    if ($d_price) { $h_ch_price->confirm_selling_price = $d_price->price; }

                    $originalShipping = (float) ($ch_price->product_shipping_cost ?: $defaultShippingCost);
                    $rowCostBasis = $isMenWomen ? $groupCostBasis : ($d_price?->cost_basis ?? 'unit');
                    $rowShippingCost = $isMenWomen ? $groupShipping : ($d_price?->shipping_cost ?? $defaultShippingCost);
                    $fobPound = convertFobUsdToPound((float) $ch_price->price_fob, $conversionRate);

                    $profit_cal = calculatePlatformProfit($h_ch_price, $platform, [
                        'cost_basis' => $rowCostBasis,
                        'shipping_cost' => $rowShippingCost,
                        'original_shipping' => $originalShipping,
                        'conversion_rate' => $conversionRate,
                        'default_shipping' => $defaultShippingCost,
                    ]);

                    $original_profit_cal = calculatePlatformProfit($h_ch_price, $platform, [
                        'cost_basis' => $rowCostBasis,
                        'shipping_cost' => $originalShipping,
                        'original_shipping' => $originalShipping,
                        'conversion_rate' => $conversionRate,
                        'default_shipping' => $defaultShippingCost,
                    ]);
                @endphp
                <tr class="discount-calc-row hover:bg-slate-50/60 dark:hover:bg-slate-700/20"
                    data-price-id="{{ $ch_price->id }}"
                    data-original-shipping="{{ number_format($originalShipping, 2, '.', '') }}"
                    data-original-profit='@json($original_profit_cal)'>

                    <input type="hidden" name="ch_price_id[{{ $ch_price->id }}]" class="ch_price_id" value="{{ $ch_price->id }}" />
                    <input type="hidden" name="sl_price_id[]" value="{{ $ch_price->id }}" />

                    {{-- Color --}}
                    <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">
                        {{ $ch_price->color_name }}<span class="text-slate-400 font-normal"> ({{ $ch_price->color_code }})</span>
                    </td>

                    @if ($isGirlsBoys)
                        <td class="px-2 py-1.5 text-slate-500 whitespace-nowrap">{{ $ch_price->range }}</td>
                        <td class="px-2 py-1.5">
                            <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                   data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                   class="w-full px-1.5 py-0.5 text-center text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-red-500 placeholder-slate-300 focus:outline-none focus:border-accent-400 discount_price discount_price{{ $ch_price->id }}"
                                   placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                        </td>
                    @elseif ($isMenWomen)
                        @if ($loop->first)
                            <td class="px-2 py-1.5" rowspan="{{ $rowCount }}">
                                <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                       data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                       class="w-full px-1.5 py-0.5 text-center text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-red-500 placeholder-slate-300 focus:outline-none focus:border-accent-400 discount_price discount_price{{ $ch_price->id }}"
                                       placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                            </td>
                        @endif
                    @else
                        <td class="px-2 py-1.5">
                            <input type="text" name="discount_price[{{ $ch_price->id }}]"
                                   data-price-id="{{ $ch_price->id }}" data-csp="{{ $ch_price->confirm_selling_price }}"
                                   class="w-full px-1.5 py-0.5 text-center text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-red-500 placeholder-slate-300 focus:outline-none focus:border-accent-400 discount_price discount_price{{ $ch_price->id }}"
                                   placeholder="0.00" value="{{ $d_price?->price ?? '' }}">
                        </td>
                    @endif

                    {{-- Status (merged for men/women & girls/boys) --}}
                    {{-- @if ($loop->first && ($isMenWomen || $isGirlsBoys))
                        <td class="px-2 py-1.5 text-center" rowspan="{{ $rowCount }}">
                            @can('general.discounts.approve')
                                <input type="checkbox" role="switch" name="group_status"
                                       class="group-status-toggle w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer"
                                       value="1" {{ $groupStatus ? 'checked' : '' }}>
                            @else
                                @if ($firstDiscount)
                                    @if ($groupStatus == 1)
                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">OK</span>
                                    @else
                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Pend</span>
                                    @endif
                                @endif
                            @endcan
                        </td>
                    @elseif (!$isMenWomen && !$isGirlsBoys)
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
                    @endif --}}

                    {{-- FOB / Unit / Shipping — single row for men/women --}}
                    @if ($isMenWomen)
                        @if ($loop->first)
                            @php $mwFobPound = convertFobUsdToPound((float) $firstPrice->price_fob, $conversionRate); @endphp
                            <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none" rowspan="{{ $rowCount }}">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="cost_basis[{{ $firstPrice->id }}]" value="fob"
                                           class="cost-basis-radio w-3.5 h-3.5 accent-accent-400"
                                           data-price-id="{{ $firstPrice->id }}" data-basis="fob"
                                           {{ $groupCostBasis === 'fob' ? 'checked' : '' }}>
                                    <span class="fob-pound-value" title="${{ number_format((float) $firstPrice->price_fob, 2) }} FOB">£{{ number_format($mwFobPound, 2) }}</span>
                                </label>
                            </td>
                            <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none" rowspan="{{ $rowCount }}">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="cost_basis[{{ $firstPrice->id }}]" value="unit"
                                           class="cost-basis-radio w-3.5 h-3.5 accent-accent-400"
                                           data-price-id="{{ $firstPrice->id }}" data-basis="unit"
                                           {{ $groupCostBasis === 'unit' ? 'checked' : '' }}>
                                    <span class="unit-price-original">@price($firstPrice->unit_price)</span>
                                    <small class="adjusted-unit-price block text-[9px] text-blue-600 dark:text-blue-400 leading-tight {{ ($profit_cal['adjusted_unit_price'] ?? null) ? '' : 'hidden' }}">
                                        @if ($profit_cal['adjusted_unit_price'] ?? null)
                                            @price($profit_cal['adjusted_unit_price'])
                                        @endif
                                    </small>
                                </label>
                            </td>
                            <td class="toogle-item commission px-2 py-1.5" style="display:none" rowspan="{{ $rowCount }}">
                                <input type="number" step="0.01" min="0"
                                       name="shipping_cost[{{ $firstPrice->id }}]"
                                       data-price-id="{{ $firstPrice->id }}"
                                       class="shipping-cost-input w-14 px-1 py-0.5 text-center text-[10px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400"
                                       value="{{ number_format($groupShipping, 2, '.', '') }}">
                            </td>
                            <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap" rowspan="{{ $rowCount }}">@price($firstPrice->confirm_selling_price)</td>
                            <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['commission'])</td>
                            <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com-vat" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['commission_vat'])</td>
                            <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap sp" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['selling_price'])</td>
                            <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sl-vat" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['selling_vat'])</td>
                            <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap vat-val" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['vat_value'])</td>
                            <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sp-vat" style="display:none" rowspan="{{ $rowCount }}">@price($profit_cal['selling_price_and_vat'])</td>
                            <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap pm" rowspan="{{ $rowCount }}">@pricews($profit_cal['profit_margin'])%</td>
                            <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap np" rowspan="{{ $rowCount }}">@price($profit_cal['net_profit'])</td>
                        @endif
                    @else
                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="cost_basis[{{ $ch_price->id }}]" value="fob"
                                       class="cost-basis-radio w-3.5 h-3.5 accent-accent-400"
                                       data-price-id="{{ $ch_price->id }}" data-basis="fob"
                                       {{ $rowCostBasis === 'fob' ? 'checked' : '' }}>
                                <span class="fob-pound-value" title="${{ number_format((float) $ch_price->price_fob, 2) }} FOB">£{{ number_format($fobPound, 2) }}</span>
                            </label>
                        </td>
                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap" style="display:none">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="cost_basis[{{ $ch_price->id }}]" value="unit"
                                       class="cost-basis-radio w-3.5 h-3.5 accent-accent-400"
                                       data-price-id="{{ $ch_price->id }}" data-basis="unit"
                                       {{ $rowCostBasis === 'unit' ? 'checked' : '' }}>
                                <span class="unit-price-original">@price($ch_price->unit_price)</span>
                                <small class="adjusted-unit-price block text-[9px] text-blue-600 dark:text-blue-400 leading-tight {{ ($profit_cal['adjusted_unit_price'] ?? null) ? '' : 'hidden' }}">
                                    @if ($profit_cal['adjusted_unit_price'] ?? null)
                                        @price($profit_cal['adjusted_unit_price'])
                                    @endif
                                </small>
                            </label>
                        </td>
                        <td class="toogle-item commission px-2 py-1.5" style="display:none">
                            <input type="number" step="0.01" min="0"
                                   name="shipping_cost[{{ $ch_price->id }}]"
                                   data-price-id="{{ $ch_price->id }}"
                                   class="shipping-cost-input w-14 px-1 py-0.5 text-center text-[10px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400"
                                   value="{{ number_format($rowShippingCost, 2, '.', '') }}">
                        </td>
                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap">@price($ch_price->confirm_selling_price)</td>
                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com" style="display:none">@price($profit_cal['commission'])</td>
                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap com-vat" style="display:none">@price($profit_cal['commission_vat'])</td>
                        <td class="toogle-item commission px-2 py-1.5 text-slate-600 whitespace-nowrap sp" style="display:none">@price($profit_cal['selling_price'])</td>
                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sl-vat" style="display:none">@price($profit_cal['selling_vat'])</td>
                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap vat-val" style="display:none">@price($profit_cal['vat_value'])</td>
                        <td class="toogle-item vat px-2 py-1.5 text-slate-600 whitespace-nowrap sp-vat" style="display:none">@price($profit_cal['selling_price_and_vat'])</td>
                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap pm">@pricews($profit_cal['profit_margin'])%</td>
                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap np">@price($profit_cal['net_profit'])</td>
                    @endif

                    {{-- @if ($loop->first)
                        <td class="px-2 py-1.5" rowspan="{{ $rowCount }}">
                            <select name="save_type" class="save_type w-full px-1.5 py-1 text-[11px] border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-accent-400">
                                <option value="1">Save</option>
                                @can('general.discounts.sent_mail')
                                    <option value="2">Save &amp; Approval</option>
                                    <option value="3">Save &amp; Executor</option>
                                @endcan
                            </select>
                        </td>
                    @endif --}}
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
