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
                'Create' => route('admin.selling_chart.create'),
            ]
        ])
        <a href="{{ route('admin.selling_chart.index') }}" class="btn tlt-btn">
            <i class="fa fa-chevron-left mr-1"></i>
            Back
        </a>
    </div>
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card-dark main-card mb-3 card pb-0">
                <div class="card-body p-0">
                    <form action="{{ route('admin.selling_chart.store') }}" method="POST" class="selling_chart_form"
                        id="selling_chart" enctype="multipart/form-data">
                        @csrf
                        <div class="position-relative form-group new_search row">
                            <label for="department_select" class="col-12 col-md-4 col-lg-3">Department <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="department_select" name="department_id" class="select2 form-control">
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option {{ old('department_id') == $department->id ? 'selected' : '' }}
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
                                    <select id="product_category" name="category_id" class="select2 form-control">
                                        <option value="">Select Category</option>
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
                                    <select id="product_mini_category" name="mini_category" class="select2 form-control"
                                        required>
                                        <option value="">Select Mini Category</option>
                                        @foreach ($selling_chart_types as $selling_chart_type)
                                            <option {{ old('mini_category') == $selling_chart_type->id ? 'selected' : '' }}
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
                                    <select id="season_select" name="season_id" class="select2 form-control">
                                        <option value="">Select Season</option>
                                        @foreach ($seasons as $season)
                                            @php
                                                // $season_name_year = intval(substr($season->name, -2));
                                                // $last_digit_season_year = $season_name_year % 10;
                                                // $expense = \App\Models\SellingChartExpense::where('status', 1)
                                                //     ->whereRaw('YEAR(year) % 10 = ?', [$last_digit_season_year])
                                                //     ->latest()
                                                //     ->first();
                                                $season_name_year = preg_replace('/\D/', '', $season->name);
                                                $digit_count = strlen($season_name_year);

                                                $current_year = date('Y');
                                                $current_century = substr($current_year, 0, -$digit_count);

                                                $season_year = $current_century . trim($season_name_year);
                                                $expense = $expenses->where('year', (int) $season_year)->first();
                                            @endphp

                                            <option {{ old('season_id') == $season->id ? 'selected' : '' }}
                                                value="{{ $season->id }}"
                                                data-conversion-rate="{{ $expense->conversion_rate ?? 0 }}"
                                                data-commercial-expense="{{ $expense->commercial_expense ?? 0 }}"
                                                data-enorsia-bd-expense="{{ $expense->enorsia_expense_bd ?? 0 }}"
                                                data-enorsia-uk-expense="{{ $expense->enorsia_expense_uk ?? 0 }}"
                                                data-shipping-cost="{{ $expense->shipping_cost ?? 0 }}">{{ $season->name }}</option>
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
                                    <select id="Season_Phase" name="season_phase_id" class="select2 form-control">
                                        <option value="">Select Season Phase</option>
                                        @foreach ($seasons_phases as $seasons_phase)
                                            <option {{ old('season_phase_id') == $seasons_phase->id ? 'selected' : '' }}
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
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="Repeat_Order" name="order_type_id" class="select2 form-control">
                                        <option value="">Select Initial/ Repeat Order</option>
                                        @foreach ($initialRepeats as $initialRepeat)
                                            <option value="{{ $initialRepeat->id }}"
                                                {{ $initialRepeat->id == 2007 ? 'selected' : '' }}>
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
                                    value="{{ old('product_launch_month') }}">
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
                                    value="{{ old('product_code') }}">
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
                                    value="{{ old('design_no') }}">
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
                                    value="{{ old('product_description') }}">
                                @error('product_description')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label for="" class="col-12 col-md-4 col-lg-3">Fabrication <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="fabrication" name="fabrication" class="select2 form-control">
                                        <option value="">Select a fabrication</option>
                                        @foreach ($fabrics as $fabric)
                                            <option {{ old('fabrication') == $fabric->id ? 'selected' : '' }}
                                                value="{{ $fabric->id }}">{{ $fabric->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- <input type="text" name="fabrication" id="fabrication"
                                    placeholder="Enter Fabrication"
                                    class="form-control @error('fabrication') is-invalid @enderror"
                                    value="{{ old('fabrication') }}"> --}}
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
                                <img id="imagePreview" class="image-preview" alt="Image Preview">
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
                                <img id="imagePreview" class="image-preview" alt="Image Preview">
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
                                    <div class="new_table table-responsive color-table mb-0">

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-row justify-content-between position-relative" style="top: -55px;">
                            <button type="button"
                                class="btn btn-lg btn-info fs-6 px-4 add_more_btn btn-invisible invisible"><i
                                    class="bi bi-plus-lg"></i> Add More </button>
                            <button type="submit"
                                class="btn btn-lg btn-primary fs-6 px-4 submit-btn btn-invisible invisible"><i
                                    class="bi bi-save ms-0"></i> Save </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    @include('backend.partials.validation-script')
    @include('backend.selling_chart.script')
@endpush
