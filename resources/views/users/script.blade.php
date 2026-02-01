<script>
    $(document).ready(function() {
        if ($('#validateForm').length) {
            $('#validateForm').validate({
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
                        minlength: 8,
                        equalTo: "#password"
                    },
                    role: {
                        required: true
                    }
                },
                messages: {
                    password_confirmation: {
                        equalTo: "Password confirmation must match the password"
                    }
                },
                messages: {
                    password_confirmation: {
                        equalTo: "Password confirmation must match the password"
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

        if ($('#EditValidateForm').length) {
            $('#EditValidateForm').validate({
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
                rules: {
                    name: {
                        required: true
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    password: {
                        minlength: 8
                    },
                    password_confirmation: {
                        minlength: 8,
                        equalTo: "#password"
                    },
                    role: {
                        required: true
                    }
                },
                messages: {
                    password_confirmation: {
                        equalTo: "Password confirmation must match the password"
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
