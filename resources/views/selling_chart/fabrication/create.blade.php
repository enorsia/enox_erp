@extends('backend.master')

@section('content')
    <div class="top_title">
        @include('backend.partials.breadcrumb', [
            'title' => 'Selling Chart Fabrication',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Main' => '',
                'Manage Selling Chart ' => '',
                'Selling Chart Fabrication' => route('admin.selling_chart.fabrication.index'),
                'Create' => route('admin.selling_chart.fabrication.create'),
            ]
        ])
        <a href="{{ route('admin.selling_chart.fabrication.index') }}" class="btn tlt-btn">
            <i class="fa fa-chevron-left mr-1"></i>
            Back
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body p-0">
                    <form action="{{ route('admin.selling_chart.fabrication.store') }}" method="POST" id="lookupNameForm">
                        @csrf

                        <div class="position-relative form-group new_search row">
                            <label for="name" class="col-12 col-md-4 col-lg-3">Name <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="name" id="name" placeholder="Enter name"
                                    class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">

                                @error('name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="position-relative form-group row">
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
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn"><i
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
    @include('backend.selling_chart.fabrication.script')
@endpush
