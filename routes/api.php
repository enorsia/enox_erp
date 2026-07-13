<?php

use App\Http\Controllers\Api\TrackController;
use App\Http\Middleware\VerifyTrackerApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:120,1', VerifyTrackerApiKey::class])
    ->post('/track', [TrackController::class, 'store']);
