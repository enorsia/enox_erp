<?php

namespace App\Services;

use App\Support\CommerceFunnelQuery;
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
        if (EcomDailyRollupSchema::hasCommerceViewColumns()) {
            $this->rollupProductMetrics($day, $from, $to);
            $this->rollupCategoryMetrics($day, $from, $to);
        }
        $this->rollupSessionDimensionMetrics($day, $from, $to);
    }

    private function rollupSiteMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        $sessions = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw(
                'COUNT(*) as session_count,
                 COUNT(DISTINCT visitor_id) as visitor_count,
                 COALESCE(SUM(has_add_to_cart), 0) as add_to_cart,
                 COALESCE(SUM(has_begin_checkout), 0) as begin_checkout,
                 COALESCE(SUM(has_proceed_checkout), 0) as proceed_checkout,
                 COALESCE(SUM(has_payment_success), 0) as payment_success',
            )
            ->first();

        $actionCount = (int) DB::table('activity_ecom_user_actions')
            ->whereBetween('created_at', $bounds)
            ->count();

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
                ->whereBetween('s.created_at', $bounds)
                ->selectRaw(
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

        $rows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', '')
            ->selectRaw('visitor_id,
                COUNT(*) as session_count,
                COALESCE(SUM(session_duration_seconds), 0) as total_duration_seconds,
                MIN(created_at) as first_seen_at,
                MAX(COALESCE(last_active_at, created_at)) as last_seen_at')
            ->groupBy('visitor_id')
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

        $rows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', '')
            ->selectRaw('visitor_id, COUNT(*) as session_count, SUM(has_payment_success) as payment_count, MIN(created_at) as first_seen_at, MAX(COALESCE(last_active_at, created_at)) as last_seen_at')
            ->groupBy('visitor_id')
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

    private function rollupProductMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_product_metrics')->where('metric_date', $metricDate)->delete();

        $rows = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.product_code')
            ->where('li.product_code', '!=', '')
            ->selectRaw("li.product_code,
                MAX(li.product_name) as product_name,
                MAX(li.sku) as sku,
                MAX(li.department_name) as department_name,
                MAX(li.category_name) as category_name,
                SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as view_count,
                SUM(CASE WHEN li.funnel_stage = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_cart_count,
                SUM(CASE WHEN li.funnel_stage = 'begin_checkout' THEN 1 ELSE 0 END) as begin_checkout_count,
                SUM(CASE WHEN li.funnel_stage = 'proceed_checkout' THEN 1 ELSE 0 END) as proceed_checkout_count,
                SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN 1 ELSE 0 END) as payment_count,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.qty ELSE 0 END), 0) as units_sold,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.line_total ELSE 0 END), 0) as revenue")
            ->groupBy('li.product_code')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('activity_ecom_daily_product_metrics')->insert([
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
            ]);
        }
    }

    private function rollupCategoryMetrics(Carbon $day, Carbon $from, Carbon $to): void
    {
        $metricDate = $day->toDateString();
        $bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        DB::table('activity_ecom_daily_category_metrics')->where('metric_date', $metricDate)->delete();

        $rows = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds)
            ->whereNotNull('li.category_name')
            ->where('li.category_name', '!=', '')
            ->selectRaw("COALESCE(li.department_name, '') as department_name,
                li.category_name,
                SUM(CASE WHEN li.funnel_stage = 'category_view' THEN 1 ELSE 0 END) as category_view_count,
                SUM(CASE WHEN li.funnel_stage IN ('product_view', 'product_view_popup') THEN 1 ELSE 0 END) as product_view_count,
                SUM(CASE WHEN li.funnel_stage = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_cart_count,
                SUM(CASE WHEN li.funnel_stage = 'begin_checkout' THEN 1 ELSE 0 END) as begin_checkout_count,
                SUM(CASE WHEN li.funnel_stage = 'proceed_checkout' THEN 1 ELSE 0 END) as proceed_checkout_count,
                SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN 1 ELSE 0 END) as payment_count,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.qty ELSE 0 END), 0) as units_sold,
                COALESCE(SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN li.line_total ELSE 0 END), 0) as revenue")
            ->groupBy('department_name', 'li.category_name')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('activity_ecom_daily_category_metrics')->insert([
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
            ]);
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

        $deviceRows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw("COALESCE(NULLIF(TRIM(device_type), ''), 'unknown') as bucket, COUNT(*) as sessions")
            ->groupBy('bucket')
            ->get();

        foreach ($deviceRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::DEVICE, (string) $row->bucket, (int) $row->sessions, $now);
        }

        $browserRows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw("COALESCE(NULLIF(TRIM(browser), ''), 'unknown') as bucket, COUNT(*) as sessions")
            ->groupBy('bucket')
            ->get();

        foreach ($browserRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::BROWSER, (string) $row->bucket, (int) $row->sessions, $now);
        }

        $geoRows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw("COALESCE(NULLIF(TRIM(city), ''), 'Unknown') as city, COALESCE(NULLIF(TRIM(country), ''), 'Unknown') as country, COUNT(*) as sessions")
            ->groupBy('city', 'country')
            ->get();

        foreach ($geoRows as $row) {
            $value = $row->city."\0".$row->country;
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::GEO, $value, (int) $row->sessions, $now);
        }

        $loggedInRows = DB::table('activity_ecom_user')
            ->whereBetween('created_at', $bounds)
            ->selectRaw('is_logged_in, COUNT(*) as sessions')
            ->groupBy('is_logged_in')
            ->get();

        foreach ($loggedInRows as $row) {
            $this->insertDimensionRow($metricDate, EcomDailyDimensionType::LOGGED_IN, (int) $row->is_logged_in === 1 ? '1' : '0', (int) $row->sessions, $now);
        }

        $hasOrder = DB::table('activity_ecom_user as s')
            ->whereBetween('s.created_at', $bounds)
            ->leftJoin('activity_ecom_orders as o', function ($join) use ($bounds) {
                $join->on('o.session_id', '=', 's.session_id')
                    ->whereBetween('o.ordered_at', $bounds);
            })
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
