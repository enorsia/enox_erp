<script>
    $(document).ready(function() {
        $('#lookupNameForm').validate({
            rules: {
                name: {
                    required: true
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
    });
</script>
