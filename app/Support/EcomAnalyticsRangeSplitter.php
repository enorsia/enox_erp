<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Dashboard ranges always read live commerce tables (filled by action sync).
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
