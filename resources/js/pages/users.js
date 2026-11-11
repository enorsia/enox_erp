import $ from '$';

/** Match toggle-track "on" class to hidden checkboxes (Toggle → Checkbox id). */
function syncUserFormToggles() {
    document.querySelectorAll('.toggle-track[id$="Toggle"]').forEach((track) => {
        const checkbox = document.getElementById(track.id.replace('Toggle', 'Checkbox'));
        if (checkbox) {
            checkbox.checked = track.classList.contains('on');
        }
    });
}

$(document).ready(function () {

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
                const tsWrapper = element.closest('.ts-wrapper');
                if (tsWrapper.length) {
                    error.insertAfter(tsWrapper);
                } else {
                    error.insertAfter(element);
                }
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
                syncUserFormToggles();
                const $btn = $('.submit-btn');
                $btn.prop('disabled', true).html(window.loader);
                form.submit();
            },
        });
        $('#validateForm').on('submit', syncUserFormToggles);
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
                const tsWrapper = element.closest('.ts-wrapper');
                if (tsWrapper.length) {
                    error.insertAfter(tsWrapper);
                } else {
                    error.insertAfter(element);
                }
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
                syncUserFormToggles();
                const $btn = $('.submit-btn');
                $btn.prop('disabled', true).html(window.loader);
                form.submit();
            },
        });
        $('#EditValidateForm').on('submit', syncUserFormToggles);
    }
});

