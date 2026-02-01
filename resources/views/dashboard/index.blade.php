@extends('master.app')

@section('content')
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex justify-content-center align-items-center flex-column" style="min-height: 60vh;">
                <div class="logo-box">
                    <div class="logo-dark">
                        <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
                        <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
                    </div>

                    <div class="logo-light">
                        <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
                        <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="logo light">
                    </div>
                </div>
                <div class="mt-3">
                    <h1 class="text-center">Welcome to EnoxSuite</h1>
                </div>
            </div>
        </div>
    </div>
@endsection
