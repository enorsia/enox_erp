<style>
    .color-box {
        position: absolute;
        top: 27px;
        left: 0;
        width: 100%;
        max-height: 120px;
        z-index: 9;
        overflow-y: auto;
    }

    .color-box .list-group-item {
        background: #374151;
        color: #fff;
        padding: 5px 10px;
        border-bottom: 2px solid #323436;
        cursor: pointer;
        font-size: 12px;
    }

    .color-box .list-group-item:hover {
        background: #323436;
    }

    #selling_chart_table .table-responsive {
        padding-bottom: 110px;
    }

    @media (min-width: 768px) {
        #selling_chart_table .create_selling_chart_tbl {
            width: 100% !important;
        }
    }
</style>
<table class="table create_selling_chart_tbl mb-0">
    <thead>
        <tr>
            <th style="width:23px">Delete</th>
            <th style="width:80px">Color</th>
            @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                {{-- <th class="size-th" style="width:40px;">Size (Age)</th> --}}
                <th class="size-th" style="width:40px;">Range</th>
            @endif
            <th style="width:30px">PO Order Qty</th>
            <th style="width:30px">Price $ (FOB)</th>
            <th style="width:30px">Unit Price (£)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($chartInfo->sellingChartPrices as $ch_price)
            <tr>
                <td class="text-center">
                    <button type="button" class=" delete-row" style="background: none; border: none;">@include('backend.component-list.delete-icon', ['title' => "Delete"])</button>
                    <input type="hidden" name="price_id[]" value="{{ $ch_price->id }}">
                </td>
                <td>
                    <div class="position-relative">
                        <input type="text" name="color[]" class="color"
                            value="{{ $ch_price->color_name }} ({{ $ch_price->color_code }})">
                        <input type="hidden" name="color_id[]" class="x_color_id" value="{{ $ch_price->color_id }}">
                        <input type="hidden" name="color_name[]" class="x_color_name"
                            value="{{ $ch_price->color_name }}">
                        <input style="position: absolute; left: 0; visibility: hidden;" type="text"
                            name="color_code[]" class="x_color_code ctmr" value="{{ $ch_price->color_code }}">
                        <div class="color-box"></div>
                    </div>
                </td>
                @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929)
                    {{-- <td class="size-field">
                        <div class="position-relative new_search">
                            <div class="new_select_field new_same_item d-flex flex-wrap">
                                <select name="size_id[]" class="js-states form-control ctmr">
                                    <option value="">Select size</option>
                                    @foreach ($sizes as $size)
                                        <option {{ $size?->lookupName?->id == $ch_price->size_id ? 'selected' : '' }}
                                            value="{{ $size?->lookupName?->id }}">
                                            {{ $size?->lookupName?->name }}
                                            {{ \Illuminate\Support\Str::productSize($size?->lookupName?->name, $chartInfo->department_id, 'uk') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </td> --}}
                    <td class="size-field">
                        <div class="position-relative new_search">
                            <div class="new_select_field new_same_item d-flex flex-wrap">
                                <select name="range_id[]" class="js-states form-control ctmr">
                                    <option value="">Select range</option>
                                    @foreach ($ranges as $range)
                                        <option {{ $range->id == $ch_price->range_id ? 'selected' : '' }}
                                            value="{{ $range->id }}"
                                            {{ $range->id == old('range_id') ? 'selected' : '' }}>
                                            {{ $range->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </td>
                @endif
                <td>
                    <input type="number" name="po_order_qty[]" class="x_po_order_qty ctmr"
                        value="{{ $ch_price->po_order_qty }}">
                </td>
                <td>
                    <input type="number" name="price_fob[]" class="x_price_fob ctmr"
                        value="{{ $ch_price->price_fob }}" readonly>
                </td>
                <td>
                    <input type="number" name="unit_price[]" class="x_unit_price" readonly
                        value="{{ $ch_price->unit_price }}">
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
