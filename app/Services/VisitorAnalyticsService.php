<?php

namespace App\Services;

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VisitorAnalyticsService
{
    private function effectiveDurationSecondsSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'COALESCE(NULLIF(session_duration_seconds, 0), CAST((strftime(\'%s\', last_active_at) - strftime(\'%s\', created_at)) AS INTEGER), 0)';
        }

        return 'COALESCE(NULLIF(session_duration_seconds, 0), TIMESTAMPDIFF(SECOND, created_at, last_active_at), 0)';
    }

    public function resolveWindow(string $window, ?int $customValue = null): Carbon
    {
        $now = TrackerTime::localNow();

        if ($customValue !== null && $customValue > 0) {
            $from = match ($window) {
                'hours' => $now->copy()->subHours($customValue),
                'days' => $now->copy()->subDays($customValue),
                'weeks' => $now->copy()->subWeeks($customValue),
                'months' => $now->copy()->subMonths($customValue),
                'years' => $now->copy()->subYears($customValue),
                default => $now->copy()->subHours($customValue),
            };

            return $from->utc();
        }

        $from = match ($window) {
            '1h' => $now->copy()->subHour(),
            '3h' => $now->copy()->subHours(3),
            '6h' => $now->copy()->subHours(6),
            '12h' => $now->copy()->subHours(12),
            '24h' => $now->copy()->subHours(24),
            '1d' => $now->copy()->subDay(),
            '7d' => $now->copy()->subDays(7),
            '14d' => $now->copy()->subDays(14),
            '30d' => $now->copy()->subDays(30),
            '90d' => $now->copy()->subDays(90),
            '1w' => $now->copy()->subWeek(),
            '4w' => $now->copy()->subWeeks(4),
            '12w' => $now->copy()->subWeeks(12),
            '52w' => $now->copy()->subWeeks(52),
            '1m' => $now->copy()->subMonth(),
            '3m' => $now->copy()->subMonths(3),
            '6m' => $now->copy()->subMonths(6),
            '12m' => $now->copy()->subMonths(12),
            '1y' => $now->copy()->subYear(),
            default => $now->copy()->subHours(24),
        };

        return $from->utc();
    }

    private function applyLastActiveRange($query, Carbon $from, ?Carbon $until = null)
    {
        if ($until !== null) {
            return $query->whereBetween('last_active_at', [$from, $until]);
        }

        return $query->where('last_active_at', '>=', $from);
    }

    private function applyCreatedRange($query, Carbon $from, ?Carbon $until = null)
    {
        if ($until !== null) {
            return $query->whereBetween('created_at', [$from, $until]);
        }

        return $query->where('created_at', '>=', $from);
    }

    public function countActiveVisitors(Carbon $from, ?Carbon $until = null): int
    {
        return (int) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->count('visitor_id');
    }

    public function countNewVisitors(Carbon $from, ?Carbon $until = null): int
    {
        $query = ActivityEcomDailyVisitor::query();

        if ($until !== null) {
            $query->whereBetween('first_seen_at', [$from, $until]);
        } else {
            $query->where('first_seen_at', '>=', $from);
        }

        return (int) $query->count();
    }

    public function countNewVisitorsInRange(Carbon $from, Carbon $to): int
    {
        $fromDate = TrackerTime::toLocal($from)?->toDateString();
        $toDate = TrackerTime::toLocal($to)?->toDateString();

        return (int) ActivityEcomDailyVisitor::query()
            ->whereBetween('visit_date', [$fromDate, $toDate])
            ->count();
    }

    public function countSessions(Carbon $from, ?Carbon $until = null): int
    {
        return (int) $this->applyCreatedRange(ActivityEcomUser::query(), $from, $until)->count();
    }

    public function avgSessionDuration(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) round((float) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw('AVG('.$durationSql.') as aggregate')
            ->value('aggregate'));
    }

    public function avgSessionDurationInRange(Carbon $from, Carbon $to): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) round((float) ActivityEcomUser::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('visitor_id')
            ->selectRaw('AVG('.$durationSql.') as aggregate')
            ->value('aggregate'));
    }

    public function avgVisitorStay(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $totals = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->select('visitor_id', DB::raw('SUM('.$durationSql.') as total_stay'))
            ->whereNotNull('visitor_id')
            ->groupBy('visitor_id')
            ->get();

        if ($totals->isEmpty()) {
            return 0;
        }

        return (int) round($totals->avg('total_stay'));
    }

    public function totalStaySeconds(Carbon $from, ?Carbon $until = null): int
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        return (int) $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw('SUM('.$durationSql.') as aggregate')
            ->value('aggregate');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(Carbon $from, ?Carbon $until = null): array
    {
        return [
            'active_visitors' => $this->countActiveVisitors($from, $until),
            'new_visitors' => $this->countNewVisitors($from, $until),
            'sessions' => $this->countSessions($from, $until),
            'avg_session_duration' => $this->avgSessionDuration($from, $until),
            'avg_session_duration_label' => $this->formatDuration($this->avgSessionDuration($from, $until)),
            'avg_visitor_stay' => $this->avgVisitorStay($from, $until),
            'avg_visitor_stay_label' => $this->formatDuration($this->avgVisitorStay($from, $until)),
            'total_stay_seconds' => $this->totalStaySeconds($from, $until),
            'total_stay_label' => $this->formatDuration($this->totalStaySeconds($from, $until)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildRollingWindows(): array
    {
        $windows = config('tracker.analytics_windows', []);
        $cards = [];

        foreach ($windows['hours'] ?? [] as $hours) {
            $since = $this->resolveWindow('hours', $hours);
            $cards[] = array_merge([
                'key' => $hours.'h',
                'label' => 'Last '.$hours.' hour'.($hours === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['days'] ?? [] as $days) {
            $since = $this->resolveWindow('days', $days);
            $cards[] = array_merge([
                'key' => $days.'d',
                'label' => 'Last '.$days.' day'.($days === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['weeks'] ?? [] as $weeks) {
            $since = $this->resolveWindow('weeks', $weeks);
            $cards[] = array_merge([
                'key' => $weeks.'w',
                'label' => 'Last '.$weeks.' week'.($weeks === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['months'] ?? [] as $months) {
            $since = $this->resolveWindow('months', $months);
            $cards[] = array_merge([
                'key' => $months.'m',
                'label' => 'Last '.$months.' month'.($months === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        foreach ($windows['years'] ?? [] as $years) {
            $since = $this->resolveWindow('years', $years);
            $cards[] = array_merge([
                'key' => $years.'y',
                'label' => 'Last '.$years.' year'.($years === 1 ? '' : 's'),
            ], $this->buildWindowCard($since));
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildWindowCard(Carbon $since): array
    {
        $avgStay = $this->avgSessionDuration($since);

        return [
            'active_visitors' => $this->countActiveVisitors($since),
            'new_visitors' => $this->countNewVisitors($since),
            'sessions' => $this->countSessions($since),
            'avg_stay_seconds' => $avgStay,
            'avg_stay_label' => $this->formatDuration($avgStay),
            'total_stay_seconds' => $this->totalStaySeconds($since),
            'total_stay_label' => $this->formatDuration($this->totalStaySeconds($since)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOverview(Carbon $from, ?Carbon $until = null): array
    {
        $summary = $this->buildSummary($from, $until);
        $durationBuckets = $this->buildDurationBuckets($from, $until);
        $newReturning = $this->buildNewVsReturning($from, $until);
        $trend = $this->buildVisitorTrend($from, $until);
        $topVisitors = $this->buildVisitorBreakdown($from, 5, $until);

        return array_merge($summary, [
            'duration_buckets' => $durationBuckets,
            'new_returning' => $newReturning,
            'trend' => $trend,
            'top_visitors' => $topVisitors->items(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildVisitorTrend(Carbon $from, ?Carbon $until = null): array
    {
        $to = $until ?? TrackerTime::nowUtc();
        $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay();
        $toLocal = TrackerTime::toLocal($to)?->copy()->endOfDay();

        if ($fromLocal === null || $toLocal === null) {
            return ['labels' => [], 'visitors' => [], 'sessions' => []];
        }

        $labels = [];
        $visitors = [];
        $sessions = [];
        $cursor = $fromLocal->copy();

        while ($cursor <= $toLocal) {
            $dayStart = $cursor->copy()->startOfDay()->utc();
            $dayEnd = $cursor->copy()->endOfDay()->utc();
            $labels[] = $cursor->format('d M');

            $visitors[] = $this->countActiveVisitors($dayStart, $dayEnd);
            $sessions[] = $this->countSessions($dayStart, $dayEnd);

            $cursor->addDay();
        }

        return compact('labels', 'visitors', 'sessions');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildNewVsReturning(Carbon $from, ?Carbon $until = null): array
    {
        $newVisitors = $this->countNewVisitors($from, $until);

        $activeVisitorIds = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->pluck('visitor_id');

        $fromDate = TrackerTime::toLocal($from)?->toDateString();
        $returning = 0;

        if ($activeVisitorIds->isNotEmpty() && $fromDate) {
            $returning = (int) ActivityEcomDailyVisitor::query()
                ->whereIn('visitor_id', $activeVisitorIds)
                ->where('visit_date', '<', $fromDate)
                ->distinct()
                ->count('visitor_id');
        }

        return [
            'new' => $newVisitors,
            'returning' => $returning,
            'labels' => ['New', 'Returning'],
            'values' => [$newVisitors, $returning],
        ];
    }

    public function buildVisitorBreakdown(Carbon $from, int $perPage = 25, ?Carbon $until = null, array $filters = []): LengthAwarePaginator
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $query = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->select([
                'visitor_id',
                DB::raw('COUNT(*) as session_count'),
                DB::raw('SUM('.$durationSql.') as total_stay_seconds'),
                DB::raw('AVG('.$durationSql.') as avg_stay_seconds'),
                DB::raw('MIN(created_at) as first_seen_at'),
                DB::raw('MAX(last_active_at) as last_active_at'),
            ])
            ->whereNotNull('visitor_id')
            ->groupBy('visitor_id')
            ->orderByDesc(DB::raw('MAX(last_active_at)'));

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where('visitor_id', 'like', $search);
        }

        if (! empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        if (isset($filters['logged_in']) && $filters['logged_in'] !== '' && $filters['logged_in'] !== null) {
            $query->where('is_logged_in', (bool) $filters['logged_in']);
        }

        if (isset($filters['has_order']) && $filters['has_order'] !== '' && $filters['has_order'] !== null) {
            $orderSessionIds = ActivityEcomUserAction::query()
                ->where('action_type', 'payment_success')
                ->pluck('session_id');

            if ((bool) $filters['has_order']) {
                $query->whereIn('session_id', $orderSessionIds);
            } else {
                $query->whereNotIn('session_id', $orderSessionIds);
            }
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(function ($row) {
            $latest = ActivityEcomUser::query()
                ->where('visitor_id', $row->visitor_id)
                ->orderByDesc('last_active_at')
                ->first(['device_type', 'browser']);

            $totalStay = (int) $row->total_stay_seconds;
            $avgStay = (int) round((float) $row->avg_stay_seconds);

            return [
                'visitor_id' => $row->visitor_id,
                'session_count' => (int) $row->session_count,
                'total_stay_seconds' => $totalStay,
                'total_stay_label' => $this->formatDuration($totalStay),
                'avg_stay_seconds' => $avgStay,
                'avg_stay_label' => $this->formatDuration($avgStay),
                'first_seen_at' => $row->first_seen_at,
                'last_active_at' => $row->last_active_at,
                'device_type' => $latest?->device_type,
                'browser' => $latest?->browser,
            ];
        });

        return $paginator;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildDurationBuckets(Carbon $from, ?Carbon $until = null): array
    {
        $durationSql = $this->effectiveDurationSecondsSql();

        $sessions = $this->applyLastActiveRange(ActivityEcomUser::query(), $from, $until)
            ->whereNotNull('visitor_id')
            ->selectRaw($durationSql.' as duration_seconds')
            ->pluck('duration_seconds');

        $buckets = [
            ['label' => '0–1 min', 'min' => 0, 'max' => 60, 'count' => 0],
            ['label' => '1–5 min', 'min' => 61, 'max' => 300, 'count' => 0],
            ['label' => '5–15 min', 'min' => 301, 'max' => 900, 'count' => 0],
            ['label' => '15–30 min', 'min' => 901, 'max' => 1800, 'count' => 0],
            ['label' => '30+ min', 'min' => 1801, 'max' => PHP_INT_MAX, 'count' => 0],
        ];

        foreach ($sessions as $seconds) {
            $seconds = (int) $seconds;

            foreach ($buckets as &$bucket) {
                if ($seconds >= $bucket['min'] && $seconds <= $bucket['max']) {
                    $bucket['count']++;
                    break;
                }
            }
        }

        return array_map(fn (array $bucket) => [
            'label' => $bucket['label'],
            'count' => $bucket['count'],
        ], $buckets);
    }

    public function formatDuration(int $seconds): string
    {
        return format_duration($seconds);
    }
}
