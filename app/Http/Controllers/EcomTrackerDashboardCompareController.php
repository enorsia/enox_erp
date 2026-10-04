<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardCompareController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $data = [
            'leftPeriod' => (string) $request->input('left_period', '24h'),
            'leftDateFrom' => (string) $request->input('left_date_from', ''),
            'leftDateTo' => (string) $request->input('left_date_to', ''),
            'rightPeriod' => (string) $request->input('right_period', 'yesterday'),
            'rightDateFrom' => (string) $request->input('right_date_from', ''),
            'rightDateTo' => (string) $request->input('right_date_to', ''),
        ];

        $backUrl = filled($request->input('back'))
            ? (string) $request->input('back')
            : route('admin.ecom-tracker.dashboard');

        return view('ecom_tracker.compare', compact('data', 'backUrl'));
    }
}
