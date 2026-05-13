<?php

namespace App\Http\Controllers;

use App\Exports\DashboardAnalyticsExport;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardAnalyticsController extends Controller
{
    public function __construct(
        private DashboardAnalyticsService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['period', 'from_year_month', 'to_year_month']);

        // Default to this_month if not set
        if (empty($filters['period'])) {
            $filters['period'] = 'this_month';
        }

        $data = $this->service->getDashboardData($filters);

        return view('dashboard.analytics', array_merge($data, ['filters' => $filters]));
    }

    public function export(Request $request)
    {
        $filters = $request->only(['period', 'from_year_month', 'to_year_month']);

        // Default to this_month if not set
        if (empty($filters['period'])) {
            $filters['period'] = 'this_month';
        }

        $range  = $this->service->resolveDateRange($filters);
        $export = new DashboardAnalyticsExport(
            $range['from']->toDateString(),
            $range['to']->toDateString(),
            $range['months'],
            ['label' => $range['label']],
        );

        return $export->download($this->service);
    }
}
