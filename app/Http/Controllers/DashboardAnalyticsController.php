<?php

namespace App\Http\Controllers;

use App\Exports\DashboardAnalyticsExport;
use App\Services\DashboardAnalyticsService;
use App\Services\SalesReportExportColumns;
use App\Services\SalesReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardAnalyticsController extends Controller
{
    public function __construct(
        private DashboardAnalyticsService $service,
        private SalesReportService $reportService,
        private SalesReportExportColumns $exportColumns,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['period', 'from_year_month', 'to_year_month']);

        // Default to this_month if not set
        if (empty($filters['period'])) {
            $filters['period'] = 'this_month';
        }

        $data = $this->service->getDashboardData($filters);

        return view('sale-spend.dashboard.index', array_merge($data, ['filters' => $filters]));
    }

    public function reportExport(Request $request): View
    {
        return view('sales.analytics_report', $this->reportService->buildPageData($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['period', 'from_year_month', 'to_year_month']);

        // Default to this_month if not set
        if (empty($filters['period'])) {
            $filters['period'] = 'this_month';
        }

        $tablesParam = $request->input('tables', 'daily_report,return_breakdown,weekly_breakdown');
        $tables      = array_filter(array_map('trim', explode(',', $tablesParam)));
        if (empty($tables)) {
            $tables = ['daily_report', 'return_breakdown', 'weekly_breakdown'];
        }

        $range  = $this->service->resolveDateRange($filters);
        $preview = $this->service->getDailyExportData(
            $range['from']->toDateString(),
            $range['to']->toDateString(),
            $range['months'],
        );
        $groupedColumns = $this->exportColumns->groupedColumnsFromTree($preview['column_data']['tree'] ?? []);
        $sections       = $this->exportColumns->buildSections($groupedColumns, $preview['root_platforms']);
        $columnSelection = $this->exportColumns->parseSelection(
            $request->input('export_columns'),
            $sections,
        );

        $export = new DashboardAnalyticsExport(
            $range['from']->toDateString(),
            $range['to']->toDateString(),
            $range['months'],
            ['label' => $range['label']],
            array_values($tables),
            $columnSelection,
        );

        return $export->download($this->service);
    }
}
