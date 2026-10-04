<?php

namespace App\Http\Controllers;

use App\Exports\EcomTrackerDashboardExport;
use App\Http\Controllers\Concerns\CountsTrackerFilters;
use App\Services\ActivityEcomUserActionSyncService;
use App\Services\EcomTrackerDashboardService;
use App\Services\EcomTrackerFeatureGate;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerRedisSupport;
use App\Support\EcomTrackerViewData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EcomTrackerDashboardController extends EcomTrackerAdminController
{
    use CountsTrackerFilters;

    public function __construct(
        EcomTrackerFeatureGate $featureGate,
        private EcomTrackerDashboardService $service,
        private ActivityEcomUserActionSyncService $actionSyncService,
    ) {
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

    public function syncActions(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $result = $this->actionSyncService->startQueuedSync();

        EcomTrackerLogger::backend()->info('analytics.dashboard.sync', 'Admin started interactive action sync', $result);

        return response()->json([
            'message' => $result['message'],
            'result' => $result,
            'progress' => $result['progress'] ?? $this->actionSyncService->syncProgress(),
        ]);
    }

    public function syncActionsStatus(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        return response()->json($this->actionSyncService->queueSyncStatus());
    }

    public function pauseSyncActions(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $result = $this->actionSyncService->pauseQueuedSync();

        return response()->json($result);
    }

    public function resumeSyncActions(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $result = $this->actionSyncService->resumeQueuedSync();

        return response()->json($result);
    }

    public function cancelSyncActions(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $result = $this->actionSyncService->cancelQueuedSync();

        return response()->json($result);
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        if ($redirect = $this->redirectIfDashboardHasLegacyFilters($request)) {
            return $redirect;
        }

        $filters = $this->dashboardDateFilters($request);

        $range = $this->service->resolveDateRange($filters);
        $filename = 'ecom-tracker-dashboard-'.$range['from']->format('Y-m-d').'-'.$range['to']->format('Y-m-d').'.xlsx';

        return Excel::download(
            EcomTrackerDashboardExport::fromFilters($this->service, $filters),
            $filename,
        );
    }
}
