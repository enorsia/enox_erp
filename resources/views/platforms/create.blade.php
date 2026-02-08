@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Platforms Create',
            'icon' => '',
            'sub_title' => [
                'Platforms ' => route('admin.platforms.index'),
                'Create' => '',
            ],
        ])
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form class="validate-form" action="{{ route('admin.platforms.store') }}" method="POST">
                        @csrf

                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="platform_name" class="col-12 col-md-4 col-lg-3">Platform name <sup
                                    class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="platform_name" id="platform_name"
                                    class="form-control @error('platform_name') is-invalid @enderror"
                                    value="{{ old('platform_name') }}" required>

                                @error('platform_name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="code" class="col-12 col-md-4 col-lg-3">Platform code <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="code" id="code"
                                    class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}"
                                    required>

                                @error('code')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="shipping_charge" class="col-12 col-md-4 col-lg-3">Shipping charge </label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="number" name="shipping_charge" id="shipping_charge"
                                    class="form-control @error('shipping_charge') is-invalid @enderror"
                                    value="{{ old('shipping_charge') }}">

                                @error('shipping_charge')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="min_profit" class="col-12 col-md-4 col-lg-3">Min profit <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="number" name="min_profit" id="min_profit"
                                    class="form-control @error('min_profit') is-invalid @enderror"
                                    value="{{ old('min_profit') }}" required>

                                @error('min_profit')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="commission" class="col-12 col-md-4 col-lg-3">Commission <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="number" name="commission" id="commission"
                                    class="form-control @error('commission') is-invalid @enderror"
                                    value="{{ old('commission') }}" required>

                                @error('commission')
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
