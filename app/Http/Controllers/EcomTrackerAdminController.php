<?php

namespace App\Http\Controllers;

use App\Services\EcomTrackerFeatureGate;

abstract class EcomTrackerAdminController extends Controller
{
    public function __construct(EcomTrackerFeatureGate $featureGate)
    {
        $featureGate->ensureEnabled();
    }
}
