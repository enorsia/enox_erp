<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Models\TrackingDailyDuration;
use App\Support\TrackerDatabase;

class SessionDurationSectionService
{
    public function forRange(string $from, string $to): array
    {
        $counts = TrackerDatabase::table('tracking_daily_durations')
            ->whereBetween('metric_date', [$from, $to])
            ->groupBy('bucket')
            ->selectRaw('bucket, SUM(sessions) as sessions')
            ->pluck('sessions', 'bucket');

        $total = (int) $counts->sum();
        $buckets = [];
        foreach (TrackingDailyDuration::BUCKETS as $bucket => $definition) {
            $count = (int) ($counts[$bucket] ?? 0);
            $buckets[] = [
                'key' => $bucket,
                'label' => $definition['label'],
                'count' => $count,
                'pct' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }

        $middle = $total === 0 ? collect() : TrackerDatabase::table('tracking_daily_visitor as dv')
            ->join('tracking_session as ts', 'ts.tracking_daily_visitor_id', '=', 'dv.id')
            ->whereBetween('dv.visit_date', [$from, $to])
            ->orderBy('ts.duration_seconds')
            ->offset(intdiv($total - 1, 2))
            ->limit($total % 2 === 0 ? 2 : 1)
            ->pluck('ts.duration_seconds');

        return [
            'buckets' => $buckets,
            'total_sessions' => $total,
            'median_label' => format_duration((int) round((float) $middle->avg())),
        ];
    }

}
