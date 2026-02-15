<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('authentication.activity_logs.index');

        $query = Activity::with(['causer', 'subject']);

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $data['activities'] = $query->latest()->paginate(20);
        $data['start'] = ($data['activities']->currentPage() - 1) * $data['activities']->perPage() + 1;

        $data['users'] = Activity::with('causer')
            ->whereNotNull('causer_id')
            ->get()
            ->pluck('causer')
            ->unique('id')
            ->sortBy('name');

        $data['log_names'] = Activity::select('log_name')
            ->distinct()
            ->whereNotNull('log_name')
            ->pluck('log_name');

        $data['events'] = Activity::select('event')
            ->distinct()
            ->whereNotNull('event')
            ->pluck('event');

        return view('activity_logs.index', $data);
    }

    public function show($id)
    {
        Gate::authorize('authentication.activity_logs.show');

        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        return view('activity_logs.show', compact('activity'));
    }
}

