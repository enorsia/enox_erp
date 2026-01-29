@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Create Role',
        ])
    </div>
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card-dark main-card mb-3 card">
                <div class="card-body">
                    <form id="role-create-form" method="POST" action="{{ route('admin.roles.store') }}" novalidate>
                        @csrf

                        {{-- Role Name --}}
                        <div class="mb-4">
                            <label for="name" class="form-label fw-semibold">Role Name</label>
                            <input id="name" type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="Enter role name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Permissions --}}
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3">Permissions</h5>

                            @if (!empty($nested) && count($nested))
                                <div class="accordion" id="permissionsAccordion">
                                    @foreach ($nested as $moduleIndex => $moduleItem)
                                        @php
                                            $module = $moduleIndex;
                                            $models = $moduleItem;
                                            $collapseId = 'collapseModule' . $moduleIndex;
                                            $headingId = 'headingModule' . $moduleIndex;
                                        @endphp

                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="{{ $headingId }}">
                                                <button
                                                    class="accordion-button fw-medium {{ $moduleIndex !== 0 ? 'collapsed' : '' }}"
                                                    type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#{{ $collapseId }}"
                                                    aria-expanded="{{ $moduleIndex === 0 ? 'true' : 'false' }}"
                                                    aria-controls="{{ $collapseId }}">
                                                    {{ $module }} Module
                                                </button>
                                            </h2>

                                            <div id="{{ $collapseId }}"
                                                class="accordion-collapse collapse {{ $moduleIndex === 0 ? 'show' : '' }}"
                                                aria-labelledby="{{ $headingId }}" data-bs-parent="#permissionsAccordion">
                                                <div class="accordion-body">
                                                    <div class="row g-3">
                                                        @foreach ($models as $model => $modelPermissions)
                                                            <div class="col-lg-6">
                                                                <div class="border rounded-3 bg-light-subtle p-3 shadow-sm h-100 model-box">
                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                        <strong class="text-capitalize text-primary">{{ $model }}</strong>

                                                                        {{-- Select all for this model --}}
                                                                        <div class="form-check form-switch mb-0">
                                                                            <input type="checkbox"
                                                                                class="form-check-input select-all-model"
                                                                                title="Select all">
                                                                        </div>
                                                                    </div>

                                                                    <hr class="my-2">

                                                                    @foreach ($modelPermissions as $perm)
                                                                        @php
                                                                            $parts = explode('.', $perm->name);
                                                                            $action = ucfirst(end($parts));
                                                                        @endphp
                                                                        <div class="form-check mb-2">
                                                                            <input class="form-check-input permission-checkbox"
                                                                                type="checkbox" name="permissions[]"
                                                                                value="{{ $perm->id }}"
                                                                                id="perm_{{ $perm->id }}"
                                                                                {{ is_array(old('permissions')) && in_array($perm->id, old('permissions')) ? 'checked' : '' }}>
                                                                            <label class="form-check-label small" for="perm_{{ $perm->id }}">{{ $action }}</label>
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

                                {{-- Server-side error (if any) --}}
                                @error('permissions')
                                    <div class="text-danger mt-2 small">{{ $message }}</div>
                                @enderror

                                {{-- Client-side error placeholder (created/filled by JS if needed) --}}
                                <div id="permissions-error-client" class="text-danger mt-2 small d-none">Select at least one permission.</div>
                            @else
                                <div class="text-muted">No permissions available.</div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg px-4 create-btn" id="createRoleBtn">
                                <span class="btn-text"><i class="bi bi-check-circle me-1"></i> Create Role</span>
                                <span class="spinner-border text-light spinner-border-sm ms-2 d-none create-spinner" role="status" aria-hidden="true"></span>
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
            const $form    = $('#role-create-form');
            const $btn     = $('#createRoleBtn');
            const $spinner = $('.create-spinner');

            // ===== Model-level "select all" logic =====
            $('.model-box').each(function() {
                const $box = $(this);
                const $selectAll  = $box.find('.select-all-model');
                const $checkboxes = $box.find('.permission-checkbox');

                if ($checkboxes.length === 0) {
                    if ($selectAll.length) $selectAll.hide();
                    return;
                }

                $selectAll.prop('checked', $checkboxes.toArray().every(cb => cb.checked));

                $selectAll.on('change', function() {
                    $checkboxes.prop('checked', this.checked).trigger('change');
                });

                $checkboxes.on('change', function() {
                    $selectAll.prop('checked', $checkboxes.toArray().every(cb => cb.checked));
                });
            });

            // ===== Spinner helpers =====
            function enableButton()  { $btn.prop('disabled', false); $spinner.addClass('d-none'); }
            function disableButton() { $btn.prop('disabled', true);  $spinner.removeClass('d-none'); }
            enableButton();

            // ===== jQuery Validation setup =====
            // Custom method: ensure at least one permission checkbox is checked
            $.validator.addMethod('atLeastOnePermission', function() {
                return $('.permission-checkbox:checked').length > 0;
            }, 'Select at least one permission.');

            const validator = $form.validate({
                ignore: [], // validate hidden fields too if needed
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
                    } else {
                        if ($('.permission-checkbox:checked').length > 0) {
                            $('#permissions-error-client').addClass('d-none').text('');
                        }
                    }
                },
                errorPlacement: function (error, element) {
                    const $el = $(element);

                    // For the permissions group, route all errors to a single area
                    if ($el.hasClass('permission-checkbox')) {
                        const $container = $('#permissions-error-client');
                        $container.text(error.text()).removeClass('d-none');
                    } else {
                        // Standard Bootstrap-friendly placement
                        error.addClass('invalid-feedback');
                        if ($el.parent('.input-group').length) {
                            error.insertAfter($el.parent());
                        } else {
                            error.insertAfter($el);
                        }
                    }
                },
                submitHandler: function (form) {
                    // Only disable + spin when valid
                    disableButton();
                    form.submit();
                }
            });

            // Live re-check for permissions container visibility
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

