<?php

namespace App\Http\Controllers;

use App\Services\EcomTrackerCompareService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardCompareController extends Controller
{
    public function index(Request $request, EcomTrackerCompareService $compareService): View|RedirectResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        return $compareService->handle($request);
    }
}
