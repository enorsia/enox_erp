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
    <style>
        .top_title {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filter_close_sec {
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
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

    <script src="{{ asset('assets/js/vendor.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/gh/enorsia/assets-new/admin/admin-js/iziToast.min.js"></script>
    @include('master.lara-izitoast')
    @stack('js')
</body>

</html>
