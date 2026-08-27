<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared funnel session resolution for dashboard recoverable panels and activity drill-down.
 */
class EcomActivityFunnelSessions
{
    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
    ) {}

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{session_ids: Collection<int, string>, rows: array<int, array{session_id: string, qty: int, value: float, occurred_at: mixed}>}
     */
    public function abandonedSessions(
        Carbon $from,
        Carbon $to,
        string $stage,
        string $payloadKey,
        string $excludeActionType,
        array $sessionFilters = [],
        ?string $period = null,
    ): array {
        $allowedSessionIds = $this->dashboardService->activitySessionIds($from, $to, $sessionFilters, $period);

        $rows = CommerceFunnelQuery::abandonedRows(
            $from,
            $to,
            $stage,
            $excludeActionType,
            $allowedSessionIds,
            $period,
        );

        usort($rows, fn (array $a, array $b) => $b['value'] <=> $a['value']);

        return [
            'session_ids' => collect($rows)->pluck('session_id'),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $sessionFilters
     * @return array{session_ids: Collection<int, string>, rows: array<int, array{session_id: string, qty: int, value: float, occurred_at: mixed}>}
     */
    public function paymentSuccessSessions(
        Carbon $from,
        Carbon $to,
        array $sessionFilters = [],
        ?string $period = null,
    ): array {
        $allowedSessionIds = $this->dashboardService->activitySessionIds($from, $to, $sessionFilters, $period);

        $rows = CommerceFunnelQuery::paymentRows($from, $to, $allowedSessionIds, $period);

        usort($rows, fn (array $a, array $b) => $b['value'] <=> $a['value']);

        return [
            'session_ids' => collect($rows)->pluck('session_id'),
            'rows' => $rows,
        ];
    }
}
