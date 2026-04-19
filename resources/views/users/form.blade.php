@extends('backend.master')


@section('content')
    <div class="top_title">
        <h6>{{ @$user ? 'Edit User' : 'Create User' }}</h6>
        <a href="{{ url()->previous() }}" class="btn tlt-btn">
            <i class="fa fa-chevron-left ms-0"></i>
            Back
        </a>
    </div>

    <form method="POST" action="{{ isset($user) ? route('users.update', $user->id) : route('users.store') }}"
        enctype="multipart/form-data" id="validateForm">
        @csrf
        @isset($user)
            @method('PUT')
        @endisset

        <div class="row justify-content-center">
            <div class="col-lg-12">
                <div class="card-dark main-card mb-3 card">
                    <div class="card-body pb-0">
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Name <span class="text-warning">(Required)</span></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name"
                                    value="{{ $user->name ?? old('name') }}" placeholder="Enter user name" autofocus>
                                @error('name')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Email <span class="text-warning">(Required)</span></label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="email" type="text" class="form-control @error('email') is-invalid @enderror" name="email"
                                    value="{{ $user->email ?? old('email') }}" placeholder="Enter user email" autofocus>
                                @error('email')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Designation</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="designation" type="text" class="form-control @error('designation') is-invalid @enderror"
                                    name="designation" value="{{ $user->designation ?? old('designation') }}" placeholder="Enter user designation" autofocus>
                                @error('designation')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Password</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                                    name="password" placeholder="Enter user password" autofocus>
                                @error('password')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Confirm Password</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="confirm_password" type="password" class="form-control @error('password') is-invalid @enderror"
                                    name="password_confirmation" placeholder="Re-Type password" autofocus>
                                @error('password')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Warehouse</label>
                            <div class="col-12 col-md-8 col-lg-9 new_select_field">
                                <select id="warehouse_id" class="form-select js-example-basic-single @error('warehouse_id') is-invalid @enderror"
                                    name="warehouse_id" autofocus>
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}" {{ @$user->warehouse_id == $warehouse->id ? 'selected' : '' }}>
                                            {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Outlet <span class="text-warning">(If required)</span></label>
                            <div class="col-12 col-md-8 col-lg-9 new_select_field">
                                <select id="outlet_id" class="form-select js-example-basic-single @error('outlet_id') is-invalid @enderror"
                                    name="outlet_id" autofocus>
                                    <option value="">Select Outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ @$user->outlet_id == $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('outlet_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Factory <span class="text-warning">(If required)</span></label>
                            <div class="col-12 col-md-8 col-lg-9 new_select_field">
                                <select id="factory_id" class="form-select js-example-basic-single @error('factory_id') is-invalid @enderror"
                                    name="factory_id" autofocus>
                                    <option value="">Select Factory</option>
                                    @foreach ($factories as $factory)
                                        <option value="{{ $factory->id }}" {{ @$user->factory_id == $factory->id ? 'selected' : '' }}>
                                            {{ $factory->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('factory_id')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Role</label>
                            <div class="col-12 col-md-8 col-lg-9 new_select_field">
                                <select id="role"
                                    class="form-control js-example-basic-single @error('role') is-invalid @enderror"
                                    name="role" autofocus>
                                    <option value="">Select Role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ @$user->role->id == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('role')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row">
                            <label class="col-12 col-md-4 col-lg-3">Avatar</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <input id="avatar" type="file"
                                    class="form-control dropify @error('avatar') is-invalid @enderror" name="avatar"
                                    data-default-file="{{ @$user->avatar != null ? asset('upload/user_images/' . @$user->avatar) : '' }}">
                                <input type="hidden" name="avatar_hidden" id="avatar_hidden" value="{{ $user->avatar ?? '' }}">
                                @error('avatar')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="position-relative form-group new_search row pb-0 mb-0">
                            <label class="col-12 col-md-4 col-lg-3">Status</label>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="custom-control custom-switch" style="padding: 0px;">
                                    <input type="checkbox" class="custom-control-input" name="status" id="status"
                                        {{ @$user->status == true ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="status">1</label>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-lg btn-primary fs-6 px-4 submit-btn">
                                <i class="bi bi-save ms-0"></i> {{ @$user ? 'Update' : 'Save' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

{{-- JS moved to resources/js/pages/users/script.js --}}
