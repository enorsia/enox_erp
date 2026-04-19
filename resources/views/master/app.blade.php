<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{config('app.name')}} | @yield('title', 'Admin')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

    {{--
        Vendor CSS loaded directly from public/assets/css/
        (font paths like ../fonts/boxicons.ttf resolve correctly from here)
    --}}
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/iziToast.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/select2-v5.min.css') }}" rel="stylesheet">

    {{-- Template config — must run before vendor.js & app.js --}}
    <script src="{{ asset('assets/js/config.js') }}"></script>

    {{-- Vite: compiles Tailwind CSS + custom.css only --}}
    @vite('resources/js/app.js')

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

    {{--
        Template & Library JS — loaded as regular scripts (need global scope).
        Must come AFTER the DOM. Order matters:
        1. jQuery (must be first — plugins depend on it)
        2. vendor.js (Bootstrap, SimpleBar, Iconify, etc.)
        3. app.js (ThemeLayout — sidebar toggle, menu, config)
        4. Plugins (Select2, validate, iziToast, SweetAlert2)
    --}}
    <script src="{{ asset('assets/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="{{ asset('assets/js/select2-v4.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('assets/js/iziToast.min.js') }}"></script>
    <script src="{{ asset('assets/js/sweetalert2@11.min.js') }}"></script>
    <script src="{{ asset('assets/js/customSweetalert2.min.js') }}"></script>

    {{-- iziToast flash messages --}}
    @include('master.lara-izitoast')

    {{-- Common functions (loader, deleteData, approveData, validate-form, image-preview) --}}
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
    </script>

    @stack('js')
</body>

</html>
