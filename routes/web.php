<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;


Route::get('/', fn() => Auth::check()
    ? redirect()->route('admin.dashboard')
    : redirect()->route('admin.login'));

Route::prefix('admin')->name('admin.')->controller(AuthController::class)->group(function () {
    Route::get('/login', 'showLogin')->middleware('guest')->name('login');
    Route::post('/login', 'login')->name('login.post');
    Route::post('/logout', 'logout')->middleware('auth')->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

