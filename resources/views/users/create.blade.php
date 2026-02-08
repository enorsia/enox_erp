@extends('master.app')

@push('css')
    <style>
        .dropify-message p {
            font-size: 18px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }

        .select2-container .select2-selection--single {
            height: 43px;
        }

        .custom-control-label::before {
            background-color: #374151;
        }

        .dropify-wrapper {
            background-color: #374151;
            border: 1px solid #4f5154;
        }

        .dropify-wrapper .dropify-preview {
            background-color: #374151;
        }

        #avatar-error {
            color: #d1474f;
            margin-top: 92px;
            margin-bottom: 0;
            font-weight: bold;
        }
    </style>
@endpush

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Admin Create',
            'icon' => 'bi bi-people',
            'sub_title' => [
                'Access Controller' => '',
            ],
        ])
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" id="validateForm">
        @csrf
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card p-0">
                    <div class="card-body">
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Name <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="name" type="text"
                                    class="form-control @error('name') is-invalid @enderror" name="name"
                                    value="{{ old('name') }}" required>
                                @error('name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Email <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="email" type="text"
                                    class="form-control @error('email') is-invalid @enderror" name="email"
                                    value="{{ old('email') }}" autofocus required>
                                @error('email')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Designation</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="designation" type="text"
                                    class="form-control @error('designation') is-invalid @enderror" name="designation"
                                    value="{{ old('designation') }}">
                                @error('designation')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Password
                                <sup class="text-warning">(required)</sup>
                            </label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="password" type="password"
                                    class="form-control @error('password') is-invalid @enderror" name="password" required>
                                @error('password')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Confirm password
                                <sup class="text-warning">(required)</sup>
                            </label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="confirm_password" type="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    name="password_confirmation" required>
                                @error('password')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Role <sup class="text-warning">
                                    (required)</sup></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select data-choices id="role"
                                        class="form-control select2 @error('role') is-invalid @enderror" name="role"
                                        required>
                                        <option value="">Select Role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}"
                                                {{ @$user->role->name == $role->name ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('role')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Avatar</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                {{-- <input id="avatar" type="file"
                                    class="form-control dropify @error('avatar') is-invalid @enderror" name="avatar"
                                    data-height="160" data-default-file="" accept="image/*"> --}}
                                <input type="file" name="avatar"
                                    class="form-control image-input @error('image') is-invalid @enderror" id="imageInput"
                                    accept="image/*">
                                <img id="imagePreview" class="image-preview" alt="Image Preview">
                                @error('avatar')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group mb-2 new_search row pb-0 mb-0">
                            <label class="col-12 col-md-4 col-lg-3">Status</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="custom-control custom-switch" style="padding: 0px;">
                                    <input type="checkbox" class="custom-control-input" name="status" id="status"
                                        {{ @$user->status == true ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn">
                                <i class="bi bi-save ms-0"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('js')
    @include('users.script')
@endpush
