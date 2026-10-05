<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EcomActivityController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.activity.index');

        $data = [
            'period' => $request->input('period', '24h'),
            'dateFrom' => (string) $request->input('date_from', ''),
            'dateTo' => (string) $request->input('date_to', ''),
        ];

        $sessions = DB::table('tracking_session')
            ->orderByDesc('last_active_at')
            ->paginate(25);

        return view('ecom_activity.index', compact('data', 'sessions'));
    }

    public function show(Request $request, string $session): View
    {
        Gate::authorize('ecom_tracker.activity.show');

        $backUrl = filled($request->input('back'))
            ? (string) $request->input('back')
            : route('admin.ecom-activity.index');

        return view('ecom_activity.show', [
            'session' => $session,
            'backUrl' => $backUrl,
        ]);
    }
}
