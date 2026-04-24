<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EnoxTrackerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('enox-tracker')->group(function () {
    Route::post('/ingest', [EnoxTrackerController::class, 'ingest'])->name('enox-tracker.ingest');
    Route::get('/health', [EnoxTrackerController::class, 'health'])->name('enox-tracker.health');
    Route::get('/debug', [EnoxTrackerController::class, 'debug'])->name('enox-tracker.debug'); // REMOVE in production
});
