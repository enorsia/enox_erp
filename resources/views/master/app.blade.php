<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>EnoX ERP | @yield('title', 'Admin')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet">
    <script src="{{ asset('assets/js/config.js') }}"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-css/iziToast.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-css/select2-v5.min.css" />
    <style>
        .top_title {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filter_close_sec {
            border-bottom-width: 2px !important;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .choices {
            width: 100%;
            margin-bottom: 0;
        }

        .is-invalid {
            color: #ef5f5f;
        }

        .iziToast-title,
        .iziToast-message {
            font-size: 16px !important;
            line-height: 16px !important;
        }

        .selling_chart_form .image-preview {
            width: 150px;
            height: 150px;
            display: none;
            margin-top: 10px;
        }

        #input_with_preview input[type="file"] {
            cursor: pointer;
            line-height: 31px;

        }

        #selling_chart_table .new_table table tr th,
        #selling_chart_table .new_table table tr td {
            text-align: center;
        }

        #selling_chart_table .new_table table tbody tr td input,
        #selling_chart_table .new_table table tbody tr td select {
            width: 100% !important;
        }
    </style>
    @stack('css')
</head>

<body>
    <!-- START Wrapper -->
    <div class="wrapper">
        @include('master.header.topbar')
        @include('master.sidebar.index')
        <!-- ==================================================== -->
        <!-- Start right Content here -->
        <!-- ==================================================== -->
        <div class="page-content">
            <!-- Start Container Fluid -->
            <div class="container-xxl">

                @yield('content')

            </div>
            <!-- End Container Fluid -->
            @include('master.footer.index')
        </div>
        <!-- ==================================================== -->
        <!-- End Page Content -->
        <!-- ==================================================== -->

    </div>
    <!-- END Wrapper -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('assets/js/vendor.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-js/select2-v4.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.0/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-js/iziToast.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-js/sweetalert2@11.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-js/customSweetalert2.min.js"></script>
    @include('master.lara-izitoast')
    <script>
        var loader = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
    <span class="">Loading...</span>`;
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

        function deleteData(id) {
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
        }

        function approveData(id) {
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
        }
    </script>
    @stack('js')
</body>

</html>
