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

    /** @var list<string> */
    private const FUNNEL_STAGES = ['add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success'];

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
        $stage = self::normalizeStage($stage);
        $flag = self::stageFlag($stage);
        $laterStages = self::laterStages($stage);

        $flagged = collect();
        if ($flag !== null) {
            $flagged = $sessions
                ->filter(function (object $session) use ($flag, $laterStages) {
                    if (! (bool) ($session->{$flag} ?? false)) {
                        return false;
                    }

                    foreach ($laterStages as $laterStage) {
                        $laterFlag = self::stageFlag($laterStage);
                        if ($laterFlag !== null && (bool) ($session->{$laterFlag} ?? false)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->keys()
                ->map(fn ($id) => (string) $id)
                ->values();
        }

        $stageSessionIds = $lines
            ->filter(fn (object $line) => (string) ($line->funnel_stage ?? '') === $stage)
            ->pluck('session_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $stageSet = array_fill_keys($stageSessionIds->all(), true);
        $flagged = $flagged->filter(fn (string $id) => isset($stageSet[$id]))->values();

        $candidates = $flagged;
        if ($candidates->isEmpty()) {
            $candidates = $stageSessionIds;
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        $excluded = [];
        foreach ($lines as $line) {
            if (! in_array((string) ($line->funnel_stage ?? ''), $laterStages, true)) {
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

        $kept = $kept->filter(fn (string $id) => isset($eventByMaxId[$id]))->values();
        $latestStage = self::latestFunnelStageFromLines($lines);
        $kept = $kept->filter(fn (string $id) => ($latestStage[$id] ?? null) === $stage)->values();
        if ($kept->isEmpty()) {
            return [];
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
            'cart_abandonment' => self::applyAbandonedSessionFilter($query, 'add_to_cart', 'begin_checkout', $from, $to),
            'begin_checkout_abandonment' => self::applyAbandonedSessionFilter($query, 'begin_checkout', 'proceed_checkout', $from, $to),
            'proceed_checkout_abandonment' => self::applyAbandonedSessionFilter($query, 'proceed_checkout', 'payment_success', $from, $to),
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
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): void {
        $stage = self::normalizeStage($stage);
        $flag = self::stageFlag($stage);

        if ($flag === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();
        $query->where("{$table}.{$flag}", true);
        self::excludeLaterStageFlags($query, $stage, $table);

        if ($from instanceof Carbon && $to instanceof Carbon) {
            self::constrainToStageInPeriod($query, $table, $stage, $from, $to);
            self::constrainToLatestFunnelStageInPeriod($query, $table, $stage, $from, $to);
            self::excludeInPeriodOrders($query, $table, $from, $to);
        }
    }

    /**
     * Stages after $stage. Abandoned at $stage means none of these happened.
     *
     * @return list<string>
     */
    public static function laterStages(string $stage): array
    {
        $index = array_search(self::normalizeStage($stage), self::FUNNEL_STAGES, true);

        if ($index === false) {
            return [];
        }

        return array_values(array_slice(self::FUNNEL_STAGES, $index + 1));
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private static function excludeLaterStageFlags($query, string $stage, ?string $table = null): void
    {
        foreach (self::laterStages($stage) as $laterStage) {
            $flag = self::stageFlag($laterStage);
            if ($flag === null) {
                continue;
            }

            $query->where($table ? "{$table}.{$flag}" : $flag, false);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private static function constrainToStageInPeriod($query, string $table, string $stage, Carbon $from, Carbon $to): void
    {
        $query->whereExists(function ($exists) use ($table, $stage, $from, $to) {
            $exists->selectRaw('1')
                ->from('activity_ecom_commerce_line_items as abandon_li')
                ->whereColumn('abandon_li.session_id', "{$table}.session_id")
                ->where('abandon_li.funnel_stage', $stage)
                ->whereBetween('abandon_li.staged_at', TrackerTime::storageRange($from, $to));
        });
    }

    /**
     * Abandoned at $stage only when that stage is the last funnel event in the period.
     * Returning to cart after begin/proceed checkout is not that stage's abandonment.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private static function constrainToLatestFunnelStageInPeriod($query, string $table, string $stage, Carbon $from, Carbon $to): void
    {
        $range = TrackerTime::storageRange($from, $to);

        $query->whereNotExists(function ($exists) use ($table, $stage, $range) {
            $exists->selectRaw('1')
                ->from('activity_ecom_commerce_line_items as later_li')
                ->whereColumn('later_li.session_id', "{$table}.session_id")
                ->whereIn('later_li.funnel_stage', self::FUNNEL_STAGES)
                ->where('later_li.funnel_stage', '!=', $stage)
                ->whereBetween('later_li.staged_at', $range)
                ->whereRaw(
                    'later_li.staged_at > (
                        SELECT MAX(stage_li.staged_at)
                        FROM activity_ecom_commerce_line_items AS stage_li
                        WHERE stage_li.session_id = later_li.session_id
                          AND stage_li.funnel_stage = ?
                          AND stage_li.staged_at BETWEEN ? AND ?
                    )',
                    [$stage, $range[0], $range[1]],
                );
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    private static function excludeInPeriodOrders($query, string $table, Carbon $from, Carbon $to): void
    {
        $query->whereNotExists(function ($exists) use ($table, $from, $to) {
            $exists->selectRaw('1')
                ->from('activity_ecom_orders as abandon_ord')
                ->whereColumn('abandon_ord.session_id', "{$table}.session_id")
                ->whereBetween('abandon_ord.ordered_at', TrackerTime::storageRange($from, $to));
        });
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array<string, string>
     */
    private static function latestFunnelStageFromLines(Collection $lines): array
    {
        $latestAt = [];
        $latestRank = [];
        $latestId = [];
        $latestStage = [];

        foreach ($lines as $line) {
            $funnelStage = self::normalizeStage((string) ($line->funnel_stage ?? ''));
            $rank = array_search($funnelStage, self::FUNNEL_STAGES, true);
            if ($rank === false) {
                continue;
            }

            $sessionId = (string) ($line->session_id ?? '');
            $stagedAt = (string) ($line->staged_at ?? '');
            $lineId = (int) ($line->id ?? 0);
            $previousAt = $latestAt[$sessionId] ?? '';
            $previousRank = $latestRank[$sessionId] ?? -1;
            $previousId = $latestId[$sessionId] ?? 0;
            $isNewer = $stagedAt > $previousAt
                || ($stagedAt === $previousAt && $rank > $previousRank)
                || ($stagedAt === $previousAt && $rank === $previousRank && $lineId > $previousId);

            if ($isNewer) {
                $latestAt[$sessionId] = $stagedAt;
                $latestRank[$sessionId] = $rank;
                $latestId[$sessionId] = $lineId;
                $latestStage[$sessionId] = $funnelStage;
            }
        }

        return $latestStage;
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

        if ($flag === null) {
            return collect();
        }

        $query = DB::table('activity_ecom_user')
            ->select('session_id')
            ->where($flag, true);

        self::excludeLaterStageFlags($query, $stage);
        self::constrainToStageInPeriod($query, 'activity_ecom_user', $stage, $from, $to);
        self::constrainToLatestFunnelStageInPeriod($query, 'activity_ecom_user', $stage, $from, $to);
        self::excludeInPeriodOrders($query, 'activity_ecom_user', $from, $to);

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
        $excluded = [];
        foreach (self::laterStages($stage) as $laterStage) {
            $excluded += CommerceLineItemQuery::sessionIdsHavingFunnelStage($candidateIds, $laterStage, $from, $to);
        }

        $kept = $candidateIds
            ->map(fn ($id) => (string) $id)
            ->reject(fn (string $id) => isset($excluded[$id]))
            ->values();

        if ($kept->isEmpty()) {
            return [];
        }

        $latestStage = self::latestFunnelStageBySession($kept, $from, $to);
        $kept = $kept->filter(fn (string $id) => ($latestStage[$id] ?? null) === $stage)->values();

        if ($kept->isEmpty()) {
            return [];
        }

        $paid = [];
        foreach ($kept->chunk(self::SESSION_ID_CHUNK) as $chunk) {
            foreach (DB::table('activity_ecom_orders')
                ->whereIn('session_id', $chunk->values()->all())
                ->whereBetween('ordered_at', TrackerTime::storageRange($from, $to))
                ->pluck('session_id') as $sessionId
            ) {
                $paid[(string) $sessionId] = true;
            }
        }

        $kept = $kept->reject(fn (string $id) => isset($paid[$id]))->values();

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
     * @param  Collection<int, string>  $sessionIds
     * @return array<string, string>
     */
    private static function latestFunnelStageBySession(Collection $sessionIds, Carbon $from, Carbon $to): array
    {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $range = TrackerTime::storageRange($from, $to);
        $lines = collect();

        foreach ($sessionIds->chunk(self::SESSION_ID_CHUNK) as $chunk) {
            $lines = $lines->concat(
                DB::table('activity_ecom_commerce_line_items')
                    ->select('session_id', 'funnel_stage', 'staged_at', 'id')
                    ->whereIn('session_id', $chunk->values()->all())
                    ->whereIn('funnel_stage', self::FUNNEL_STAGES)
                    ->whereBetween('staged_at', $range)
                    ->get(),
            );
        }

        return self::latestFunnelStageFromLines($lines);
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
