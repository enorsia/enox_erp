@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Febrication Create',
            'icon' => '',
            'sub_title' => [
                'Manage Selling Chart ' => route('admin.selling_chart.index'),
                'Febrication' => route('admin.selling_chart.fabrication.index'),
                'Create' => '',
            ],
        ])
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form class="validate-form" action="{{ route('admin.selling_chart.fabrication.store') }}" method="POST">
                        @csrf

                        <div class="position-relative form-group mb-2 new_search row">
                            <label for="name" class="col-12 col-md-4 col-lg-3">Febrication Name<sup
                                    class="text-warning">
                                    (unique & required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" required>

                                @error('name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
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

