<?php

namespace App\Http\Controllers;

use App\Services\EcomTrackerFeatureGate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardController extends EcomTrackerAdminController
{
    public function __construct(EcomTrackerFeatureGate $featureGate)
    {
        parent::__construct($featureGate);
    }

    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $data = [
            'period' => $request->input('period', '24h'),
            'dateFrom' => (string) $request->input('date_from', ''),
            'dateTo' => (string) $request->input('date_to', ''),
        ];

        return view('ecom_tracker.dashboard', compact('data'));
    }
}
