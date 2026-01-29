@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Edit Role',
        ])
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form id="role-edit-form" method="POST" action="{{ route('admin.roles.update', $role->id) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="page" value="{{ request('page') }}">
                        <div class="mb-4">
                            <label for="name" class="form-label fw-semibold">Role Name</label>
                            <input id="name" type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $role->name) }}" placeholder="Enter role name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold mb-3">Assign Permissions</h5>

                            @if (!empty($nested) && count($nested))
                                <div class="accordion" id="permissionsAccordion">
                                    @foreach ($nested as $moduleIndex => $models)
                                        @php
                                            $module = $moduleIndex;
                                            $collapseId = 'collapseModule' . $loop->index;
                                            $headingId  = 'headingModule' . $loop->index;
                                        @endphp

                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="{{ $headingId }}">
                                                <button
                                                    class="accordion-button fw-medium {{ !$loop->first ? 'collapsed' : '' }}"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                    aria-controls="{{ $collapseId }}">
                                                    {{ ucfirst($module) }} Module
                                                </button>
                                            </h2>

                                            <div id="{{ $collapseId }}"
                                                class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                                aria-labelledby="{{ $headingId }}" data-bs-parent="#permissionsAccordion">
                                                <div class="accordion-body">
                                                    <div class="row g-3">
                                                        @foreach ($models as $model => $modelPermissions)
                                                            <div class="col-lg-6">
                                                                <div class="border rounded-3 bg-light-subtle p-3 shadow-sm h-100 model-box">
                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                        <strong class="text-capitalize text-primary">{{ $model }}</strong>

                                                                        <div class="form-check form-switch mb-0">
                                                                            <input type="checkbox"
                                                                                class="form-check-input select-all-model"
                                                                                title="Select all">
                                                                        </div>
                                                                    </div>

                                                                    <hr class="my-2">

                                                                    @foreach ($modelPermissions as $perm)
                                                                        @php
                                                                            $parts   = explode('.', $perm->name);
                                                                            $action  = ucfirst(end($parts));
                                                                            $checked = is_array(old('permissions'))
                                                                                ? in_array($perm->id, old('permissions'))
                                                                                : in_array($perm->id, $rolePermissions);
                                                                        @endphp
                                                                        <div class="form-check mb-2">
                                                                            <input class="form-check-input permission-checkbox"
                                                                                type="checkbox" name="permissions[]"
                                                                                value="{{ $perm->id }}"
                                                                                id="perm_{{ $perm->id }}"
                                                                                {{ $checked ? 'checked' : '' }}>
                                                                            <label class="form-check-label small" for="perm_{{ $perm->id }}">
                                                                                {{ $action }}
                                                                            </label>
                                                                            <small class="text-muted ms-2 d-block">{{ $perm->name }}</small>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @error('permissions')
                                    <div class="text-danger mt-2 small">{{ $message }}</div>
                                @enderror

                                <div id="permissions-error-client" class="text-danger mt-2 small d-none">Select at least one permission.</div>
                            @else
                                <div class="text-muted">No permissions available.</div>
                            @endif
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg px-4 update-btn" id="updateRoleBtn">
                                <span class="btn-text"><i class="bi bi-save2 me-1"></i> Save Changes</span>
                                <span class="spinner-border text-light spinner-border-sm ms-2 d-none update-spinner" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
  @push('js')
    <script>
        (function ($) {
            const $form    = $('#role-edit-form');
            const $btn     = $('#updateRoleBtn');
            const $spinner = $('.update-spinner');

            $('.model-box').each(function () {
                const $box        = $(this);
                const $selectAll  = $box.find('.select-all-model');
                const $checkboxes = $box.find('.permission-checkbox');

                if ($checkboxes.length === 0) {
                    if ($selectAll.length) $selectAll.hide();
                    return;
                }

                $selectAll.prop('checked', $checkboxes.toArray().every(cb => cb.checked));

                $selectAll.on('change', function () {
                    $checkboxes.prop('checked', this.checked).trigger('change');
                });

                $checkboxes.on('change', function () {
                    $selectAll.prop('checked', $checkboxes.toArray().every(cb => cb.checked));
                });
            });

            function enableButton()  { $btn.prop('disabled', false); $spinner.addClass('d-none'); }
            function disableButton() { $btn.prop('disabled', true);  $spinner.removeClass('d-none'); }
            enableButton();

            $.validator.addMethod('atLeastOnePermission', function () {
                return $('.permission-checkbox:checked').length > 0;
            }, 'Select at least one permission.');

            const validator = $form.validate({
                ignore: [],
                rules: {
                    name: {
                        required: true,
                        minlength: 3
                    },
                    'permissions[]': {
                        atLeastOnePermission: true
                    }
                },
                messages: {
                    name: {
                        required: 'Role name is required.',
                        minlength: 'Role name must be at least 3 characters.'
                    },
                    'permissions[]': {
                        atLeastOnePermission: 'Select at least one permission.'
                    }
                },
                errorClass: 'is-invalid',
                validClass: 'is-valid',
                errorElement: 'div',
                highlight: function (element) {
                    const $el = $(element);
                    if (!$el.hasClass('permission-checkbox')) {
                        $el.addClass('is-invalid');
                    } else {
                        $('#permissions-error-client').removeClass('d-none');
                    }
                },
                unhighlight: function (element) {
                    const $el = $(element);
                    if (!$el.hasClass('permission-checkbox')) {
                        $el.removeClass('is-invalid');
                    } else if ($('.permission-checkbox:checked').length > 0) {
                        $('#permissions-error-client').addClass('d-none').text('');
                    }
                },
                errorPlacement: function (error, element) {
                    const $el = $(element);
                    if ($el.hasClass('permission-checkbox')) {
                        $('#permissions-error-client').text(error.text()).removeClass('d-none');
                    } else {
                        error.addClass('invalid-feedback');
                        if ($el.parent('.input-group').length) {
                            error.insertAfter($el.parent());
                        } else {
                            error.insertAfter($el);
                        }
                    }
                },
                submitHandler: function (form) {
                    disableButton();
                    form.submit();
                }
            });

            $(document).on('change', '.permission-checkbox', function () {
                if ($('.permission-checkbox:checked').length > 0) {
                    $('#permissions-error-client').addClass('d-none').text('');
                } else if ($form.valid() === false) {
                    $('#permissions-error-client').removeClass('d-none');
                }
            });
        })(jQuery);
    </script>
@endpush

