@extends('backend.master')

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

@push('js')
    @include('backend.partials.validation-script')
    <script>
        $(document).ready(function() {
            $('.dropify').dropify();
            $('.dropify-clear').on('click', function() {
                var dropifyInput = $('.dropify');
                dropifyInput.dropify('reset');
                $("#avatar_hidden").val('');
            });
            $('.js-example-basic-single').select2();
            var isEdit = {{ @$user ? 'false' : 'true' }};

            if ($('#validateForm').length && isEdit) {
                $('#validateForm').validate({
                    rules: {
                        name: {
                            required: true
                        },
                        email: {
                            required: true,
                            email: true
                        },
                        password: {
                            required: true,
                            minlength: 8
                        },
                        password_confirmation: {
                            required: true,
                            minlength: 8
                        },
                        role: {
                            required: true
                        },
                        avatar: {
                            required: true
                        },
                        outlet_id: {
                            required: false
                        },
                        warehouse_id: {
                            required: function() {
                                return $('#outlet_id').val() != '';
                            }
                        }
                    },
                    messages: {
                        email: {
                            email: "Please enter a valid email address."
                        },
                        password: {
                            minlength: "Your password must be at least 8 characters long."
                        },
                        password_confirmation: {
                            minlength: "Your password must be at least 8 characters long."
                        },
                        warehouse_id: {
                            required: "Warehouse is required when outlet is filled."
                        }
                    },
                    errorPlacement: function(error, element) {
                        if (element.hasClass('select2-hidden-accessible')) {
                            error.insertAfter(element.next('.select2').first());
                        } else {
                            error.insertAfter(element);
                        }
                    },
                    submitHandler: function(form) {
                        $('.submit-btn').html(loader);
                        $('.submit-btn').attr('disabled', true);
                        setTimeout(function() {
                            form.submit();
                        }, 400);
                    }
                });
            }
        });
    </script>
@endpush
