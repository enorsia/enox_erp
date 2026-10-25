<?php

namespace App\Services\EcomTrackerDashboard\Concerns;

use App\Services\EcomActivityListService;
use App\Support\TrackerDatabase;
use Carbon\Carbon;

trait InteractsWithSessionFilterRatios
{
    public function __construct(
        protected EcomActivityListService $activityList,
    ) {}

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array<string, float> metric_date => ratio in [0, 1]
     */
    protected function sessionFilterRatiosByDate(Carbon $from, Carbon $to, string $period, array $sessionFilters): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $totalsByDate = TrackerDatabase::table('tracking_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->pluck('sessions', 'metric_date');

        $range = ['from' => $from, 'to' => $to, 'period' => $period];

        $filteredByDate = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->selectRaw('dv.visit_date as session_date, COUNT(*) as sessions')
            ->tap(fn ($query) => $this->activityList->constrainDashboardSessionsInRange($query, $range, $sessionFilters))
            ->groupBy('dv.visit_date')
            ->pluck('sessions', 'session_date');

        $ratios = [];
        foreach ($totalsByDate as $date => $totalSessions) {
            $total = (int) $totalSessions;
            $filtered = (int) ($filteredByDate[$date] ?? 0);
            $ratios[(string) $date] = $total > 0 ? min(1.0, $filtered / $total) : 0.0;
        }

        return $ratios;
    }
}
