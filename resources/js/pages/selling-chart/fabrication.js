/**
 * Selling Chart — Fabrication page script
 */
$(document).ready(function () {
    if ($('#lookupNameForm').length) {
        $('#lookupNameForm').validate({
            rules: {
                name: { required: true }
            },
            submitHandler: function (form) {
                $('.submit-btn').html(window.loader);
                $('.submit-btn').attr('disabled', true);
                setTimeout(function () { form.submit(); }, 400);
            }
        });
    }
});

