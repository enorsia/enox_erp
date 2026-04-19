/**
 * Roles - Create Page JS
 */
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
    $.validator.addMethod('atLeastOnePermission', function() {
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
            } else {
                if ($('.permission-checkbox:checked').length > 0) {
                    $('#permissions-error-client').addClass('d-none').text('');
                }
            }
        },
        errorPlacement: function (error, element) {
            const $el = $(element);
            if ($el.hasClass('permission-checkbox')) {
                const $container = $('#permissions-error-client');
                $container.text(error.text()).removeClass('d-none');
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

    // Live re-check for permissions container visibility
    $(document).on('change', '.permission-checkbox', function () {
        if ($('.permission-checkbox:checked').length > 0) {
            $('#permissions-error-client').addClass('d-none').text('');
        } else if ($form.valid() === false) {
            $('#permissions-error-client').removeClass('d-none');
        }
    });
})(jQuery);

