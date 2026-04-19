/**
 * Common JS - Shared functions used across all pages
 * These functions are made globally available via window object
 */

// Loader HTML for button states
window.loader = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    <span class="">Loading...</span>`;

// Initialize jQuery Validate on forms with .validate-form class
$(document).ready(function () {
    if ($('.validate-form').length) {
        $('.validate-form').validate({
            ignore: [],
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            errorPlacement: function (error, element) {
                if (element.hasClass('choices__input')) {
                    error.insertAfter(element.closest('.choices'));
                } else {
                    error.insertAfter(element);
                }
            },
            submitHandler: function (form) {
                const $btn = $('.validate-btn');
                $btn.prop('disabled', true).html(window.loader);
                form.submit();
            }
        });
    }

    // Image preview on file input change
    $(document).on('change', '.image-input', function () {
        const file = this.files[0];
        const preview = $(this).siblings('.image-preview');

        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.attr('src', e.target.result).removeClass('d-none').show();
            };
            reader.readAsDataURL(file);
        } else {
            preview.addClass('d-none').hide();
        }
    });
});

/**
 * Delete confirmation using SweetAlert2
 * @param {string|number} id - The ID of the record to delete
 */
window.deleteData = function (id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete-form-' + id).submit();
        }
    });
};

/**
 * Approve confirmation using SweetAlert2
 * @param {string|number} id - The ID of the record to approve
 */
window.approveData = function (id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('approve-form-' + id).submit();
        }
    });
};

