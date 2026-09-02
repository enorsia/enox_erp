<?php

namespace App\Support;

use App\Support\CommerceHasOrderFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Period-scoped funnel reads from the normalized commerce tables.
 *
 * Read activity_ecom_user flags, activity_ecom_commerce_line_items, and
 * activity_ecom_orders so dashboard queries do not scan activity_ecom_user_actions.
 */
final class CommerceFunnelQuery
{
    private const SESSION_ID_CHUNK = 1000;

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    public static function abandonedRows(
        Carbon $from,
        Carbon $to,
        string $stage,
        string $excludeActionType,
        ?Collection $allowedSessionIds,
        ?string $period = null,
    ): array {
        if ($allowedSessionIds !== null && $allowedSessionIds->isEmpty()) {
            return [];
        }

        $excludeStage = self::normalizeStage($excludeActionType);
        $stage = self::normalizeStage($stage);

        $flagged = self::flaggedAbandonedSessionIds($from, $to, $stage, $excludeStage, $allowedSessionIds, $period);
        if ($flagged->isNotEmpty()) {
            return self::hydrateAbandonedRows($flagged, $from, $to, $stage, $excludeStage);
        }

        $fromLines = self::lineItemStageSessionIds($from, $to, $stage, $allowedSessionIds, $period);
        if ($fromLines->isNotEmpty()) {
            return self::hydrateAbandonedRows($fromLines, $from, $to, $stage, $excludeStage);
        }

        return [];
    }

    /**
     * Same abandoned-row rules as abandonedRows(), using already-loaded session
     * flags and line items so the dashboard does not query those tables again.
     *
     * @param  Collection<string, object>  $sessions  keyed by session_id
     * @param  Collection<int, object>  $lines
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    public static function abandonedRowsFromLoadedData(
        Collection $sessions,
        Collection $lines,
        string $stage,
        string $excludeActionType,
    ): array {
        $excludeStage = self::normalizeStage($excludeActionType);
        $stage = self::normalizeStage($stage);
        $flag = self::stageFlag($stage);
        $excludeFlag = self::stageFlag($excludeStage);

        $flagged = collect();
        if ($flag !== null) {
            $flagged = $sessions
                ->filter(function (object $session) use ($flag, $excludeFlag) {
                    if (! (bool) ($session->{$flag} ?? false)) {
                        return false;
                    }

                    return $excludeFlag === null || ! (bool) ($session->{$excludeFlag} ?? false);
                })
                ->keys()
                ->map(fn ($id) => (string) $id)
                ->values();
        }

        $candidates = $flagged;
        if ($candidates->isEmpty()) {
            $candidates = $lines
                ->filter(fn (object $line) => (string) ($line->funnel_stage ?? '') === $stage)
                ->pluck('session_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        $excluded = [];
        foreach ($lines as $line) {
            if ((string) ($line->funnel_stage ?? '') !== $excludeStage) {
                continue;
            }

            $excluded[(string) $line->session_id] = true;
        }

        $kept = $candidates->reject(fn (string $id) => isset($excluded[$id]))->values();
        if ($kept->isEmpty()) {
            return [];
        }

        $keptSet = array_fill_keys($kept->all(), true);
        $latestIdBySession = [];
        $eventByMaxId = [];

        foreach ($lines as $line) {
            $sessionId = (string) ($line->session_id ?? '');
            if (! isset($keptSet[$sessionId]) || (string) ($line->funnel_stage ?? '') !== $stage) {
                continue;
            }

            $lineId = (int) ($line->id ?? 0);
            if ($lineId > ($latestIdBySession[$sessionId] ?? 0)) {
                $latestIdBySession[$sessionId] = $lineId;
                $eventByMaxId[$sessionId] = (string) ($line->event_id ?? '');
            }
        }

        $qtyBySession = [];
        $valueBySession = [];
        $occurredBySession = [];

        foreach ($lines as $line) {
            $sessionId = (string) ($line->session_id ?? '');
            $eventId = (string) ($line->event_id ?? '');
            if (($eventByMaxId[$sessionId] ?? null) !== $eventId || (string) ($line->funnel_stage ?? '') !== $stage) {
                continue;
            }

            $qtyBySession[$sessionId] = ($qtyBySession[$sessionId] ?? 0) + (int) round((float) ($line->qty ?? 0));
            $valueBySession[$sessionId] = ($valueBySession[$sessionId] ?? 0) + (float) ($line->line_total ?? 0);

            $stagedAt = $line->staged_at ?? null;
            if ($stagedAt !== null && (string) ($occurredBySession[$sessionId] ?? '') < (string) $stagedAt) {
                $occurredBySession[$sessionId] = $stagedAt;
            }
        }

        $rows = [];
        foreach ($kept as $sessionId) {
            $rows[] = [
                'session_id' => $sessionId,
                'qty' => (int) ($qtyBySession[$sessionId] ?? 0),
                'value' => round((float) ($valueBySession[$sessionId] ?? 0), 2),
                'occurred_at' => $occurredBySession[$sessionId] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    public static function paymentRows(
        Carbon $from,
        Carbon $to,
        ?Collection $allowedSessionIds,
        ?string $period = null,
    ): array {
        if ($allowedSessionIds !== null && $allowedSessionIds->isEmpty()) {
            return [];
        }

        $range = TrackerTime::storageRange($from, $to);
        $query = DB::table('activity_ecom_orders')
            ->select('session_id', 'ordered_at', 'amount_paid', 'item_qty', 'order_id', 'event_id')
            ->whereBetween('ordered_at', $range)
            ->orderByDesc('ordered_at');

        self::applySessionScope($query, $allowedSessionIds, $from, $to, $period);

        $rows = [];
        $seen = [];

        foreach ($query->get() as $order) {
            $orderId = trim((string) ($order->order_id ?? ''));
            $dedupeKey = $orderId !== '' ? $orderId : (string) ($order->event_id ?? '');

            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }

            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }

            $rows[] = [
                'session_id' => (string) $order->session_id,
                'qty' => max(1, (int) ($order->item_qty ?? 0)),
                'value' => round((float) ($order->amount_paid ?? 0), 2),
                'occurred_at' => $order->ordered_at,
            ];
        }

        return $rows;
    }

    /**
     * Same payment-row rules as paymentRows(), using already-loaded orders.
     *
     * @param  Collection<int, object>  $orders
     * @param  Collection<string, object>|null  $sessions  keyed by session_id; when set, orders outside the session set are skipped
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    public static function paymentRowsFromLoadedData(Collection $orders, ?Collection $sessions = null): array
    {
        $sorted = $orders
            ->sortByDesc(fn (object $order) => (string) ($order->ordered_at ?? ''))
            ->values();

        $rows = [];
        $seen = [];

        foreach ($sorted as $order) {
            $sessionId = (string) ($order->session_id ?? '');
            if ($sessions !== null && ! $sessions->has($sessionId)) {
                continue;
            }

            $orderId = trim((string) ($order->order_id ?? ''));
            $dedupeKey = $orderId !== '' ? $orderId : (string) ($order->event_id ?? '');

            if ($dedupeKey !== '' && isset($seen[$dedupeKey])) {
                continue;
            }

            if ($dedupeKey !== '') {
                $seen[$dedupeKey] = true;
            }

            $rows[] = [
                'session_id' => $sessionId,
                'qty' => max(1, (int) ($order->item_qty ?? 0)),
                'value' => round((float) ($order->amount_paid ?? 0), 2),
                'occurred_at' => $order->ordered_at ?? null,
            ];
        }

        return $rows;
    }

    public static function stageFlag(string $stage): ?string
    {
        return match (self::normalizeStage($stage)) {
            'add_to_cart' => 'has_add_to_cart',
            'begin_checkout' => 'has_begin_checkout',
            'proceed_checkout' => 'has_proceed_checkout',
            'payment_success' => 'has_payment_success',
            default => null,
        };
    }

    /**
     * Activity-list funnel drawer / focus filter using session flags (no giant whereIn).
     *
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applySidebarFunnelKey(
        Builder $query,
        string $funnelKey,
        Carbon $from,
        Carbon $to,
    ): void {
        match ($funnelKey) {
            'cart_abandonment' => self::applyAbandonedSessionFilter($query, 'add_to_cart', 'begin_checkout'),
            'begin_checkout_abandonment' => self::applyAbandonedSessionFilter($query, 'begin_checkout', 'proceed_checkout'),
            'proceed_checkout_abandonment' => self::applyAbandonedSessionFilter($query, 'proceed_checkout', 'payment_success'),
            'payment_success' => self::applyPaymentSuccessSessionFilter($query, $from, $to),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyAbandonedSessionFilter(
        Builder $query,
        string $stage,
        string $excludeActionType,
    ): void {
        $stage = self::normalizeStage($stage);
        $excludeActionType = self::normalizeStage($excludeActionType);
        $flag = self::stageFlag($stage);
        $excludeFlag = self::stageFlag($excludeActionType);

        if ($flag === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();
        $query->where("{$table}.{$flag}", true);

        if ($excludeFlag !== null) {
            $query->where("{$table}.{$excludeFlag}", false);
        }
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyPaymentSuccessSessionFilter(
        Builder $query,
        Carbon $from,
        Carbon $to,
    ): void {
        CommerceHasOrderFilter::apply($query, true, $from, $to);
    }

    public static function normalizeStage(string $stage): string
    {
        return match ($stage) {
            'proceed_to_checkout' => 'proceed_checkout',
            default => $stage,
        };
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return Collection<int, string>
     */
    private static function flaggedAbandonedSessionIds(
        Carbon $from,
        Carbon $to,
        string $stage,
        string $excludeStage,
        ?Collection $allowedSessionIds,
        ?string $period,
    ): Collection {
        $flag = self::stageFlag($stage);
        $excludeFlag = self::stageFlag($excludeStage);

        if ($flag === null) {
            return collect();
        }

        $query = DB::table('activity_ecom_user')
            ->select('session_id')
            ->where($flag, true);

        if ($excludeFlag !== null) {
            $query->where($excludeFlag, false);
        }

        if ($allowedSessionIds !== null) {
            self::constrainToSessionIds($query, $allowedSessionIds);
        } else {
            TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);
        }

        return $query->pluck('session_id')->unique()->values();
    }

    /**
     * @param  Collection<int, string>|null  $allowedSessionIds
     * @return Collection<int, string>
     */
    private static function lineItemStageSessionIds(
        Carbon $from,
        Carbon $to,
        string $stage,
        ?Collection $allowedSessionIds,
        ?string $period,
    ): Collection {
        $query = DB::table('activity_ecom_commerce_line_items')
            ->select('session_id')
            ->where('funnel_stage', $stage)
            ->whereBetween('staged_at', TrackerTime::storageRange($from, $to))
            ->distinct();

        self::applySessionScope($query, $allowedSessionIds, $from, $to, $period);

        return $query->pluck('session_id')->values();
    }

    /**
     * @param  Collection<int, string>  $candidateIds
     * @return list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>
     */
    private static function hydrateAbandonedRows(
        Collection $candidateIds,
        Carbon $from,
        Carbon $to,
        string $stage,
        string $excludeStage,
    ): array {
        $excluded = CommerceLineItemQuery::sessionIdsHavingFunnelStage($candidateIds, $excludeStage, $from, $to);

        $kept = $candidateIds
            ->map(fn ($id) => (string) $id)
            ->reject(fn (string $id) => isset($excluded[$id]))
            ->values();

        if ($kept->isEmpty()) {
            return [];
        }

        $totals = self::latestLineItemTotals($kept, $stage, $from, $to);

        $rows = [];

        foreach ($kept as $sessionId) {
            $row = $totals[$sessionId] ?? ['qty' => 0, 'value' => 0.0, 'occurred_at' => null];
            $rows[] = [
                'session_id' => $sessionId,
                'qty' => (int) $row['qty'],
                'value' => round((float) $row['value'], 2),
                'occurred_at' => $row['occurred_at'],
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     * @return array<string, array{qty: int, value: float, occurred_at: mixed}>
     */
    private static function latestLineItemTotals(
        Collection $sessionIds,
        string $stage,
        Carbon $from,
        Carbon $to,
    ): array {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $range = TrackerTime::storageRange($from, $to);
        $totals = [];

        foreach ($sessionIds->chunk(self::SESSION_ID_CHUNK) as $chunk) {
            $ids = $chunk->values()->all();
            $latestIdsQuery = DB::table('activity_ecom_commerce_line_items')
                ->select('session_id', DB::raw('MAX(id) as max_id'))
                ->where('funnel_stage', $stage)
                ->whereBetween('staged_at', $range)
                ->whereIn('session_id', $ids)
                ->groupBy('session_id');

            $events = DB::table('activity_ecom_commerce_line_items as li')
                ->joinSub($latestIdsQuery, 'latest', 'li.id', '=', 'latest.max_id')
                ->select('li.session_id', 'li.event_id');

            $rows = DB::table('activity_ecom_commerce_line_items as lines')
                ->joinSub($events, 'ev', 'lines.event_id', '=', 'ev.event_id')
                ->where('lines.funnel_stage', $stage)
                ->groupBy('lines.session_id')
                ->select(
                    'lines.session_id',
                    DB::raw('COALESCE(SUM(lines.qty), 0) as qty'),
                    DB::raw('COALESCE(SUM(lines.line_total), 0) as value'),
                    DB::raw('MAX(lines.staged_at) as occurred_at'),
                )
                ->get();

            foreach ($rows as $row) {
                $totals[(string) $row->session_id] = [
                    'qty' => (int) round((float) $row->qty),
                    'value' => round((float) $row->value, 2),
                    'occurred_at' => $row->occurred_at,
                ];
            }
        }

        return $totals;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  Collection<int, string>|null  $sessionIds
     */
    private static function applySessionScope(
        $query,
        ?Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        ?string $period,
        string $column = 'session_id',
    ): void {
        if ($sessionIds !== null) {
            self::constrainToSessionIds($query, $sessionIds, $column);

            return;
        }

        $query->whereIn($column, function ($sub) use ($from, $to, $period) {
            $sub->from('activity_ecom_user')->select('session_id');
            TrackerTime::applyEcomActivitySessionScope($sub, $from, $to, $period);
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  Collection<int|string, mixed>  $sessionIds
     */
    private static function constrainToSessionIds($query, Collection $sessionIds, string $column = 'session_id'): void
    {
        $ids = $sessionIds->values()->all();

        if ($ids === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        if (count($ids) <= self::SESSION_ID_CHUNK) {
            $query->whereIn($column, $ids);

            return;
        }

        $query->where(function ($outer) use ($ids, $column) {
            foreach (array_chunk($ids, self::SESSION_ID_CHUNK) as $chunk) {
                $outer->orWhereIn($column, $chunk);
            }
        });
    }
}
