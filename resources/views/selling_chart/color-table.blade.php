<table class="w-full text-[12px] border-collapse create_selling_chart_tbl">
    <thead class="bg-slate-50 dark:bg-slate-700/50">
        <tr>
            <th class="tbl-th" style="width:36px">Del</th>
            <th class="tbl-th">Color</th>

            @if ($department_id == 1928 || $department_id == 1929)
                <th class="tbl-th size-th" style="width:110px">Range</th>
            @endif

            <th class="tbl-th" style="width:90px">PO Qty</th>
            <th class="tbl-th" style="width:90px">FOB ($)</th>
            <th class="tbl-th" style="width:90px">Unit (£)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="border-b border-slate-100 dark:border-slate-700/60">
            <td class="px-2 py-1.5 text-center">
                <button type="button"
                    class="w-7 h-7 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-red-500 hover:bg-red-500 hover:text-white transition-colors flex items-center justify-center delete-row">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </td>
            <td class="px-2 py-1.5">
                <div class="relative">
                    <input type="text" name="color[]" class="tbl-input color" placeholder="Search color…">
                    <input type="hidden" name="color_id[]" class="x_color_id">
                    <input type="hidden" name="color_name[]" class="x_color_name">
                    <input type="text" name="color_code[]" class="x_color_code ctmr"
                        style="position:absolute;left:0;visibility:hidden;">
                    <div class="color-box absolute left-0 top-full mt-1 w-56 z-50"></div>
                </div>
            </td>

            @if ($department_id == 1928 || $department_id == 1929)
                <td class="px-2 py-1.5 size-field">
                    <select name="range_id[]" class="tbl-input ctmr">
                        <option value="">Select range</option>
                        @foreach ($ranges as $range)
                            <option value="{{ $range->id }}"
                                {{ $range->id == old('range_id') ? 'selected' : '' }}>
                                {{ $range->name }}
                            </option>
                        @endforeach
                    </select>
                </td>
            @endif

            <td class="px-2 py-1.5 order-qt">
                <input type="number" name="po_order_qty[]" class="tbl-input x_po_order_qty ctmr" placeholder="0">
            </td>
            <td class="px-2 py-1.5">
                <input type="number" name="price_fob[]" class="tbl-input x_price_fob ctmr" placeholder="0.00">
            </td>
            <td class="px-2 py-1.5">
                <input type="number" name="unit_price[]" class="tbl-input x_unit_price" readonly placeholder="0.00">
            </td>
        </tr>
    </tbody>
</table>
