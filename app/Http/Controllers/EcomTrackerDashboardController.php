<?php

namespace App\Http\Controllers;

use App\Exports\EcomTrackerDashboardExport;
use App\Http\Controllers\Concerns\CountsTrackerFilters;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcomTrackerDashboardController extends Controller
{
    use CountsTrackerFilters;

    public function __construct(
        private EcomTrackerDashboardService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $filters = array_merge(
            $this->dashboardDateFilters($request),
            $this->dashboardSessionFilters($request),
        );

        $dashboard = $this->service->getDashboardData($filters);
        $dashboard['chart_payload'] = $this->service->chartPayload($dashboard);

        return view('ecom_tracker.dashboard', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
            'activeFilterCount' => $this->dashboardActiveFilterCount($request),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $filters = array_merge(
            $this->dashboardDateFilters($request),
            $this->dashboardSessionFilters($request),
        );

        $range = $this->service->resolveDateRange($filters);
        $filename = 'ecom-tracker-dashboard-'.$range['from']->format('Y-m-d').'-'.$range['to']->format('Y-m-d').'.xlsx';

        return Excel::download(
            EcomTrackerDashboardExport::fromFilters($this->service, $filters),
            $filename,
        );
    }
}
