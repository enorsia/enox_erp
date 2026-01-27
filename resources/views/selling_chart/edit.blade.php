@extends('backend.master')

@section('content')
    <div class="top_title">
        @include('backend.partials.breadcrumb', [
            'title' => 'Manage Selling Chart',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Main' => '',
                'Manage Selling Chart ' => '',
                'Manage Selling Chart' => route('admin.selling_chart.index'),
                'Edit' => route('admin.selling_chart.index'),
            ]
        ])
        <a href="{{ session('backUrl', url()->previous()) }}" class="btn tlt-btn">
            <i class="fa fa-chevron-left mr-1"></i>
            Back
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card-dark main-card mb-3 card pb-0">
                <div class="card-body p-0">
                    <form action="{{ route('admin.selling_chart.update', ['id' => $chartInfo->id]) }}" method="POST"
                        class="selling_chart_form" id="selling_chart" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="position-relative form-group new_search row">
                            <label for="department_select" class="col-12 col-md-4 col-lg-3">Department <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="department_select" name="department_id" class="js-states form-control"
                                        disabled>
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option {{ $chartInfo->department_id == $department->id ? 'selected' : '' }}
                                                value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('department_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="product_category" class="col-12 col-md-4 col-lg-3">Product Category<sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="product_category" name="category_id" class="js-states form-control"
                                        disabled>
                                        <option value="">Select Category</option>
                                        @foreach ($selling_chart_cats as $selling_chart_cat)
                                            <option
                                                {{ $chartInfo->category_id == $selling_chart_cat->id ? 'selected' : '' }}
                                                value="{{ $selling_chart_cat->id }}">{{ $selling_chart_cat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('category_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="product_mini_category" class="col-12 col-md-4 col-lg-3">Product Mini Category<sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="product_mini_category" name="mini_category" class="js-states form-control"
                                        required>
                                        <option value="">Select Mini Category</option>
                                        @foreach ($selling_chart_types as $selling_chart_type)
                                            <option
                                                {{ $chartInfo->mini_category == $selling_chart_type->id ? 'selected' : '' }}
                                                value="{{ $selling_chart_type->id }}">{{ $selling_chart_type->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('mini_category')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="season_select" class="col-12 col-md-4 col-lg-3">Season <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="season_select" name="season_id" class="js-states form-control">
                                        <option value="">Select Season</option>
                                        @foreach ($seasons as $season)
                                            @php
                                                $season_name_year = intval(substr($season->name, -2));
                                                $last_digit_season_year = $season_name_year % 10;
                                                $expense = \App\Models\SellingChartExpense::where('status', 1)
                                                    ->whereRaw('YEAR(year) % 10 = ?', [$last_digit_season_year])
                                                    ->latest()
                                                    ->first();
                                            @endphp
                                            <option {{ $chartInfo->season_id == $season->id ? 'selected' : '' }}
                                                value="{{ $season->id }}"
                                                data-conversion-rate="{{ $expense->conversion_rate ?? 0 }}"
                                                data-commercial-expense="{{ $expense->commercial_expense ?? 0 }}"
                                                data-enorsia-bd-expense="{{ $expense->enorsia_expense_bd ?? 0 }}"
                                                data-enorsia-uk-expense="{{ $expense->enorsia_expense_uk ?? 0 }}"
                                                data-shipping-cost="{{ $expense->shipping_cost ?? 0 }}">
                                                {{ $season->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('season_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="Season_Phase" class="col-12 col-md-4 col-lg-3">Season Phase <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="Season_Phase" name="season_phase_id" class="js-states form-control">
                                        <option value="">Select Season Phase</option>
                                        @foreach ($seasons_phases as $seasons_phase)
                                            <option {{ $chartInfo->phase_id == $seasons_phase->id ? 'selected' : '' }}
                                                value="{{ $seasons_phase->id }}">{{ $seasons_phase->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('season_phase_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="Repeat_Order" class="col-12 col-md-4 col-lg-3">Initial/ Repeat Order <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                {{-- <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="Repeat_Order" name="order_type_id" class="js-states form-control">
                                        <option value="">Select Order Type</option>
                                        <option {{ $chartInfo->initial_repeated_status == 1 ? 'selected' : '' }}
                                            value="1">Initial</option>
                                        <option {{ $chartInfo->initial_repeated_status == 2 ? 'selected' : '' }}
                                            value="2">Repeated</option>
                                    </select>
                                </div> --}}
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="Repeat_Order" name="order_type_id" class="select2 form-control">
                                        <option value="">Select Initial/ Repeat Order</option>
                                        @foreach ($initialRepeats as $initialRepeat)
                                            <option value="{{ $initialRepeat->id }}"
                                                {{ $initialRepeat->id == $chartInfo->initial_repeated_id ? 'selected' : '' }}>
                                                {{ $initialRepeat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @error('order_type_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="product_launch_month" class="col-12 col-md-4 col-lg-3">Product Launch Month <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="product_launch_month" id="product_launch_month"
                                    placeholder="Enter Product Launch Month"
                                    class="form-control @error('product_launch_month') is-invalid @enderror"
                                    value="{{ $chartInfo->product_launch_month }}">
                                @error('product_launch_month')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="product_code" class="col-12 col-md-4 col-lg-3">Product Code <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="product_code" id="product_code"
                                    placeholder="Enter product code"
                                    class="form-control @error('product_code') is-invalid @enderror"
                                    value="{{ $chartInfo->product_code }}">
                                @error('product_code')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="design_no" class="col-12 col-md-4 col-lg-3">Design No <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="design_no" id="design_no" placeholder="Enter design no"
                                    class="form-control @error('design_no') is-invalid @enderror"
                                    value="{{ $chartInfo->design_no }}">
                                @error('design_no')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="product_design" class="col-12 col-md-4 col-lg-3">Product Description <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="product_description" id="product_design"
                                    placeholder="Enter Product Description"
                                    class="form-control @error('product_description') is-invalid @enderror"
                                    value="{{ $chartInfo->product_description }}">
                                @error('product_description')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="fabrication" class="col-12 col-md-4 col-lg-3">Fabrication <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="fabrication" name="fabrication" class="select2 form-control">
                                        <option value="">Select a fabrication</option>
                                        @foreach ($fabrics as $fabric)
                                            <option {{ $chartInfo->fabrication_id == $fabric->id ? 'selected' : '' }}
                                                value="{{ $fabric->id }}">{{ $fabric->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- <input type="text" name="fabrication" id="fabrication"
                                    placeholder="Enter Fabrication"
                                    class="form-control @error('fabrication') is-invalid @enderror"
                                    value="{{ $chartInfo->fabrication }}"> --}}
                                @error('fabrication')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row" id="input_with_preview">
                            <label for="name" class="col-12 col-md-4 col-lg-3">Inspiration Image</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="file" name="image"
                                    class="form-control image-input @error('image') is-invalid @enderror" id="imageInput"
                                    accept="image/*">
                                <img id="imagePreview" class="image-preview" @if ($chartInfo->inspiration_image) style="display: block;" @endif alt="Image Preview"
                                    src="{{ $chartInfo->inspiration_image ? cloudflareImage($chartInfo->inspiration_image, 150) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 150) }}">
                                @error('image')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row" id="input_with_preview">
                            <label for="name" class="col-12 col-md-4 col-lg-3">Design Image</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="file" name="design_image"
                                    class="form-control image-input @error('image') is-invalid @enderror" id="imageInput"
                                    accept="image/*">
                                <img id="imagePreview" class="image-preview" @if ($chartInfo->design_image) style="display: block;" @endif alt="Image Preview"
                                    src="{{ $chartInfo->design_image ? cloudflareImage($chartInfo->design_image, 150) : cloudflareImage('099de045-63a0-407d-75ca-8e22f95b8700', 150) }}">
                                @error('design_image')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="new_search row" id="selling_chart_table">
                            <div class="col-12">
                                <div class="selling_table_body">
                                    <input type="hidden" id="ch_in_id" value="{{ $chartInfo->id }}" />
                                    <div class="new_table table-responsive color-table mb-0">
                                        @include('backend.selling_chart.edit-color-table')
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-row justify-content-between position-relative" style="top: -55px;">
                            <button type="button" class="btn btn-lg btn-info fs-6 px-4 add_more_btn"><i
                                    class="bi bi-plus-lg"></i> Add More </button>
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn"><i
                                    class="bi bi-save ms-0"></i> Save </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
    </form>

    {{-- <div class="row justify-content-center">
        <div class="col-12">
            <div class="card-dark main-card mb-3 card" id="selling_chart_view_table_edit">
                <div class="card-body">
                    <form action="#" method="POST" class="selling_chart_form" id="selling_chart">
                        <div class=" new_search row" id="selling_chart_table">
                            <div class="col-12">
                                <div class="selling_table_body">
                                    <div class="new_table table-responsive">
                                        <table class="table selling_chart_edit_table">
                                            <thead>
                                                <tr>
                                                    <th >Check</th>
                                                    <th >Color Code</th>
                                                    <th scope="col">Color Name</th>
                                                    <th scope="col">PO Order Qty</th>
                                                    <th scope="col">Price $ (FOB)</th>
                                                    <th scope="col">Unit Price</th>
                                                    <th scope="col">Shipping Cost </th>
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
                                                    <th scope="col">Net Profit </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        <input style="height: 15px !important; width: 15px !important;"
                                                            type="checkbox" id="vehicle1" name="vehicle1"
                                                            value="Bike">
                                                    </td>
                                                    <td><input type="text" name="color_code[]" class="color_code"></td>
                                                    <td><input type="text" name="color_name[]" class="color_name"></td>
                                                    <td><input type="number" name="po_order_qty[]" class="po_order_qty"></td>
                                                    <td><input type="number" name="price_fob[]" class="price_fob"></td>
                                                    <td><input type="number" name="unit_price[]" class="unit_price" readonly></td>
                                                    <td><input type="number" name="shipping_cost[]" class="shipping_cost"></td>
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
                                                    <td><input type="number" name="discount_net_profit[]" class="discount_net_profit" readonly></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class=" text-end">
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn"><i class="bi bi-save ms-0"></i> Save </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div> --}}
@endsection
@push('js')
    @include('backend.partials.validation-script')
    @include('backend.selling_chart.script')
@endpush
