<?php

namespace App\Services;

use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared funnel session resolution for dashboard recoverable panels and activity drill-down.
 */
class EcomActivityFunnelSessions
{
    private const SESSION_ID_CHUNK = 1000;

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

        $candidatesQuery = ActivityEcomUserAction::query()
            ->select('session_id', 'created_at', $payloadKey)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', $stage)
            ->orderByDesc('created_at');

        if ($allowedSessionIds->isEmpty()) {
            return ['session_ids' => collect(), 'rows' => []];
        }

        $this->constrainToSessionIds($candidatesQuery, $allowedSessionIds);

        $candidates = $candidatesQuery->get()->groupBy('session_id');
        $excludedSessionIds = $this->sessionIdsHavingActionType($candidates->keys(), $excludeActionType);
        $rows = [];

        foreach ($candidates as $sessionId => $stageActions) {
            if (isset($excludedSessionIds[(string) $sessionId])) {
                continue;
            }

            $latest = $stageActions->first();
            $payload = is_array($latest->{$payloadKey} ?? null) ? $latest->{$payloadKey} : [];

            $rows[] = [
                'session_id' => (string) $sessionId,
                'qty' => $this->resolvePayloadEventQty($payload),
                'value' => round((float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0), 2),
                'occurred_at' => $latest->created_at,
            ];
        }

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

        $actionsQuery = ActivityEcomUserAction::query()
            ->select('event_id', 'session_id', 'created_at', 'payment_success')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->orderByDesc('created_at');

        if ($allowedSessionIds->isEmpty()) {
            return ['session_ids' => collect(), 'rows' => []];
        }

        $this->constrainToSessionIds($actionsQuery, $allowedSessionIds);

        $rows = [];
        $seen = [];

        foreach ($actionsQuery->get() as $action) {
            $payload = is_array($action->payment_success) ? $action->payment_success : [];
            $orderId = $payload['order_id'] ?? null;
            $dedupeKey = filled($orderId) ? (string) $orderId : (string) ($action->event_id ?? '');

            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }

            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }

            $rows[] = [
                'session_id' => (string) $action->session_id,
                'qty' => $this->paymentActionItemQty($action),
                'value' => round((float) ($payload['amount_paid'] ?? 0), 2),
                'occurred_at' => $action->created_at,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['value'] <=> $a['value']);

        return [
            'session_ids' => collect($rows)->pluck('session_id'),
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePayloadEventQty(array $payload): int
    {
        if (isset($payload['items']) && is_array($payload['items'])) {
            return max(0, (int) collect($payload['items'])->sum(fn ($item) => (int) ($item['qty'] ?? $item['quantity'] ?? 1)));
        }

        return max(0, (int) ($payload['qty'] ?? $payload['quantity'] ?? 1));
    }

    private function paymentActionItemQty(ActivityEcomUserAction $action): int
    {
        $payload = is_array($action->payment_success) ? $action->payment_success : [];

        return $this->resolvePayloadEventQty($payload);
    }

    /**
     * @param  Collection<int|string, mixed>  $sessionIds
     * @return array<string, true>
     */
    private function sessionIdsHavingActionType(Collection $sessionIds, string $actionType): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $found = [];

        foreach ($sessionIds->chunk(self::SESSION_ID_CHUNK) as $chunk) {
            $ids = ActivityEcomUserAction::query()
                ->select('session_id')
                ->whereIn('session_id', $chunk->values()->all())
                ->where('action_type', $actionType)
                ->distinct()
                ->pluck('session_id');

            foreach ($ids as $id) {
                $found[(string) $id] = true;
            }
        }

        return $found;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  Collection<int, string>  $sessionIds
     */
    private function constrainToSessionIds($query, Collection $sessionIds): void
    {
        $ids = $sessionIds->values()->all();

        if (count($ids) <= self::SESSION_ID_CHUNK) {
            $query->whereIn('session_id', $ids);

            return;
        }

        $query->where(function ($inner) use ($ids) {
            foreach (array_chunk($ids, self::SESSION_ID_CHUNK) as $chunk) {
                $inner->orWhereIn('session_id', $chunk);
            }
        });
    }
}
