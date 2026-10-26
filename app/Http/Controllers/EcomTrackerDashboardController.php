<?php

namespace App\Http\Controllers;

use App\Jobs\TrackerDashboardSyncJob;
use App\Services\EcomActivityListService;
use App\Services\EcomTrackerCompareFilteredDashboardService;
use App\Services\EcomTrackerDashboard\EcomTrackerDashboardService;
use App\Services\TrackerDashboardSyncService;
use App\Support\TrackerTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class EcomTrackerDashboardController extends Controller
{
    private const PRODUCT_PERFORMANCE_PER_PAGE = 30;

    public function index(
        Request $request,
        EcomTrackerDashboardService $dashboardService,
        EcomTrackerCompareFilteredDashboardService $filteredDashboard,
        EcomActivityListService $activityList,
    ): View {
        Gate::authorize('ecom_tracker.dashboard.index');

        $context = $this->trackerPageContext($request, 'admin.ecom-tracker.dashboard');
        $data = $context['data'];
        $filters = $context['filters'];
        $range = $context['range'];
        $period = $context['period'];
        $filterQuery = $context['filterQuery'];

        $dashboard = $dashboardService->dashboard($range, $period, $request->fullUrl());
        if (EcomActivityListService::hasActiveFilters($filters)) {
            $dashboard = $filteredDashboard->apply($dashboard, $range, $period, $filters, $request->fullUrl());
        }

        $data['productsViewAllUrl'] = route('admin.ecom-tracker.dashboard.products', array_filter([
            'period' => $period,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ]) + $filterQuery);

        $filterOptions = $activityList->filterOptions($filters['department'] ?? null);
        $dashboardSyncRunning = TrackerDashboardSyncJob::isRunning();

        return view('ecom_tracker.dashboard', compact('data', 'dashboard', 'filterOptions', 'dashboardSyncRunning'));
    }

    public function products(
        Request $request,
        EcomTrackerDashboardService $dashboardService,
    ): View {
        Gate::authorize('ecom_tracker.dashboard.index');

        $context = $this->trackerPageContext($request, 'admin.ecom-tracker.dashboard.products', productListFiltersOnly: true);
        $data = $context['data'];
        $filters = $context['filters'];
        $range = $context['range'];
        $period = $context['period'];
        $filterQuery = $context['filterQuery'];

        $products = $dashboardService->products(
            $range['from'],
            $range['to'],
            $period,
            $filters,
            null,
        );

        $page = max(1, (int) $request->query('page', 1));
        $perPage = self::PRODUCT_PERFORMANCE_PER_PAGE;
        $allRows = $products['rows'];
        $paginator = new LengthAwarePaginator(
            array_values(array_slice($allRows, ($page - 1) * $perPage, $perPage)),
            count($allRows),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => 'page'],
        );
        $paginator->appends($request->except('page'));

        $data['dashboardUrl'] = route('admin.ecom-tracker.dashboard', array_filter([
            'period' => $period,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
        ]) + $filterQuery);

        return view('ecom_tracker.products', compact('data', 'products', 'paginator'));
    }

    /**
     * @return array{
     *     period: string,
     *     range: array{from: \Carbon\Carbon, to: \Carbon\Carbon},
     *     filters: array<string, mixed>,
     *     filterQuery: array<string, mixed>,
     *     data: array<string, mixed>
     * }
     */
    private function trackerPageContext(Request $request, string $routeName, bool $productListFiltersOnly = false): array
    {
        $period = EcomActivityListService::normalizePeriod($request->query('period'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) $request->query('date_from', ''),
            (string) $request->query('date_to', ''),
        );
        $filters = $productListFiltersOnly
            ? EcomActivityListService::ecomTrackerProductListFiltersFromRequest($request)
            : EcomActivityListService::ecomTrackerFiltersFromRequest($request);
        $filterQuery = $productListFiltersOnly
            ? EcomActivityListService::ecomTrackerProductListFilterQueryParams($request)
            : EcomActivityListService::ecomTrackerFilterQueryParams($request);
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
            'prevUrl' => $prevQuery ? route($routeName, $prevQuery + $filterQuery) : null,
            'nextUrl' => $nextQuery ? route($routeName, $nextQuery + $filterQuery) : null,
            'resetUrl' => route($routeName, array_filter([
                'period' => $period,
                'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
                'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
            ])),
            'activityLink' => fn (array $activityFilters = []) => route('admin.ecom-activity.index', $periodQuery + array_filter($activityFilters, fn ($value) => $value !== null)),
            'filterFormAction' => route($routeName),
            'periodPresetUrl' => fn (string $periodKey) => route($routeName, ['period' => $periodKey] + $filterQuery),
        ];

        return [
            'period' => $period,
            'range' => $range,
            'filters' => $filters,
            'filterQuery' => $filterQuery,
            'data' => $data,
        ];
    }

    public function sync(Request $request, TrackerDashboardSyncService $syncService): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        try {
            $result = TrackerDashboardSyncJob::start($syncService);
        } catch (Throwable $e) {
            Log::error('tracker.dashboard.sync: http sync request failed', [
                'step' => 'dashboard.sync.button',
                'user_id' => $request->user()?->id,
                'exception' => $e,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Sync failed. Check server log (tracker.dashboard.sync).',
            ], 500);
        }

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
