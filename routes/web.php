<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FabricationController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SalesChartController;
use App\Http\Controllers\SellingChartExpenseController;
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
    Route::resource('platforms', PlatformController::class);

    Route::controller(SalesChartController::class)->group(function () {
        Route::get('selling-chart/manage', 'index')->name('selling_chart.index');
        Route::get('selling-chart/get-color-by-search/{search}', 'getColorBySearch')->name('selling_chart.get.color');
        Route::get('selling-chart/get-size-range/{department_id}/{ch_info_id?}', 'getSizeRange')->name('selling_chart.get.size.range');
        Route::get('selling-chart/manage/create', 'create')->name('selling_chart.create');
        Route::post('selling-chart/manage', 'store')->name('selling_chart.store');
        Route::get('selling-chart/manage/upload-sheet', 'uploadSheet')->name('selling_chart.upload.sheet');
        Route::post('selling-chart/manage/import', 'import')->name('selling_chart.import');
        Route::get('selling-chart/manage/{id}/edit', 'edit')->name('selling_chart.edit');
        Route::put('selling-chart/manage/{id}', 'update')->name('selling_chart.update');
        Route::delete('selling-chart/manage/{id}', 'destroy')->name('selling_chart.destroy');
        Route::get('selling-chart/manage/bulk-edit', 'bulkEdit')->name('selling_chart.bulk.edit');
        Route::post('selling-chart/manage/bulk-update', 'bulkUpdate')->name('selling_chart.bulk.update');
        Route::post('selling-chart/manage/approve/{id}', 'approve')->name('selling_chart.approve');
        Route::get('selling-chart/manage/view/{id}', 'viewSingleChart')->name('selling_chart.view.single.chart');
    });
    Route::controller(FabricationController::class)->group(function () {
        Route::get('selling-chart/fabrication', 'index')->name('selling_chart.fabrication.index');
        Route::get('selling-chart/fabrication/create', 'create')->name('selling_chart.fabrication.create');
        Route::post('selling-chart/fabrication', 'store')->name('selling_chart.fabrication.store');
    });
    Route::controller(SellingChartExpenseController::class)->group(function () {
        Route::get('selling-chart/expense', 'index')->name('selling_chart.expense.index');
        Route::get('selling-chart/expense/create', 'create')->name('selling_chart.expense.create');
        Route::post('selling-chart/expense', 'store')->name('selling_chart.expense.store');
        Route::get('selling-chart/expense/{id}/edit', 'edit')->name('selling_chart.expense.edit');
        Route::put('selling-chart/expense/{id}', 'update')->name('selling_chart.expense.update');
        Route::delete('selling-chart/expense/{id}', 'destroy')->name('selling_chart.expense.destroy');
    });
});
