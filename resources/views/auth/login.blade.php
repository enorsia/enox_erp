<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{config('app.name')}} | @yield('title', 'Login')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet">
    <script src="{{ asset('assets/js/config.js') }}"></script>
</head>

<body class="h-100">
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 h-100">
            <!-- Left Side - Login Form -->
            <div class="col-xxl-7 d-flex align-items-center justify-content-center" style="min-height: 100vh;">
                <div class="col-lg-6 col-md-8 col-sm-10 px-4">
                    <div class="auth-logo mb-4">
                        <a href="{{route('admin.login')}}" class="logo-dark">
                            <img src="{{asset('assets/images/logo-dark.png')}}" height="24" alt="logo dark">
                        </a>

                        <a href="{{route('admin.login')}}" class="logo-light">
                            <img src="{{asset('assets/images/logo-light.png')}}" height="24" alt="logo light">
                        </a>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <h2 class="fw-bold fs-24">Sign In</h2>
                    <p class="text-muted mt-1 mb-4">Enter your email address and password to access admin panel.</p>

                    <form action="{{route('admin.login.post')}}" class="authentication-form" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="example-email">Email</label>
                            <input type="email" id="example-email" name="email" class="form-control" placeholder="Enter your email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="example-password">Password</label>
                            <input type="password" id="example-password" name="password" class="form-control" placeholder="Enter your password">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkbox-signin" name="remember_me">
                                <label class="form-check-label" for="checkbox-signin">Remember me</label>
                            </div>
                        </div>

                        <div class="mb-1 text-center d-grid">
                            <button class="btn btn-soft-primary" type="submit">Sign In</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Side - Image -->
            <div class="col-xxl-5 d-none d-xxl-flex align-items-center p-3" style="min-height: 100vh;">
                <div class="w-100 h-100 overflow-hidden" style="border-radius: 20px;">
                    <img src="{{asset('assets/images/small/img-10.jpg')}}" alt="" class="w-100 h-100" style="object-fit: cover; object-position: center;">
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/vendor.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>

