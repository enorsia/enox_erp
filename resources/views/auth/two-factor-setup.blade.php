<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ config('app.name') }} | Set up 2FA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet">
</head>

<body class="h-100">
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 h-100">
            <div class="col-xxl-7 d-flex align-items-center justify-content-center" style="min-height: 100vh;">
                <div class="col-lg-6 col-md-8 col-sm-10 px-4">
                    <h2 class="fw-bold fs-24">Set up Google Authenticator</h2>
                    <p class="text-muted mt-1 mb-4">Scan the QR code with Google Authenticator, then enter the code to finish setup for <strong>{{ $email }}</strong>.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <div class="mb-4 text-center">
                        <img src="{{ app(\App\Services\TwoFactorAuthService::class)->qrCodeDataUri($otpUrl, 200) }}" width="200" height="200" alt="2FA QR code" class="border rounded">
                        <p class="text-muted small mt-2 mb-0">Manual key: <code>{{ $secret }}</code></p>
                    </div>

                    <form action="{{ route('admin.two-factor.setup.post') }}" method="POST" autocomplete="on">
                        @csrf
                        <input type="hidden" name="username" value="{{ $email }}" autocomplete="username" aria-hidden="true" tabindex="-1">
                        <div class="mb-3">
                            <label class="form-label" for="code">Confirm with 6-digit code</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="code" name="code" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember_device" name="remember_device" value="1">
                                <label class="form-check-label" for="remember_device">Trust this device after setup</label>
                            </div>
                        </div>
                        <div class="mb-1 text-center d-grid">
                            <button class="btn btn-primary" type="submit">Complete setup</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
