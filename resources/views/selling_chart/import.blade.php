@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Chart Import',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Manage Selling Chart' => route('admin.selling_chart.index'),
                'Import Sales Chart' => route('admin.selling_chart.upload.sheet'),
            ],
        ])
        {{-- <div>
            <a href="{{ route('admin.selling_chart.index') }}" class="btn btn-outline-secondary">
                <i class="fa fa-chevron-left mr-1"></i>
                &lt; Back
            </a>
        </div> --}}
    </div>
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card-dark main-card mb-3 card p-0">
                <div class="card-body">

                    @if (session('import_msg'))
                        <div class="alert alert-danger fw-bold text-center" role="alert">
                            <div class="mb-1">
                                {{ session('import_msg') }}
                            </div>
                            <div>
                                {{ session('in_value') }}
                            </div>
                            {{-- <small>Should be check (Department, Season, Season Phase, Product Category, Mini Category, Size
                                Range)
                                columns.
                            </small> --}}
                        </div>
                    @endif

                    <form class="mb-4" action="{{ route('admin.selling_chart.import') }}" method="POST"
                        enctype="multipart/form-data" id="import_form">
                        @csrf
                        <div class="position-relative form-group new_search row mb-3">
                            <label for="name" class="col-12 col-md-4 col-lg-3">Excel Sheet <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input class="form-control" type="file" name="sheet" required
                                    style="line-height: 31px;">
                            </div>
                        </div>
                        @error('sheet')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                        <div class="text-end">
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn"><i
                                    class="bi bi-save ms-0"></i> Import </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    @include('selling_chart.script')
@endpush
