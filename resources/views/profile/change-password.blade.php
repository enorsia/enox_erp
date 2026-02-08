@extends('master.app')

@push('css')
@endpush

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Password Change',
            'icon' => 'bi bi-people',
            'sub_title' => [
                'Access Controller' => '',
            ],
        ])
    </div>

    <form class="validate-form" method="POST" action="{{ route('admin.password.update.post') }}" enctype="multipart/form-data">
        @csrf
        <div class="card p-0">
            <div class="card-body">
                <div class="position-relative form-group mb-3 new_search row">
                    <label class="col-12 col-md-4 col-lg-3" for="current_password">Current Password <sup class="text-warning">(required)</sup></label>
                    <div class="col-12 col-md-8 col-lg-9">
                        <input id="current_password" type="password"
                            class="form-control @error('current_password') is-invalid @enderror" name="current_password"
                            required>

                        @error('current_password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                <div class="position-relative form-group mb-3 new_search row">
                    <label class="col-12 col-md-4 col-lg-3">New Password <sup class="text-warning">(required)</sup></label>
                    <div class="col-12 col-md-8 col-lg-9">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                            name="password" required>
                        @error('password')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                <div class="position-relative form-group mb-3 new_search row">
                    <label class="col-12 col-md-4 col-lg-3">Confirm password <sup class="text-warning">(required)</sup></label>
                    <div class="col-12 col-md-8 col-lg-9">
                        <input id="confirm_password" type="password"
                            class="form-control @error('password') is-invalid @enderror" name="password_confirmation"
                            required>
                        @error('password')
                            <span class="text-danger" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary validate-btn">
                        <i class="bi bi-save ms-0"></i> Update Password
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('js')
    @include('users.script')
@endpush
