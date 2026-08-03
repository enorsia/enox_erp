<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('authentication.activity_logs.index');

        $systemUserIds = User::systemUserIds();

        $query = Activity::with(['causer', 'subject']);

        if ($systemUserIds->isNotEmpty()) {
            $query->where(function ($q) use ($systemUserIds) {
                $q->where(function ($causer) use ($systemUserIds) {
                    $causer->whereNull('causer_id')
                        ->orWhere('causer_type', '!=', User::class)
                        ->orWhereNotIn('causer_id', $systemUserIds);
                })->where(function ($subject) use ($systemUserIds) {
                    $subject->whereNull('subject_id')
                        ->orWhere('subject_type', '!=', User::class)
                        ->orWhereNotIn('subject_id', $systemUserIds);
                });
            });
        }

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
            ->when($systemUserIds->isNotEmpty(), function ($q) use ($systemUserIds) {
                $q->where(function ($inner) use ($systemUserIds) {
                    $inner->where('causer_type', '!=', User::class)
                        ->orWhereNotIn('causer_id', $systemUserIds);
                });
            })
            ->get()
            ->pluck('causer')
            ->filter()
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

    public function show(int $id): View
    {
        Gate::authorize('authentication.activity_logs.show');

        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        if ($this->involvesSystemUser($activity)) {
            abort(404);
        }

        return view('activity_logs.show', compact('activity'));
    }

    private function involvesSystemUser(Activity $activity): bool
    {
        if (
            $activity->causer_type === User::class
            && $activity->causer_id
            && User::whereKey($activity->causer_id)->value('is_system')
        ) {
            return true;
        }

        if (
            $activity->subject_type === User::class
            && $activity->subject_id
            && User::whereKey($activity->subject_id)->value('is_system')
        ) {
            return true;
        }

        return false;
    }
}
