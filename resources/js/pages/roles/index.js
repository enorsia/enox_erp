/**
 * Roles - Index Page JS
 */
$(function() {
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const roleId = $btn.data('role-id');
        const form = $('#delete-form-' + roleId);

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'No, cancel!',
            customClass: {
                confirmButton: 'btn btn-primary w-xs me-2 mt-2',
                cancelButton: 'btn btn-danger w-xs mt-2'
            },
            buttonsStyling: false
        }).then(function(result) {
            if (result.isConfirmed) {
                $btn.prop('disabled', true);
                $btn.find('.delete-icon').addClass('d-none');
                $btn.find('.delete-spinner').removeClass('d-none');
                form.submit();
            }
        });
    });
});

