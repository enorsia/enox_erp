@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Expense Create',
            'icon' => '',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Selling Chart Expense' => route('admin.selling_chart.expense.index'),
                'Create' => '',
            ],
        ])
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form class="validate-form" action="{{ route('admin.selling_chart.expense.store') }}" method="POST">
                        @csrf
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="year" class="col-12 col-md-4 col-lg-3">Year <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <select name="year" id="year"
                                    class="form-select @error('year') is-invalid @enderror" data-choices required>
                                    <option value="">Select Year</option>
                                    @for ($i = 2020; $i <= 2030; $i++)
                                        <option value="{{ $i }}" {{ old('year') == $i ? 'selected' : '' }}>
                                            {{ $i }}</option>
                                    @endfor
                                </select>

                                @error('year')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="conversion_rate" class="col-12 col-md-4 col-lg-3">Conversion Rate <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="conversion_rate" id="conversion_rate" class="form-control @error('conversion_rate') is-invalid @enderror"
                                    value="{{ old('conversion_rate') }}" required>

                                @error('conversion_rate')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="commercial_expense" class="col-12 col-md-4 col-lg-3">Commercial Expense <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="commercial_expense" id="commercial_expense"
                                    class="form-control @error('commercial_expense') is-invalid @enderror"
                                    value="{{ old('commercial_expense') }}" required>

                                @error('commercial_expense')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="enorsia_expense_bd" class="col-12 col-md-4 col-lg-3">Enorsia Expense Bd <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="enorsia_expense_bd" id="enorsia_expense_bd" class="form-control @error('enorsia_expense_bd') is-invalid @enderror"
                                    value="{{ old('enorsia_expense_bd') }}" required>

                                @error('enorsia_expense_bd')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="enorsia_expense_uk" class="col-12 col-md-4 col-lg-3">Enorsia Expense Uk <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="enorsia_expense_uk" id="enorsia_expense_uk" class="form-control @error('enorsia_expense_uk') is-invalid @enderror"
                                    value="{{ old('enorsia_expense_uk') }}" required>

                                @error('enorsia_expense_uk')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="shipping_cost" class="col-12 col-md-4 col-lg-3">Shipping Cost</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="shipping_cost" id="shipping_cost" class="form-control @error('shipping_cost') is-invalid @enderror">
                            </div>
                        </div>

                        <div class="position-relative form-group mb-2 row">
                            <label class="col-12 col-md-4 col-lg-3">Status</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="custom-control custom-switch" style="padding: 0px;">
                                    <input type="checkbox" class="custom-control-input" name="status" id="status"
                                        checked>
                                    <label class="custom-control-label" for="status"></label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 validate-btn"><i
                                    class="bi bi-save ms-0"></i> Save </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    {{-- @include('backend.partials.validation-script') --}}
    @include('selling_chart.expense.script')
@endpush
