<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Splits dashboard/activity calendar ranges into closed days (rollup tables) vs live (raw tables, usually today).
 */
final class EcomAnalyticsRangeSplitter
{
    /**
     * @return array{
     *     use_rollups: bool,
     *     closed_from: ?Carbon,
     *     closed_to: ?Carbon,
     *     closed_dates: list<string>,
     *     live_from: ?Carbon,
     *     live_to: ?Carbon
     * }
     */
    public static function split(Carbon $from, Carbon $to, ?string $period): array
    {
        if (! config('tracker.use_daily_rollups', true)) {
            return self::liveOnly($from, $to);
        }

        if ($period === '24h') {
            return self::liveOnly($from, $to);
        }

        $today = TrackerTime::localNow()->startOfDay();
        $fromLocal = TrackerTime::toLocal($from)?->copy()->startOfDay() ?? $from->copy()->startOfDay();
        $toLocal = TrackerTime::toLocal($to)?->copy()->startOfDay() ?? $to->copy()->startOfDay();

        if ($toLocal->lt($today)) {
            return [
                'use_rollups' => true,
                'closed_from' => $fromLocal,
                'closed_to' => $toLocal,
                'closed_dates' => self::dateStringsBetween($fromLocal, $toLocal),
                'live_from' => null,
                'live_to' => null,
            ];
        }

        $closedTo = $today->copy()->subDay();

        if ($fromLocal->gt($closedTo)) {
            return self::liveOnly($from, $to);
        }

        $liveFromUtc = $today->copy()->startOfDay()->utc();
        $liveToUtc = TrackerTime::toUtc($to) ?? $to->copy()->utc();

        return [
            'use_rollups' => true,
            'closed_from' => $fromLocal,
            'closed_to' => $closedTo,
            'closed_dates' => self::dateStringsBetween($fromLocal, $closedTo),
            'live_from' => $liveFromUtc,
            'live_to' => $liveToUtc,
        ];
    }

    /**
     * @return list<string>
     */
    public static function dateStringsBetween(Carbon $fromLocal, Carbon $toLocal): array
    {
        $dates = [];
        $cursor = $fromLocal->copy();

        while ($cursor->lte($toLocal)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array{
     *     use_rollups: bool,
     *     closed_from: ?Carbon,
     *     closed_to: ?Carbon,
     *     closed_dates: list<string>,
     *     live_from: ?Carbon,
     *     live_to: ?Carbon
     * }
     */
    private static function liveOnly(Carbon $from, Carbon $to): array
    {
        return [
            'use_rollups' => false,
            'closed_from' => null,
            'closed_to' => null,
            'closed_dates' => [],
            'live_from' => $from,
            'live_to' => $to,
        ];
    }
}
