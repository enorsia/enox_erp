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

        $chunkSize = max(1, (int) config('tracker.dashboard_sync_batch_size', 25));
        $maxSessions = max(1, (int) config('tracker.dashboard_sync_max_per_run', 5000));
        $chunks = $syncService->planChunks($chunkSize, $maxSessions);

        if ($chunks === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Nothing to sync. Only pending sessions idle for 30+ minutes are included.',
            ], 422);
        }

        $batchCount = count($chunks);
        $sessionCount = array_sum(array_map('count', $chunks));

        // Run planner after the JSON response (no separate queue worker needed for this step).
        TrackerDashboardSyncJob::dispatch()->afterResponse();

        return response()->json([
            'ok' => true,
            'message' => "Sync started: {$batchCount} batch(es), up to {$sessionCount} session(s). Ensure a queue worker is running.",
            'batches' => $batchCount,
            'sessions' => $sessionCount,
        ]);
    }
}
