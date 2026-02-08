@extends('master.app')
@push('css')
    <style>
        .warning-text .swal2-title {
            font-size: 1.5em !important;
            color: #bb2727 !important;
        }

        .error {
            margin-top: 5px;
            font-size: 12px;
        }

        .selling_chart_form input[type="checkbox"]:checked::after {
            content: "";
        }

        #selling_chart_table .new_table table tbody tr td input {
            width: 80px !important;
            text-align: center;
        }

        #selling_chart_table .new_table table tbody tr td input,
        #selling_chart_table .new_table table tbody tr td select {
            width: 100px !important;
        }

        .sticky-table {
            max-height: 80vh;
            overflow: auto;
        }

        .sticky-table thead th{
            position: sticky;
            top: -1px;
            z-index: 10;
            background: #fff;
        }
       html[data-bs-theme="dark"]  .sticky-table thead th {
            background: #282F36;
        }
    </style>
@endpush
@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Chart Bult Edit',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Manage Selling Chart' => route('admin.selling_chart.index'),
                'Bult edit' => '',
            ],
        ])
        {{-- <a href="{{ session('backUrl', url()->previous()) }}" class="btn tlt-btn">
            <i class="fa fa-chevron-left mr-1"></i>
            Back
        </a> --}}
    </div>
    <div class="mb-3 card p-0" id="selling_chart_view_table_edit">
        <div class="card-body">
            <form action="{{ route('admin.selling_chart.bulk.update') }}" method="POST" class="selling_chart_form"
                id="bulk_form">
                @csrf
                <div class="new_search" id="selling_chart_table">
                    <div class="selling_table_body">
                        <div class="new_table table-responsive sticky-table">
                            <table class="table table-bordered selling_chart_edit_table"
                                style="width: max-contents !importtant;">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">Check</th>
                                        <th style="width: 180px;">Design No</th>
                                        <th style="width: 100px;">Design Image</th>
                                        <th style="width: 100px;">Inspiration Image</th>
                                        <th style="width: 200px;">Color Code</th>
                                        <th style="width: 200px;" scope="col">Color Name</th>

                                        @if (
                                            !$chartInfos->isEmpty() &&
                                                ($chartInfos[0]['department_id'] == 1928 ||
                                                    $chartInfos[0]['department_id'] == 1929 ||
                                                    !request('department_id')))
                                            {{-- <th class="text-nowrap" style="width: 160px !important;">
                                                            Size (Age)</th> --}}
                                            <th class="text-nowrap" style="width: 160px !important;">Range</th>
                                        @endif

                                        <th style="width: 80px;" scope="col">PO Order Qty</th>
                                        <th style="width: 80px;" scope="col">Price $ (FOB)</th>
                                        <th style="width: 80px;" scope="col">Unit Price</th>
                                        <th style="width: 80px;" scope="col">Shipping Cost </th>
                                        <th style="width: 80px;" scope="col">Confirm Selling Price</th>
                                        <th style="width: 80px;" scope="col">20% Selling VAT</th>
                                        <th style="width: 80px;" scope="col">Vat Value £</th>
                                        <th style="width: 80px;" scope="col">Profit Margin %</th>
                                        <th style="width: 80px;" scope="col">Net Profit </th>
                                        <th style="width: 80px;" scope="col">Discount %</th>
                                        <th style="width: 80px;" scope="col">Discount Selling Price</th>
                                        <th style="width: 80px;" scope="col">20% Selling Vat Dedact Price
                                        </th>
                                        <th style="width: 80px;" scope="col">Vat Value £</th>
                                        <th style="width: 80px;" scope="col">Profit Margin %</th>
                                        <th style="width: 80px;" scope="col">Net Profit </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (!$chartInfos->isEmpty())
                                        @foreach ($chartInfos as $chartInfo)
                                            @foreach ($chartInfo->sellingChartPrices as $ch_price)
                                                <tr>
                                                    <td class="text-center">
                                                        <input style="height: 15px !important; width: 15px !important;"
                                                            type="checkbox" id="price_id" name="price_id[]"
                                                            value="{{ $ch_price->id }}">
                                                        <input type="hidden" name="price_id_all[]"
                                                            value="{{ $ch_price->id }}">

                                                        @php
                                                            $season_name_year = preg_replace(
                                                                '/\D/',
                                                                '',
                                                                $chartInfo->season_name,
                                                            );
                                                            // $season_name_year = intval(substr($chartInfo->season_name, -2));
                                                            $digit_count = strlen($season_name_year);

                                                            $current_year = date('Y');
                                                            $current_century = substr($current_year, 0, -$digit_count);

                                                            $season_year = $current_century . trim($season_name_year);
                                                            $expense = $expenses
                                                                ->where('year', (int) $season_year)
                                                                ->first();
                                                        @endphp

                                                        <input class="expense_input" type="hidden" name="expense_input"
                                                            value="{{ $expense?->year }}"
                                                            data-department="{{ request('department_id') }}"
                                                            data-conversion-rate="{{ $expense?->conversion_rate ?? 0 }}"
                                                            data-commercial-expense="{{ $expense?->commercial_expense ?? 0 }}"
                                                            data-enorsia-bd-expense="{{ $expense?->enorsia_expense_bd ?? 0 }}"
                                                            data-enorsia-uk-expense="{{ $expense?->enorsia_expense_uk ?? 0 }}"
                                                            data-shipping-cost="{{ $expense?->shipping_cost ?? 0 }}">

                                                    </td>
                                                    <td>{{ $chartInfo->design_no }}</td>
                                                    <td>
                                                        @if ($chartInfo->design_image)
                                                            <img class="img-fluid"
                                                                src="{{ cloudflareImage($chartInfo->design_image, 50) }}"
                                                                alt="Design Image">
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($chartInfo->inspiration_image)
                                                            <img class="img-fluid"
                                                                src="{{ cloudflareImage($chartInfo->inspiration_image, 50) }}"
                                                                alt="Inspiration Image">
                                                        @endif
                                                    </td>
                                                    <td><input readonly value="{{ $ch_price->color_code }}" type="text"
                                                            name="color_code[]" class="color_code"></td>
                                                    <td><input readonly value="{{ $ch_price->color_name }}" type="text"
                                                            name="color_name[]" class="color_name"></td>
                                                    @if ($chartInfo->department_id == 1928 || $chartInfo->department_id == 1929 || !request('department_id'))
                                                        {{-- <td class="text-nowrap"
                                                                        style="width: 130px !important;">
                                                                        <div class="position-relative new_search">
                                                                            <div
                                                                                class="new_select_field new_same_item d-flex flex-wrap">
                                                                                <select name="size_id[]"
                                                                                    class="js-states form-control ctmr">
                                                                                    <option value="">Select size
                                                                                    </option>
                                                                                    @foreach ($sizes as $size)
                                                                                        <option
                                                                                            {{ $size?->lookupName?->id == $ch_price->size_id ? 'selected' : '' }}
                                                                                            value="{{ $size?->lookupName?->id }}">
                                                                                            {{ $size?->lookupName?->name }}
                                                                                            {{ \Illuminate\Support\Str::productSize($size?->lookupName?->name, $chartInfo->department_id, 'uk') }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </td> --}}
                                                        <td class="text-nowrap" style="width: 130px !important;">
                                                            <div class="position-relative new_search">
                                                                <div
                                                                    class="new_select_field new_same_item d-flex flex-wrap">
                                                                    <select name="range_id[]"
                                                                        class="js-states form-control ctmr">
                                                                        <option value="">Select range
                                                                        </option>
                                                                        @foreach ($ranges as $range)
                                                                            <option
                                                                                {{ $range->id == $ch_price->range_id ? 'selected' : '' }}
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
                                                    <td><input value="{{ $ch_price->po_order_qty }}" type="number"
                                                            name="po_order_qty[]" class="po_order_qty ctmr"></td>
                                                    <td><input value="{{ $ch_price->price_fob }}" type="number"
                                                            name="price_fob[]" class="price_fob ctmr">
                                                    </td>
                                                    <td><input value="{{ $ch_price->unit_price }}" type="number"
                                                            name="unit_price[]" class="unit_price" readonly></td>

                                                    <td><input
                                                            value="{{ $ch_price->product_shipping_cost ? $ch_price->product_shipping_cost : $expense->shipping_cost ?? 0 }}"
                                                            type="number" name="shipping_cost[]" class="shipping_cost">
                                                    </td>
                                                    <td><input value="{{ $ch_price->confirm_selling_price }}"
                                                            type="number" name="confirm_selling_price[]"
                                                            class="confirm_selling_price"></td>
                                                    <td><input value="{{ $ch_price->vat_price }}" type="number"
                                                            name="seling_vat[]" class="seling_vat" readonly></td>
                                                    <td><input value="{{ $ch_price->vat_value }}" type="number"
                                                            name="seling_vat_value[]" class="seling_vat_value" readonly>
                                                    </td>

                                                    <td><input value="{{ $ch_price->profit_margin }}" type="number"
                                                            name="profit_margin[]" class="profit_margin" readonly></td>
                                                    <td><input value="{{ $ch_price->net_profit }}" type="number"
                                                            name="net_profit[]" class="net_profit" readonly></td>
                                                    <td><input value="{{ $ch_price->discount }}" type="number"
                                                            name="discount[]" class="discount">
                                                    </td>
                                                    <td><input value="{{ $ch_price->discount_selling_price }}"
                                                            type="number" name="discount_selling_price[]"
                                                            class="discount_selling_price" readonly></td>
                                                    <td><input value="{{ $ch_price->discount_vat_price }}" type="number"
                                                            name="selling_vat_dedact_price[]"
                                                            class="selling_vat_dedact_price" readonly></td>
                                                    <td><input value="{{ $ch_price->discount_vat_value }}" type="number"
                                                            name="discount_vat_value[]" class="discount_vat_value"
                                                            readonly></td>
                                                    <td><input value="{{ $ch_price->discount_profit_margin }}"
                                                            type="number" name="discount_profit_margin[]"
                                                            class="discount_profit_margin" readonly></td>
                                                    <td><input value="{{ $ch_price->discount_net_profit }}"
                                                            type="number" name="discount_net_profit[]"
                                                            class="discount_net_profit" readonly></td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @else
                                        <tr>
                                            <td class="text-nowrap" colspan="18">
                                                <h5 class="text-danger text-center text-uppercase py-2 mb-0">No
                                                    Result found.
                                                </h5>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="mt-2 text-end">
                    <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn"><i
                            class="bi bi-save ms-0"></i> Save </button>
                </div>
            </form>
        </div>
    </div>
@endsection
@push('js')
    @include('selling_chart.script')
@endpush
