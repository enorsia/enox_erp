<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
use App\Support\CommerceRollupSessionScope;
use App\Support\EcomDailyDimensionType;
use App\Support\EcomDailyRollupDayStatus;
use App\Support\EcomDailyRollupSchema;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills existing activity_ecom_daily_* tables from raw data (sessions/orders unchanged).
 */
class EcomDailyRollupService
{
    public function __construct(
        private EcomTrafficSourceAggregator $trafficAggregator,
    ) {}

    public function rollupDateWithStatus(string $metricDate): void
    {
        EcomDailyRollupDayStatus::markAttempt($metricDate);

        try {
            $this->rollupDate($metricDate);
            EcomDailyRollupDayStatus::markSuccess($metricDate);
        } catch (\Throwable $exception) {
            EcomDailyRollupDayStatus::markFailed($metricDate, $exception->getMessage());

            throw $exception;
        }
    }

    public function rollupDate(string $metricDate): void
    {
        $day = Carbon::parse($metricDate, TrackerTime::timezone())->startOfDay();
        $bounds = TrackerTime::localCalendarDateStorageRange($metricDate);
        $from = Carbon::parse($bounds[0], 'UTC');
        $to = Carbon::parse($bounds[1], 'UTC');

        $this->rollupSiteMetrics($day, $from, $to);
        $this->rollupDailyVisitors($day, $from, $to);
        $this->rollupVisitorMetrics($day, $from, $to);
        $this->rollupTrafficDimensionMetrics($day, $from, $to);
        $this->rollupCurrencyDimensionMetrics($day, $from, $to);
        if (EcomDailyRollupSchema::hasCommerceViewColumns()) {
            $this->rollupProductMetrics($day, $from, $to);
            $this->rollupCategoryMetrics($day, $from, $to);
        }
        $this->rollupSessionDimensionMetrics($day, $from, $to);
    }

    private function rollupSiteMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        $sessions = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($sessions, 's');
        $sessions = $sessions->selectRaw(
                'COUNT(*) as session_count,
                 COUNT(DISTINCT s.visitor_id) as visitor_count,
                 COALESCE(SUM(s.has_add_to_cart), 0) as add_to_cart,
                 COALESCE(SUM(s.has_begin_checkout), 0) as begin_checkout,
                 COALESCE(SUM(s.has_proceed_checkout), 0) as proceed_checkout,
                 COALESCE(SUM(s.has_payment_success), 0) as payment_success',
            )
            ->first();

        $actionCountQuery = DB::table('activity_ecom_user_actions as a')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'a.session_id')
            ->whereBetween('a.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($actionCountQuery, 's');
        $actionCount = (int) $actionCountQuery->count();

        $orderTotals = CommerceFunnelQuery::paymentMetricTotals($from, $to, null, 'custom');

        $sitePayload = [
                'session_count' => (int) ($sessions->session_count ?? 0),
                'visitor_count' => (int) ($sessions->visitor_count ?? 0),
                'action_count' => $actionCount,
                'add_to_cart_count' => (int) ($sessions->add_to_cart ?? 0),
                'begin_checkout_count' => (int) ($sessions->begin_checkout ?? 0),
                'proceed_checkout_count' => (int) ($sessions->proceed_checkout ?? 0),
                'payment_success_count' => (int) $orderTotals['purchases'],
                'order_count' => $orderTotals['purchases'],
                'revenue_total' => $orderTotals['revenue'],
                'items_sold_qty' => $orderTotals['item_qty'],
                'updated_at' => now(),
                'created_at' => now(),
        ];

        if (EcomDailyRollupSchema::hasCommerceViewColumns()) {
            $viewStats = DB::table('activity_ecom_commerce_line_items as li')
                ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
                ->whereBetween('li.staged_at', $bounds)
                ->whereBetween('s.created_at', $bounds);
            CommerceRollupSessionScope::applyHumanSessionFilter($viewStats, 's');
            $viewStats = $viewStats->selectRaw(
                    "SUM(CASE WHEN li.funnel_stage = 'category_view' THEN 1 ELSE 0 END) as category_views,
                     SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as product_views",
                )
                ->first();
            $sitePayload['category_view_count'] = (int) ($viewStats->category_views ?? 0);
            $sitePayload['product_view_count'] = (int) ($viewStats->product_views ?? 0);
        }

        DB::table('activity_ecom_daily_site_metrics')->updateOrInsert(
            ['metric_date' => $day->toDateString()],
            $sitePayload,
        );
    }

    private function rollupDailyVisitors(Carbon $day, Carbon $from, Carbon $to): void
    {
        $visitDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_visitors')->where('visit_date', $visitDate)->delete();

        $rows = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('s.visitor_id')
            ->where('s.visitor_id', '!=', '');
        CommerceRollupSessionScope::applyHumanSessionFilter($rows, 's');
        $rows = $rows->selectRaw('s.visitor_id as visitor_id,
                COUNT(*) as session_count,
                COALESCE(SUM(s.session_duration_seconds), 0) as total_duration_seconds,
                MIN(s.created_at) as first_seen_at,
                MAX(COALESCE(s.last_active_at, s.created_at)) as last_seen_at')
            ->groupBy('s.visitor_id')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('activity_ecom_daily_visitors')->insert([
                'visitor_id' => (string) $row->visitor_id,
                'visit_date' => $visitDate,
                'first_seen_at' => $row->first_seen_at,
                'last_seen_at' => $row->last_seen_at,
                'total_duration_seconds' => (int) $row->total_duration_seconds,
                'session_count' => (int) $row->session_count,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function rollupVisitorMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_visitor_metrics')
            ->where('metric_date', $metricDate)
            ->delete();

        $orderRevenue = DB::table('activity_ecom_orders as o')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'o.session_id')
            ->whereBetween('o.ordered_at', $bounds)
            ->whereNotNull('s.visitor_id')
            ->selectRaw('s.visitor_id, COALESCE(SUM(o.amount_paid), 0) as revenue')
            ->groupBy('s.visitor_id')
            ->pluck('revenue', 'visitor_id');

        $rows = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('s.visitor_id')
            ->where('s.visitor_id', '!=', '');
        CommerceRollupSessionScope::applyHumanSessionFilter($rows, 's');
        $rows = $rows->selectRaw('s.visitor_id as visitor_id, COUNT(*) as session_count, SUM(s.has_payment_success) as payment_count, MIN(s.created_at) as first_seen_at, MAX(COALESCE(s.last_active_at, s.created_at)) as last_seen_at')
            ->groupBy('s.visitor_id')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            $visitorId = (string) $row->visitor_id;

            DB::table('activity_ecom_daily_visitor_metrics')->insert([
                'metric_date' => $metricDate,
                'visitor_id' => $visitorId,
                'session_count' => (int) $row->session_count,
                'payment_count' => (int) $row->payment_count,
                'revenue' => (float) ($orderRevenue[$visitorId] ?? 0),
                'first_seen_at' => $row->first_seen_at,
                'last_seen_at' => $row->last_seen_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function rollupTrafficDimensionMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $buckets = $this->trafficAggregator->bucketsForRange($from, $to, null, null);
        $metricDate = $day->toDateString();

        DB::table('activity_ecom_daily_dimension_metrics')
            ->where('metric_date', $metricDate)
            ->where('dimension_type', EcomDailyDimensionType::LIST_TRAFFIC)
            ->delete();

        $now = now();

        foreach ($buckets as $bucket) {
            $source = (string) ($bucket['source'] ?? '(direct)');
            $medium = (string) ($bucket['medium'] ?? 'none');

            DB::table('activity_ecom_daily_dimension_metrics')->insert([
                'metric_date' => $metricDate,
                'dimension_type' => EcomDailyDimensionType::LIST_TRAFFIC,
                'dimension_value' => EcomDailyDimensionType::trafficDimensionValue($source, $medium),
                'session_count' => (int) ($bucket['sessions'] ?? 0),
                'visitor_count' => 0,
                'payment_count' => (int) ($bucket['payment_success'] ?? 0),
                'revenue' => (float) ($bucket['revenue'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function rollupCurrencyDimensionMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_dimension_metrics')
            ->where('metric_date', $metricDate)
            ->where('dimension_type', EcomDailyDimensionType::CURRENCY)
            ->delete();

        $rows = DB::table('activity_ecom_orders')
            ->selectRaw("COALESCE(NULLIF(currency, ''), '(none)') as currency_label, COUNT(*) as payment_count, COALESCE(SUM(amount_paid), 0) as revenue")
            ->whereBetween('ordered_at', $bounds)
            ->groupBy('currency_label')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('activity_ecom_daily_dimension_metrics')->insert([
                'metric_date' => $metricDate,
                'dimension_type' => EcomDailyDimensionType::CURRENCY,
                'dimension_value' => (string) $row->currency_label,
                'session_count' => 0,
                'visitor_count' => 0,
                'payment_count' => (int) $row->payment_count,
                'revenue' => round((float) $row->revenue, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function rollupProductMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_product_metrics')->where('metric_date', $metricDate)->delete();

        $aggregateByCatalogId = config('tracker.rollups_aggregate_by_catalog_ids', false)
            && EcomDailyRollupSchema::hasCatalogIdColumns();

        $productQuery = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->leftJoin('activity_ecom_user_bot_context as bc_li', 'bc_li.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.product_code')
            ->where('li.product_code', '!=', '');

        $groupColumn = $aggregateByCatalogId ? 'li.tracker_product_id' : 'li.product_code';

        $rows = $productQuery->selectRaw(
            ($aggregateByCatalogId ? 'li.tracker_product_id' : 'li.product_code').' as group_key,
                MAX(li.product_code) as product_code,
                MAX(li.product_name) as product_name,
                MAX(li.sku) as sku,
                MAX(li.department_name) as department_name,
                MAX(li.category_name) as category_name,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage IN ('product_view', 'product_view_popup')").' as view_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'add_to_cart'").' as add_to_cart_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'begin_checkout'").' as begin_checkout_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'proceed_checkout'").' as proceed_checkout_count,
                '.CommerceRollupSessionScope::countPaymentSuccessLines().' as payment_count,
                '.CommerceRollupSessionScope::sumPaymentQty().' as units_sold,
                '.CommerceRollupSessionScope::sumPaymentRevenue().' as revenue',
        )
            ->groupBy($groupColumn)
            ->get();

        $now = now();

        foreach ($rows as $row) {
            $payload = [
                'metric_date' => $metricDate,
                'product_code' => (string) $row->product_code,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'department_name' => $row->department_name,
                'category_name' => $row->category_name,
                'view_count' => (int) $row->view_count,
                'add_to_cart_count' => (int) $row->add_to_cart_count,
                'begin_checkout_count' => (int) $row->begin_checkout_count,
                'proceed_checkout_count' => (int) $row->proceed_checkout_count,
                'payment_count' => (int) $row->payment_count,
                'units_sold' => (float) $row->units_sold,
                'revenue' => (float) $row->revenue,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (EcomDailyRollupSchema::hasCatalogIdColumns()) {
                $payload['tracker_product_id'] = $aggregateByCatalogId
                    ? (int) ($row->group_key ?? 0)
                    : null;
            }

            DB::table('activity_ecom_daily_product_metrics')->insert($payload);
        }
    }

    private function rollupCategoryMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_category_metrics')->where('metric_date', $metricDate)->delete();

        $aggregateByCatalogId = config('tracker.rollups_aggregate_by_catalog_ids', false)
            && EcomDailyRollupSchema::hasCatalogIdColumns();

        $categoryQuery = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->leftJoin('activity_ecom_user_bot_context as bc_li', 'bc_li.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.category_name')
            ->where('li.category_name', '!=', '');

        if ($aggregateByCatalogId) {
            $rows = $categoryQuery->selectRaw(
                'li.tracker_department_id,
                li.tracker_category_id,
                MAX(COALESCE(li.department_name, \'\')) as department_name,
                MAX(li.category_name) as category_name,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'category_view'").' as category_view_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage IN ('product_view', 'product_view_popup')").' as product_view_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'add_to_cart'").' as add_to_cart_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'begin_checkout'").' as begin_checkout_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'proceed_checkout'").' as proceed_checkout_count,
                '.CommerceRollupSessionScope::countPaymentSuccessLines().' as payment_count,
                '.CommerceRollupSessionScope::sumPaymentQty().' as units_sold,
                '.CommerceRollupSessionScope::sumPaymentRevenue().' as revenue',
            )
                ->groupBy('li.tracker_department_id', 'li.tracker_category_id')
                ->get();
        } else {
            $rows = $categoryQuery->selectRaw(
                "COALESCE(li.department_name, '') as department_name,
                li.category_name,
                ".CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'category_view'").' as category_view_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage IN ('product_view', 'product_view_popup')").' as product_view_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'add_to_cart'").' as add_to_cart_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'begin_checkout'").' as begin_checkout_count,
                '.CommerceRollupSessionScope::countLineItemsWhere("li.funnel_stage = 'proceed_checkout'").' as proceed_checkout_count,
                '.CommerceRollupSessionScope::countPaymentSuccessLines().' as payment_count,
                '.CommerceRollupSessionScope::sumPaymentQty().' as units_sold,
                '.CommerceRollupSessionScope::sumPaymentRevenue().' as revenue',
            )
                ->groupBy('department_name', 'li.category_name')
                ->get();
        }

        $now = now();

        foreach ($rows as $row) {
            $payload = [
                'metric_date' => $metricDate,
                'department_name' => (string) $row->department_name,
                'category_name' => (string) $row->category_name,
                'category_view_count' => (int) $row->category_view_count,
                'product_view_count' => (int) $row->product_view_count,
                'add_to_cart_count' => (int) $row->add_to_cart_count,
                'begin_checkout_count' => (int) $row->begin_checkout_count,
                'proceed_checkout_count' => (int) $row->proceed_checkout_count,
                'payment_count' => (int) $row->payment_count,
                'units_sold' => (float) $row->units_sold,
                'revenue' => (float) $row->revenue,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (EcomDailyRollupSchema::hasCatalogIdColumns()) {
                $payload['tracker_department_id'] = $aggregateByCatalogId
                    ? (int) ($row->tracker_department_id ?? 0)
                    : null;
                $payload['tracker_category_id'] = $aggregateByCatalogId
                    ? (int) ($row->tracker_category_id ?? 0)
                    : null;
            }

            DB::table('activity_ecom_daily_category_metrics')->insert($payload);
        }
    }

    private function rollupSessionDimensionMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        $types = [
            EcomDailyDimensionType::DEVICE,
            EcomDailyDimensionType::BROWSER,
            EcomDailyDimensionType::GEO,
            EcomDailyDimensionType::LOGGED_IN,
            EcomDailyDimensionType::HAS_ORDER,
        ];

        DB::table('activity_ecom_daily_dimension_metrics')
            ->where('metric_date', $metricDate)
            ->whereIn('dimension_type', $types)
            ->delete();

        $now = now();

        $deviceQuery = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($deviceQuery, 's');
        $deviceRows = $deviceQuery
            ->selectRaw("COALESCE(NULLIF(TRIM(s.device_type), ''), 'unknown') as bucket, COUNT(*) as sessions")
            ->groupBy('bucket')
            ->get();

        foreach ($deviceRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::DEVICE, (string) $row->bucket, (int) $row->sessions, $now);
        }

        $browserQuery = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($browserQuery, 's');
        $browserRows = $browserQuery
            ->selectRaw("COALESCE(NULLIF(TRIM(s.browser), ''), 'unknown') as bucket, COUNT(*) as sessions")
            ->groupBy('bucket')
            ->get();

        foreach ($browserRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::BROWSER, (string) $row->bucket, (int) $row->sessions, $now);
        }

        $geoQuery = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($geoQuery, 's');
        $geoRows = $geoQuery
            ->selectRaw("COALESCE(NULLIF(TRIM(s.city), ''), 'Unknown') as city, COALESCE(NULLIF(TRIM(s.country), ''), 'Unknown') as country, COUNT(*) as sessions")
            ->groupBy('city', 'country')
            ->get();

        foreach ($geoRows as $row) {
            $value = $row->city."\0".$row->country;
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::GEO, $value, (int) $row->sessions, $now);
        }

        $loggedInQuery = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds);
        CommerceRollupSessionScope::applyHumanSessionFilter($loggedInQuery, 's');
        $loggedInRows = $loggedInQuery
            ->selectRaw('s.is_logged_in as is_logged_in, COUNT(*) as sessions')
            ->groupBy('s.is_logged_in')
            ->get();

        foreach ($loggedInRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::LOGGED_IN, (int) $row->is_logged_in === 1 ? '1' : '0', (int) $row->sessions, $now);
        }

        $hasOrderQuery = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds)
            ->leftJoin('activity_ecom_orders as o', function ($join) use ($bounds) {
                $join->on('o.session_id', '=', 's.session_id')
                    ->whereBetween('o.ordered_at', $bounds);
            });
        CommerceRollupSessionScope::applyHumanSessionFilter($hasOrderQuery, 's');
        $hasOrder = $hasOrderQuery
            ->selectRaw('CASE WHEN o.session_id IS NOT NULL THEN 1 ELSE 0 END as has_order, COUNT(*) as sessions')
            ->groupBy('has_order')
            ->get();

        foreach ($hasOrder as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::HAS_ORDER, (string) (int) $row->has_order, (int) $row->sessions, $now);
        }
    }

    private function insertDimensionRow(string $metricDate, string $type, string $value, int $sessions, $now): void
    {
        DB::table('activity_ecom_daily_dimension_metrics')->insert([
            'metric_date' => $metricDate,
            'dimension_type' => $type,
            'dimension_value' => $value,
            'session_count' => $sessions,
            'visitor_count' => 0,
            'payment_count' => 0,
            'revenue' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
