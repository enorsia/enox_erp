<?php

namespace App\Http\Controllers;

use App\Exports\EcomTrackerDashboardExport;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcomTrackerDashboardController extends Controller
{
    public function __construct(
        private EcomTrackerDashboardService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('general.ecom_tracker_dashboard.index');

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $filters['period'] = $filters['period'] ?? '30d';

        $dashboard = $this->service->getDashboardData($filters);
        $dashboard['chart_payload'] = $this->service->chartPayload($dashboard);

        return view('ecom_tracker.dashboard', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('general.ecom_tracker_dashboard.index');

        $filters = $request->only(['period', 'date_from', 'date_to']);
        $filters['period'] = $filters['period'] ?? '30d';

        $range = $this->service->resolveDateRange($filters);
        $filename = 'ecom-tracker-dashboard-'.$range['from']->format('Y-m-d').'-'.$range['to']->format('Y-m-d').'.xlsx';

        return Excel::download(
            EcomTrackerDashboardExport::fromFilters($this->service, $filters),
            $filename,
        );
    }
}
