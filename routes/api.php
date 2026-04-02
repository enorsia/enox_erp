<?php

use App\Http\Controllers\PayloadRelayController;
use Illuminate\Support\Facades\Route;

Route::post('/enoxsuite/payloads', [PayloadRelayController::class, 'store'])
    ->name('api.enoxsuite.payloads.store');

