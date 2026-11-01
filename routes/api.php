<?php

use App\Http\Controllers\Api\SellingChartController;
use App\Http\Controllers\Api\TrackController;
use App\Http\Controllers\Api\TrackCtrCategoryController;
use App\Http\Controllers\Api\VisitorSessionController;
use App\Http\Middleware\VerifyTrackerApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:240,1', VerifyTrackerApiKey::class])
    ->post('/track', [TrackController::class, 'store']);

Route::middleware(['throttle:240,1', VerifyTrackerApiKey::class])
    ->post('/tracker/ctr-category-events', [TrackCtrCategoryController::class, 'store']);

Route::middleware(['throttle:120,1', VerifyTrackerApiKey::class])
    ->post('/tracker/resolve-visit', [VisitorSessionController::class, 'store']);


Route::middleware('internal.api')->group(function () {
    Route::controller(SellingChartController::class)->group(function () {
        Route::get('selling-chart/get-discount-histories', 'getDiscountHistories');
        Route::post('selling-chart/update-discount-history', 'updateDiscountHistory');
    });
    Route::controller(TrackController::class)->group(function () {
        Route::get('tracking/ctr-summaries', 'getTrackingCtrSummaries');
        Route::post('tracking/ctr-summaries/settings', 'ctrSummariesSettings');
    });
});
