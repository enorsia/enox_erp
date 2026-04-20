import $ from '$';

$(document).ready(function () {

    /* ── Select2 for role dropdown ── */
    if ($.fn.select2) {
        $('#role, [data-select2]').select2({ width: '100%' });
    }

    /* ── Status toggle sync on load ── */
    const statusToggle = document.getElementById('statusToggle');
    if (statusToggle) {
        statusToggle.addEventListener('click', function () {
            const cb = document.getElementById('statusCheckbox');
            if (cb) cb.checked = this.classList.contains('on');
        });
    }

    /* ══════════════════════════════════
       CREATE FORM VALIDATION
    ══════════════════════════════════ */
    if ($('#validateForm').length) {
        $('#validateForm').validate({
            ignore: [],
            errorClass: 'f-error-validate',
            validClass: '',
            errorElement: 'p',
            errorPlacement: function (error, element) {
                error.addClass('f-error');
                error.insertAfter(element);
            },
            rules: {
                name:                  { required: true },
                email:                 { required: true, email: true },
                password:              { required: true, minlength: 8 },
                password_confirmation: { required: true, minlength: 8, equalTo: '#password' },
                role:                  { required: true },
            },
            messages: {
                password: {
                    minlength: 'Password must be at least 8 characters.',
                },
                password_confirmation: {
                    equalTo: 'Passwords do not match.',
                },
            },
            submitHandler: function (form) {
                const $btn = $('.submit-btn');
                $btn.prop('disabled', true).html(window.loader);
                form.submit();
            },
        });
    }

    /* ══════════════════════════════════
       EDIT FORM VALIDATION
    ══════════════════════════════════ */
    if ($('#EditValidateForm').length) {
        $('#EditValidateForm').validate({
            ignore: [],
            errorClass: 'f-error-validate',
            validClass: '',
            errorElement: 'p',
            errorPlacement: function (error, element) {
                error.addClass('f-error');
                error.insertAfter(element);
            },
            rules: {
                name:  { required: true },
                email: { required: true, email: true },
                password: {
                    minlength: 8,
                },
                password_confirmation: {
                    minlength: 8,
                    equalTo: '#password',
                },
                role: { required: true },
            },
            messages: {
                password_confirmation: {
                    equalTo: 'Passwords do not match.',
                },
            },
            submitHandler: function (form) {
                const $btn = $('.submit-btn');
                $btn.prop('disabled', true).html(window.loader);
                form.submit();
            },
        });
    }
});

