<?php

namespace App\Http\Controllers;

use App\Jobs\TrackerDashboardSyncJob;
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

    public function sync(Request $request): JsonResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        TrackerDashboardSyncJob::dispatch();

        return response()->json([
            'ok' => true,
            'message' => 'Sync queued.',
        ]);
    }
}
