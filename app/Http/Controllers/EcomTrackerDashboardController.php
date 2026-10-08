<?php

namespace App\Http\Controllers;

use App\Jobs\TrackerDashboardSyncJob;
use App\Services\TrackerDashboardSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardController extends Controller
{
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
