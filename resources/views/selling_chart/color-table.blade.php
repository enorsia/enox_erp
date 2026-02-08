<table class="table table-bordered create_selling_chart_tbl">
    <thead>
        <tr>
            <th style="width:23px">Delete</th>
            <th style="width:80px">Color</th>

            @if ($department_id == 1928 || $department_id == 1929)
                {{-- <th class="size-th" style="width:40px;">Size (Age)</th> --}}
                <th class="size-th" style="width:40px;">Range</th>
            @endif

            <th style="width:30px">PO Order Qty</th>
            <th style="width:30px">Price $ (FOB)</th>
            <th style="width:30px">Unit Price (£)</th>
            {{-- <th scope="col">Shipping Cost </th>
            <th scope="col">Confirm Selling Price</th>
            <th scope="col">20% Selling VAT</th>
            <th scope="col">Vat Value £</th>
            <th scope="col">Profit Margin %</th>
            <th scope="col">Net Profit </th>
            <th scope="col">Discount %</th>
            <th scope="col">Discount Selling Price</th>
            <th scope="col">20% Selling Vat Dedact Price</th>
            <th scope="col">Vat Value £</th>
            <th scope="col">Profit Margin %</th>
            <th scope="col">Net Profit </th> --}}
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm delete-row">
                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken" class="fs-18 delete-icon"></iconify-icon>
                </button>
            </td>
            <td>
                <div class="position-relative">
                    <input type="text" name="color[]" class="color">
                    <input type="hidden" name="color_id[]" class="x_color_id">
                    <input type="hidden" name="color_name[]" class="x_color_name">
                    <input style="position: absolute; left: 0; visibility: hidden;" type="text" name="color_code[]"
                        class="x_color_code ctmr">
                    <div class="color-box"></div>
                </div>
            </td>

            @if ($department_id == 1928 || $department_id == 1929)
                <td class="size-field">
                    <div class="position-relative new_search">
                        <div class="new_select_field new_same_item d-flex flex-wrap">
                            <select name="range_id[]" class="ctmr" style="height: 27px;">
                                <option value="">Select range</option>
                                @foreach ($ranges as $range)
                                    <option value="{{ $range->id }}"
                                        {{ $range->id == old('range_id') ? 'selected' : '' }}>
                                        {{ $range->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </td>
            @endif

            <td class="order-qt">
                <input type="number" name="po_order_qty[]" class="x_po_order_qty ctmr">
            </td>
            <td>
                <input type="number" name="price_fob[]" class="x_price_fob ctmr">
            </td>
            <td>
                <input type="number" name="unit_price[]" class="x_unit_price" readonly>
            </td>
            {{-- <td><input type="number" name="shipping_cost[]" class="shipping_cost"></td>
            <td><input type="number" name="confirm_selling_price[]" class="confirm_selling_price"></td>
            <td><input type="number" name="seling_vat[]" class="seling_vat" readonly></td>
            <td><input type="number" name="seling_vat_value[]" class="seling_vat_value" readonly></td>
            <td><input type="number" name="profit_margin[]" class="profit_margin" readonly></td>
            <td><input type="number" name="net_profit[]" class="net_profit" readonly></td>
            <td><input type="number" name="discount[]" class="discount"></td>
            <td><input type="number" name="discount_selling_price[]" class="discount_selling_price" readonly></td>
            <td><input type="number" name="selling_vat_dedact_price[]" class="selling_vat_dedact_price" readonly></td>
            <td><input type="number" name="discount_vat_value[]" class="discount_vat_value" readonly></td>
            <td><input type="number" name="discount_profit_margin[]" class="discount_profit_margin" readonly></td>
            <td><input type="number" name="discount_net_profit[]" class="discount_net_profit" readonly></td> --}}
        </tr>
    </tbody>
</table>
