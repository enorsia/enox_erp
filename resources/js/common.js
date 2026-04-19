/**
 * Common functions used across all pages.
 * Imported via app.js through Vite.
 */

var loader = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    <span class="">Loading...</span>`;
window.loader = loader;

$(document).ready(function () {
    $('.validate-form').validate({
        ignore: [],
        errorClass: 'is-invalid',
        validClass: 'is-valid',
        errorElement: 'div',
        errorPlacement: function(error, element) {
            if (element.hasClass('choices__input')) {
                error.insertAfter(element.closest('.choices'));
            } else {
                error.insertAfter(element);
            }
        },
        submitHandler: function(form) {
            const $btn = $('.validate-btn');
            $btn.prop('disabled', true).html(loader);
            form.submit();
        }
    });
});

window.deleteData = function(id) {
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

window.approveData = function(id) {
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

$(document).on('change', '.image-input', function() {
    const file = this.files[0];
    const preview = $(this).siblings('.image-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.attr('src', e.target.result).removeClass('d-none').show();
        };
        reader.readAsDataURL(file);
    } else {
        preview.addClass('d-none').hide();
    }
});
