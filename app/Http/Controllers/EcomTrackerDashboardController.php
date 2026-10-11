<?php

namespace App\Http\Controllers;

use App\Jobs\TrackerDashboardSyncJob;
use App\Services\EcomActivityListService;
use App\Services\EcomTrackerCompareFilteredDashboardService;
use App\Services\EcomTrackerDashboardService;
use App\Services\TrackerDashboardSyncService;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardController extends Controller
{
    public function index(
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        EcomTrackerCompareFilteredDashboardService $filteredDashboard,
        EcomActivityListService $activityList,
    ): View {
        Gate::authorize('ecom_tracker.dashboard.index');

        $period = EcomActivityListService::normalizePeriod($request->query('period'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );
        $filters = EcomActivityListService::filtersFromRequest($request);
        $filterQuery = EcomActivityListService::filterQueryParams($request);
        $prevQuery = EcomActivityListService::shiftedPeriodQuery($range, -1);
        $nextQuery = EcomActivityListService::shiftedPeriodQuery($range, 1);
        $periodQuery = array_filter([
            'period' => $period,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
            'back' => $request->fullUrl(),
        ]) + $filterQuery;

        $data = [
            'period' => $period,
            'dateFrom' => $range['from']->toDateString(),
            'dateTo' => $range['to']->toDateString(),
            'rangeLabel' => TrackerTime::formatLocalDateRangeLabel($range['from'], $range['to']),
            'prevUrl' => $prevQuery ? route('admin.ecom-tracker.dashboard', $prevQuery + $filterQuery) : null,
            'nextUrl' => $nextQuery ? route('admin.ecom-tracker.dashboard', $nextQuery + $filterQuery) : null,
            'resetUrl' => route('admin.ecom-tracker.dashboard', array_filter([
                'period' => $period,
                'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
                'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
            ])),
            'activityLink' => fn (array $activityFilters = []) => route('admin.ecom-activity.index', $periodQuery + array_filter($activityFilters, fn ($value) => $value !== null)),
        ];

        $dashboard = $dashboardService->dashboard($range, $period, $request->fullUrl());
        if (EcomActivityListService::hasActiveFilters($filters)) {
            $dashboard = $filteredDashboard->apply($dashboard, $range, $period, $filters, $request->fullUrl());
        }

        $filterOptions = $activityList->filterOptions($filters['department'] ?? null);

        return view('ecom_tracker.dashboard', compact('data', 'dashboard', 'filterOptions'));
    }

    public function sync(Request $request, TrackerDashboardSyncService $syncService): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $result = TrackerDashboardSyncJob::start($syncService);

        if ($result['status'] === TrackerDashboardSyncJob::ALREADY_RUNNING) {
            return response()->json([
                'ok' => false,
                'message' => 'Sync is already running. Please wait until it finishes.',
            ], 409);
        }

        if ($result['status'] === TrackerDashboardSyncJob::NOTHING_TO_SYNC) {
            return response()->json([
                'ok' => false,
                'message' => 'No more data to sync.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => "Sync started: {$result['batches']} batch(es), {$result['sessions']} session(s). Ensure a queue worker is running.",
            'batches' => $result['batches'],
            'sessions' => $result['sessions'],
        ]);
    }
}
