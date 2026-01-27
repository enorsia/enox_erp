@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Selling Chart Expense',
            'icon' => '',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Selling Chart Expense' => route('admin.selling_chart.expense.index'),
                'Create' => '',
            ],
        ])

        <div>
            <a href="{{ session('backUrl', url()->previous()) }}" class="btn btn-outline-secondary">
                &lt; Back
            </a>
        </div>
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form class="validate-form" action="{{ route('admin.platforms.store') }}" method="POST">
                        @csrf

                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="platform_name" class="col-12 col-md-4 col-lg-3">Platform Name <sup
                                    class="text-warning">
                                    (unique & required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="platform_name" id="platform_name" class="form-control @error('platform_name') is-invalid @enderror"
                                    value="{{ old('platform_name') }}" required>

                                @error('platform_name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="shipping_charge" class="col-12 col-md-4 col-lg-3">Shipping Charge </label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="number" name="shipping_charge" id="shipping_charge" class="form-control @error('shipping_charge') is-invalid @enderror"
                                    value="{{ old('shipping_charge') }}">

                                @error('shipping_charge')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="note" class="col-12 col-md-4 col-lg-3">Note</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <textarea name="note" id="note" class="form-control @error('note') is-invalid @enderror"
                                    value="{{ old('note') }}" rows="3"></textarea>

                                @error('note')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
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
