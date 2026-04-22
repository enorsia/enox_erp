@extends('layouts.app')

@section('title', 'Bulk Edit Selling Chart')

@section('content')
    {{-- Page-level config for selling-chart.js --}}
    <div id="selling-chart-form-content"></div>

    <div class="px-5 py-6">

        {{-- PAGE HEADER --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Bulk Edit</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Edit selling chart price data in bulk
                </p>
            </div>
            <a href="{{ route('admin.selling_chart.index') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to List
            </a>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">
            <form action="{{ route('admin.selling_chart.bulk.update') }}" method="POST"
                  id="bulk_form">
                @csrf

                {{-- Sticky scrollable table --}}
                <div class="sticky-table">
                    <table class="w-full text-[11px] border-collapse selling_chart_edit_table">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="tbl-th sticky left-0 bg-slate-50 dark:bg-slate-700/50 z-20" style="width:44px">Check
                            </th>
                            <th class="tbl-th" style="width:160px">Design No</th>
                            <th class="tbl-th" style="width:70px">Design</th>
                            <th class="tbl-th" style="width:70px">Inspiration</th>
                            <th class="tbl-th" style="width:130px">Color Code</th>
                            <th class="tbl-th" style="width:130px">Color Name</th>

                            @if (
                                !$chartInfos->isEmpty() &&
                                ($chartInfos[0]['department_id'] == 1928 ||
                                 $chartInfos[0]['department_id'] == 1929 ||
                                 !request('department_id')))
                                <th class="tbl-th" style="width:120px">Range</th>
                            @endif

                            <th class="tbl-th" style="width:80px">PO Qty</th>
                            <th class="tbl-th" style="width:80px">FOB ($)</th>
                            <th class="tbl-th" style="width:80px">Unit (£)</th>
                            <th class="tbl-th" style="width:80px">Shipping</th>
                            <th class="tbl-th" style="width:80px">CSP (£)</th>
                            <th class="tbl-th" style="width:80px">VAT (£)</th>
                            <th class="tbl-th" style="width:80px">VAT Val</th>
                            <th class="tbl-th" style="width:80px">PM %</th>
                            <th class="tbl-th" style="width:80px">Net Profit</th>
                            <th class="tbl-th" style="width:80px">Discount %</th>
                            <th class="tbl-th" style="width:80px">Disc. CSP</th>
                            <th class="tbl-th" style="width:80px">Disc. VAT</th>
                            <th class="tbl-th" style="width:80px">Disc. VAT Val</th>
                            <th class="tbl-th" style="width:80px">Disc. PM %</th>
                            <th class="tbl-th" style="width:80px">Disc. NP</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if (!$chartInfos->isEmpty())
                            @foreach ($chartInfos as $chartInfo)
                                @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                    @php
                                        $season_name_year = preg_replace('/\D/', '', $chartInfo->season_name);
                                        $digit_count      = strlen($season_name_year);
                                        $current_year     = date('Y');
                                        $current_century  = substr($current_year, 0, -$digit_count);
                                        $season_year      = $current_century . trim($season_name_year);
                                        $expense          = $expenses->where('year', (int) $season_year)->first();
                                    @endphp
                                    <tr
                                        class="border-b border-slate-100 dark:border-slate-700/60 hover:bg-slate-50/50 dark:hover:bg-slate-700/20">
                                        <td class="px-2 py-1.5 text-center sticky left-0 bg-white dark:bg-slate-800">
                                            <input type="checkbox" name="price_id[]" value="{{ $ch_price->id }}"
                                                   class="w-3.5 h-3.5 rounded accent-accent-400 cursor-pointer">
                                            <input type="hidden" name="price_id_all[]" value="{{ $ch_price->id }}">
                                            <input class="expense_input" type="hidden" name="expense_input"
                                                   value="{{ $expense?->year }}"
                                                   data-department="{{ request('department_id') }}"
                                                   data-conversion-rate="{{ $expense?->conversion_rate ?? 0 }}"
                                                   data-commercial-expense="{{ $expense?->commercial_expense ?? 0 }}"
                                                   data-enorsia-bd-expense="{{ $expense?->enorsia_expense_bd ?? 0 }}"
                                                   data-enorsia-uk-expense="{{ $expense?->enorsia_expense_uk ?? 0 }}"
                                                   data-shipping-cost="{{ $expense?->shipping_cost ?? 0 }}">
                                        </td>
                                        <td class="px-2 py-1.5 font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                            {{ $chartInfo->design_no }}</td>
                                        <td class="px-2 py-1.5">
                                            @if ($chartInfo->design_image)
                                                <img class="w-10 h-10 rounded object-cover"
                                                     src="{{ cloudflareImage($chartInfo->design_image, 50) }}"
                                                     alt="Design">
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5">
                                            @if ($chartInfo->inspiration_image)
                                                <img class="w-10 h-10 rounded object-cover"
                                                     src="{{ cloudflareImage($chartInfo->inspiration_image, 50) }}"
                                                     alt="Inspiration">
                                            @endif
                                        </td>
                                        <td class="px-2 py-1.5"><input readonly value="{{ $ch_price->color_code }}"
                                                                        type="text" name="color_code[]"
                                                                        class="tbl-input color_code"></td>
                                        <td class="px-2 py-1.5"><input readonly value="{{ $ch_price->color_name }}"
                                                                        type="text" name="color_name[]"
                                                                        class="tbl-input color_name"></td>

                                        @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929 || !request('department_id'))
                                            <td class="px-2 py-1.5" style="min-width:110px">
                                                <select name="range_id[]" class="tbl-input ctmr">
                                                    <option value="">Select range</option>
                                                    @foreach ($ranges as $range)
                                                        <option value="{{ $range->id }}"
                                                            {{ $range->id == $ch_price->range_id ? 'selected' : '' }}>
                                                            {{ $range->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif

                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->po_order_qty }}"
                                                                        type="number" name="po_order_qty[]"
                                                                        class="tbl-input po_order_qty ctmr"></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->price_fob }}"
                                                                        type="number" name="price_fob[]"
                                                                        class="tbl-input price_fob ctmr"></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->unit_price }}"
                                                                        type="number" name="unit_price[]"
                                                                        class="tbl-input unit_price" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->product_shipping_cost ?: ($expense->shipping_cost ?? 0) }}"
                                                                        type="number" name="shipping_cost[]"
                                                                        class="tbl-input shipping_cost"></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->confirm_selling_price }}"
                                                                        type="number" name="confirm_selling_price[]"
                                                                        class="tbl-input confirm_selling_price"></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->vat_price }}"
                                                                        type="number" name="seling_vat[]"
                                                                        class="tbl-input seling_vat" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->vat_value }}"
                                                                        type="number" name="seling_vat_value[]"
                                                                        class="tbl-input seling_vat_value" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->profit_margin }}"
                                                                        type="number" name="profit_margin[]"
                                                                        class="tbl-input profit_margin" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->net_profit }}"
                                                                        type="number" name="net_profit[]"
                                                                        class="tbl-input net_profit" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount }}"
                                                                        type="number" name="discount[]"
                                                                        class="tbl-input discount"></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount_selling_price }}"
                                                                        type="number" name="discount_selling_price[]"
                                                                        class="tbl-input discount_selling_price" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount_vat_price }}"
                                                                        type="number" name="selling_vat_dedact_price[]"
                                                                        class="tbl-input selling_vat_dedact_price" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount_vat_value }}"
                                                                        type="number" name="discount_vat_value[]"
                                                                        class="tbl-input discount_vat_value" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount_profit_margin }}"
                                                                        type="number" name="discount_profit_margin[]"
                                                                        class="tbl-input discount_profit_margin" readonly></td>
                                        <td class="px-2 py-1.5"><input value="{{ $ch_price->discount_net_profit }}"
                                                                        type="number" name="discount_net_profit[]"
                                                                        class="tbl-input discount_net_profit" readonly></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @else
                            <tr>
                                <td colspan="22"
                                    class="px-4 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
                                    No results found.
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex justify-end">
                    <button type="submit"
                        class="submit-btn inline-flex items-center gap-2 px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
@endsection
