<script>
    $("#year").select2({
        placeholder: "Select a year",
        allowClear: true
    });
    $(document).ready(function() {
        $('#ExpenseForm').validate({
            rules: {
                year: {
                    required: true
                },
                conversion_rate: {
                    required: true
                },
                commercial_expense: {
                    required: true
                },
                enorsia_expense_bd: {
                    required: true
                },
                enorsia_expense_uk: {
                    required: true
                },
            },
            submitHandler: function(form) {
                $('.submit-btn').html(loader);
                $('.submit-btn').attr('disabled', true);
                setTimeout(function() {
                    form.submit();
                }, 400);

            }
        });
    });
</script>
