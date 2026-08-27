<?php

namespace App\Services;

use App\Models\ActivityEcomUserAction;
use App\Support\CommerceLineItemQuery;
use App\Support\CommerceReadSupport;
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

        if ($allowedSessionIds !== null && $allowedSessionIds->isEmpty()) {
            return ['session_ids' => collect(), 'rows' => []];
        }

        $candidatesQuery = DB::table('activity_ecom_user_actions')
            ->select('session_id', 'created_at', 'commerce_total', 'amount_paid', 'item_qty', 'line_count')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', $stage)
            ->orderByDesc('created_at');

        if ($allowedSessionIds !== null) {
            $this->constrainToSessionIds($candidatesQuery, $allowedSessionIds);
        } else {
            $candidatesQuery->whereIn('session_id', function ($sub) use ($from, $to, $period) {
                $sub->from('activity_ecom_user')->select('session_id');
                TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
            });
        }

        $candidates = $candidatesQuery->get()->groupBy('session_id');
        $excludeStage = match ($excludeActionType) {
            'begin_checkout' => 'begin_checkout',
            'proceed_checkout' => 'proceed_checkout',
            'payment_success' => 'payment_success',
            default => $excludeActionType,
        };
        $excludedSessionIds = CommerceLineItemQuery::sessionIdsHavingFunnelStage(
            $candidates->keys(),
            $excludeStage,
            $from,
            $to,
        );
        if ($excludedSessionIds === []) {
            $excludedSessionIds = $this->sessionIdsHavingActionType($candidates->keys(), $excludeActionType);
        }
        $rows = [];

        foreach ($candidates as $sessionId => $stageActions) {
            if (isset($excludedSessionIds[(string) $sessionId])) {
                continue;
            }

            $latest = $stageActions->first();

            $rows[] = [
                'session_id' => (string) $sessionId,
                'qty' => CommerceReadSupport::itemQtyForAction($latest),
                'value' => round((float) (CommerceReadSupport::amountForAction($latest) ?? 0), 2),
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

        if ($allowedSessionIds !== null && $allowedSessionIds->isEmpty()) {
            return ['session_ids' => collect(), 'rows' => []];
        }

        $actionsQuery = DB::table('activity_ecom_user_actions')
            ->select('event_id', 'session_id', 'created_at', 'order_id', 'amount_paid', 'commerce_total', 'item_qty', 'line_count')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->where('action_type', 'payment_success')
            ->orderByDesc('created_at');

        if ($allowedSessionIds !== null) {
            $this->constrainToSessionIds($actionsQuery, $allowedSessionIds);
        } else {
            $actionsQuery->whereIn('session_id', function ($sub) use ($from, $to, $period) {
                $sub->from('activity_ecom_user')->select('session_id');
                TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
            });
        }

        $rows = [];
        $seen = [];

        foreach ($actionsQuery->get() as $action) {
            $orderId = CommerceReadSupport::orderIdForAction($action);
            $dedupeKey = filled($orderId) ? $orderId : (string) ($action->event_id ?? '');

            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }

            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }

            $rows[] = [
                'session_id' => (string) $action->session_id,
                'qty' => CommerceReadSupport::itemQtyForAction($action),
                'value' => round((float) (CommerceReadSupport::amountForAction($action) ?? 0), 2),
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
