<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
use App\Support\EcomDailyDimensionType;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only comparison of daily site rollups vs order revenue on ordered_at.
 */
class TrackerRollupReconcileService
{
    /**
     * @return list<array{
     *     metric_date: string,
     *     rollup_revenue: float|null,
     *     rollup_order_count: int|null,
     *     orders_revenue: float,
     *     orders_count: int,
     *     rollup_source_revenue: float,
     *     rollup_source_purchases: int,
     *     revenue_delta: float|null,
     *     order_count_delta: int|null,
     *     within_tolerance: bool,
     *     rollup_row_missing: bool,
     *     orders_by_currency: array<string, array{revenue: float, count: int}>,
     *     rollup_by_currency: array<string, array{revenue: float, count: int}>,
     *     currency_mismatches: list<string>,
     *     bot_sessions_with_paid_orders: int
     * }>
     */
    public function compareRange(string $fromDate, string $toDate, float $tolerance = 0.01): array
    {
        $timezone = TrackerTime::timezone();
        $from = Carbon::parse($fromDate, $timezone)->startOfDay();
        $to = Carbon::parse($toDate, $timezone)->startOfDay();

        if ($from->gt($to)) {
            return [];
        }

        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $rows[] = $this->compareDay($cursor->toDateString(), $tolerance);
            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @return array{
     *     metric_date: string,
     *     rollup_revenue: float|null,
     *     rollup_order_count: int|null,
     *     orders_revenue: float,
     *     orders_count: int,
     *     rollup_source_revenue: float,
     *     rollup_source_purchases: int,
     *     revenue_delta: float|null,
     *     order_count_delta: int|null,
     *     within_tolerance: bool,
     *     rollup_row_missing: bool,
     *     orders_by_currency: array<string, array{revenue: float, count: int}>,
     *     rollup_by_currency: array<string, array{revenue: float, count: int}>,
     *     currency_mismatches: list<string>,
     *     bot_sessions_with_paid_orders: int
     * }
     */
    public function compareDay(string $metricDate, float $tolerance = 0.01): array
    {
        [$fromUtc, $toUtc] = TrackerTime::localCalendarDateStorageRange($metricDate);
        $from = Carbon::parse($fromUtc, 'UTC');
        $to = Carbon::parse($toUtc, 'UTC');

        $rollup = DB::table('activity_ecom_daily_site_metrics')
            ->where('metric_date', $metricDate)
            ->first();

        $rollupMissing = $rollup === null;
        $rollupRevenue = $rollupMissing ? null : round((float) ($rollup->revenue_total ?? 0), 2);
        $rollupOrderCount = $rollupMissing
            ? null
            : (int) ($rollup->order_count ?? $rollup->payment_success_count ?? 0);

        $ordersByCurrency = $this->ordersTotalsOnOrderedAt($metricDate);
        $ordersRevenue = round((float) collect($ordersByCurrency)->sum(fn (array $row) => $row['revenue']), 2);
        $ordersCount = (int) collect($ordersByCurrency)->sum(fn (array $row) => $row['count']);

        $rollupSource = CommerceFunnelQuery::paymentMetricTotals($from, $to, null, 'custom');
        $rollupByCurrency = $this->rollupTotalsByCurrency($metricDate);
        $currencyMismatches = $this->currencyMismatches($ordersByCurrency, $rollupByCurrency, $tolerance);
        $botSessionsWithPaidOrders = $this->botSessionsWithPaidOrdersOnOrderedAt($metricDate);

        if ($rollupMissing) {
            return [
                'metric_date' => $metricDate,
                'rollup_revenue' => null,
                'rollup_order_count' => null,
                'orders_revenue' => $ordersRevenue,
                'orders_count' => $ordersCount,
                'rollup_source_revenue' => round((float) $rollupSource['revenue'], 2),
                'rollup_source_purchases' => (int) $rollupSource['purchases'],
                'revenue_delta' => null,
                'order_count_delta' => null,
                'within_tolerance' => $ordersCount === 0 && $currencyMismatches === [],
                'rollup_row_missing' => true,
                'orders_by_currency' => $ordersByCurrency,
                'rollup_by_currency' => $rollupByCurrency,
                'currency_mismatches' => $currencyMismatches,
                'bot_sessions_with_paid_orders' => $botSessionsWithPaidOrders,
            ];
        }

        $revenueDelta = round($rollupRevenue - $ordersRevenue, 2);
        $countDelta = $rollupOrderCount - $ordersCount;
        $within = abs($revenueDelta) <= $tolerance
            && $countDelta === 0
            && $currencyMismatches === [];

        return [
            'metric_date' => $metricDate,
            'rollup_revenue' => $rollupRevenue,
            'rollup_order_count' => $rollupOrderCount,
            'orders_revenue' => $ordersRevenue,
            'orders_count' => $ordersCount,
            'rollup_source_revenue' => round((float) $rollupSource['revenue'], 2),
            'rollup_source_purchases' => (int) $rollupSource['purchases'],
            'revenue_delta' => $revenueDelta,
            'order_count_delta' => $countDelta,
            'within_tolerance' => $within,
            'rollup_row_missing' => false,
            'orders_by_currency' => $ordersByCurrency,
            'rollup_by_currency' => $rollupByCurrency,
            'currency_mismatches' => $currencyMismatches,
            'bot_sessions_with_paid_orders' => $botSessionsWithPaidOrders,
        ];
    }

    /**
     * @return array<string, array{revenue: float, count: int}>
     */
    private function rollupTotalsByCurrency(string $metricDate): array
    {
        $rows = DB::table('activity_ecom_daily_dimension_metrics')
            ->where('metric_date', $metricDate)
            ->where('dimension_type', EcomDailyDimensionType::CURRENCY)
            ->get();

        $byCurrency = [];
        foreach ($rows as $row) {
            $currency = (string) ($row->dimension_value ?? '(none)');
            $byCurrency[$currency] = [
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'count' => (int) ($row->payment_count ?? 0),
            ];
        }

        return $byCurrency;
    }

    /**
     * @param  array<string, array{revenue: float, count: int}>  $ordersByCurrency
     * @param  array<string, array{revenue: float, count: int}>  $rollupByCurrency
     * @return list<string>
     */
    private function currencyMismatches(array $ordersByCurrency, array $rollupByCurrency, float $tolerance): array
    {
        $currencies = array_unique(array_merge(array_keys($ordersByCurrency), array_keys($rollupByCurrency)));
        sort($currencies);
        $mismatches = [];

        foreach ($currencies as $currency) {
            $orders = $ordersByCurrency[$currency] ?? ['revenue' => 0.0, 'count' => 0];
            $rollup = $rollupByCurrency[$currency] ?? ['revenue' => 0.0, 'count' => 0];

            if (abs($rollup['revenue'] - $orders['revenue']) > $tolerance || $rollup['count'] !== $orders['count']) {
                $mismatches[] = $currency;
            }
        }

        return $mismatches;
    }

    private function botSessionsWithPaidOrdersOnOrderedAt(string $metricDate): int
    {
        if (! Schema::hasTable('activity_ecom_user_bot_context')) {
            return 0;
        }

        [$start, $end] = TrackerTime::localCalendarDateStorageRange($metricDate);

        return (int) DB::table('activity_ecom_orders as o')
            ->join('activity_ecom_user_bot_context as bc', 'bc.session_id', '=', 'o.session_id')
            ->where('bc.is_bot', true)
            ->whereBetween('o.ordered_at', [$start, $end])
            ->distinct('o.session_id')
            ->count('o.session_id');
    }

    /**
     * @return array<string, array{revenue: float, count: int}>
     */
    private function ordersTotalsOnOrderedAt(string $metricDate): array
    {
        [$start, $end] = TrackerTime::localCalendarDateStorageRange($metricDate);

        $rows = DB::table('activity_ecom_orders')
            ->selectRaw("COALESCE(NULLIF(currency, ''), '(none)') as currency_label, SUM(amount_paid) as revenue, COUNT(*) as order_count")
            ->whereBetween('ordered_at', [$start, $end])
            ->groupBy('currency_label')
            ->get();

        $byCurrency = [];
        foreach ($rows as $row) {
            $currency = (string) ($row->currency_label ?? '(none)');
            $byCurrency[$currency] = [
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'count' => (int) ($row->order_count ?? 0),
            ];
        }

        return $byCurrency;
    }
}
