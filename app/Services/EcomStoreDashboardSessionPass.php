<?php

namespace App\Services;

use App\Support\EcomDailyDimensionType;
use App\Support\SessionDurationBuckets;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One scoped read of activity_ecom_user for the store dashboard batch path.
 */
final class EcomStoreDashboardSessionPass
{
    /**
     * @return Collection<int, object>
     */
    public function maxLastActiveAtFromSessionRows(Collection $rows): ?string
    {
        $lastActiveAt = null;

        foreach ($rows as $row) {
            $activeAt = $row->last_active_at ?? $row->updated_at;
            if ($activeAt !== null && ($lastActiveAt === null || (string) $activeAt > (string) $lastActiveAt)) {
                $lastActiveAt = (string) $activeAt;
            }
        }

        return $lastActiveAt;
    }

    public function loadRows(Carbon $from, Carbon $to, ?string $period): Collection
    {
        $query = DB::table('activity_ecom_user as s')->select(
            's.session_id',
            's.visitor_id',
            's.device_type',
            's.browser',
            's.city',
            's.country',
            's.session_duration_seconds',
            's.has_add_to_cart',
            's.has_begin_checkout',
            's.has_proceed_checkout',
            's.has_payment_success',
            's.list_traffic_utm_source',
            's.list_traffic_utm_medium',
            's.conversion_utm_source',
            's.utm_source',
            's.created_at',
            's.last_active_at',
            's.updated_at',
        );
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 's');

        return $query->get();
    }

    /**
     * Commerce line items for recoverable-sale panels (same scope as dashboard period).
     *
     * @return Collection<int, object>
     */
    /**
     * @return array{buckets: array<int, array<string, mixed>>, total_sessions: int, median_seconds: int, median_label: string}
     */
    public function durationDistributionFromSql(
        Carbon $from,
        Carbon $to,
        ?string $period,
        VisitorAnalyticsService $visitorAnalytics,
    ): array {
        $cases = [];
        foreach (SessionDurationBuckets::definitions() as $bucket) {
            $expr = 'COALESCE(session_duration_seconds, 0)';
            if ($bucket['max'] === PHP_INT_MAX) {
                $cases[] = "SUM(CASE WHEN {$expr} >= {$bucket['min']} THEN 1 ELSE 0 END) as `{$bucket['key']}`";
            } else {
                $cases[] = "SUM(CASE WHEN {$expr} BETWEEN {$bucket['min']} AND {$bucket['max']} THEN 1 ELSE 0 END) as `{$bucket['key']}`";
            }
        }

        $query = DB::table('activity_ecom_user');
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period);
        $row = $query->selectRaw(implode(', ', $cases))->first();

        $countsByKey = [];
        foreach (SessionDurationBuckets::definitions() as $bucket) {
            $countsByKey[$bucket['key']] = (int) ($row?->{$bucket['key']} ?? 0);
        }

        $distribution = SessionDurationBuckets::fromBucketCounts($countsByKey);

        return [
            'buckets' => $distribution['buckets'],
            'total_sessions' => $distribution['total_sessions'],
            'median_seconds' => $distribution['median_seconds'],
            'median_label' => $visitorAnalytics->formatDuration($distribution['median_seconds']),
        ];
    }

    /**
     * @param  Collection<int, object>  $dimensionRows
     * @param  array<string, array<string, int|float|string>>  $liveDeviceBuckets
     * @param  array<string, array<string, int|float|string>>  $liveBrowserBuckets
     * @return array{by_device: array<int, array<string, mixed>>, by_browser: array<int, array<string, mixed>>}
     */
    public function deviceBreakdownFromDimensionRollups(
        Collection $dimensionRows,
        array $liveDeviceBuckets,
        array $liveBrowserBuckets,
    ): array {
        $deviceBuckets = [];
        $browserBuckets = [];

        foreach ($dimensionRows as $row) {
            $type = (string) $row->dimension_type;
            if ($type !== EcomDailyDimensionType::DEVICE && $type !== EcomDailyDimensionType::BROWSER) {
                continue;
            }

            $label = $this->normalizeLabel(
                (string) $row->dimension_value,
                $type === EcomDailyDimensionType::BROWSER ? 'Unknown browser' : 'Unknown device',
            );

            if ($type === EcomDailyDimensionType::DEVICE) {
                $target = &$deviceBuckets;
            } else {
                $target = &$browserBuckets;
            }

            if (! isset($target[$label])) {
                $target[$label] = [
                    'label' => $label,
                    'sessions' => 0,
                    'views' => 0,
                    'add_to_cart' => 0,
                    'begin_checkout' => 0,
                    'proceed_checkout' => 0,
                    'purchases' => 0,
                    'sold_qty' => 0,
                    'revenue' => 0.0,
                ];
            }

            $target[$label]['sessions'] += (int) $row->sessions;
            $target[$label]['purchases'] += (int) $row->payments;
            $target[$label]['revenue'] = round((float) $target[$label]['revenue'] + (float) $row->revenue, 2);
        }

        foreach ($liveDeviceBuckets as $label => $bucket) {
            $this->mergeDeviceBrowserBucket($deviceBuckets, $label, $bucket);
        }

        foreach ($liveBrowserBuckets as $label => $bucket) {
            $this->mergeDeviceBrowserBucket($browserBuckets, $label, $bucket);
        }

        return [
            'by_device' => array_values($deviceBuckets),
            'by_browser' => array_values($browserBuckets),
        ];
    }

    /**
     * @param  array<string, array<string, int|float|string>>  $target
     * @param  array<string, int|float|string>  $bucket
     */
    private function mergeDeviceBrowserBucket(array &$target, string $label, array $bucket): void
    {
        if (! isset($target[$label])) {
            $target[$label] = [
                'label' => $label,
                'sessions' => 0,
                'views' => 0,
                'add_to_cart' => 0,
                'begin_checkout' => 0,
                'proceed_checkout' => 0,
                'purchases' => 0,
                'sold_qty' => 0,
                'revenue' => 0.0,
            ];
        }

        foreach (['sessions', 'views', 'add_to_cart', 'begin_checkout', 'proceed_checkout', 'purchases', 'sold_qty'] as $field) {
            $target[$label][$field] = (int) $target[$label][$field] + (int) ($bucket[$field] ?? 0);
        }

        $target[$label]['revenue'] = round((float) $target[$label]['revenue'] + (float) ($bucket['revenue'] ?? 0), 2);
    }

    public function loadCommerceLineItems(Carbon $from, Carbon $to, ?string $period): Collection
    {
        $query = DB::table('activity_ecom_commerce_line_items as li')
            ->select(
                'li.id',
                'li.session_id',
                'li.event_id',
                'li.funnel_stage',
                'li.staged_at',
                'li.qty',
                'li.line_total',
            )
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to));
        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 's');

        return $query->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, object>  $orders
     * @param  array{live_from: ?Carbon, live_to: ?Carbon}  $split
     * @return array{
     *     abandonment: array{cart_abandoned_count: int, begin_checkout_abandoned_count: int, proceed_checkout_abandoned_count: int},
     *     engagement: array<string, mixed>,
     *     duration_distribution: array{buckets: array<int, array<string, mixed>>, total_sessions: int, median_seconds: int, median_label: string},
     *     last_active_at: ?string,
     *     device_buckets: array<string, array<string, int|float>>,
     *     browser_buckets: array<string, array<string, int|float>>,
     *     session_device_map: array<string, string>,
     *     session_browser_map: array<string, string>,
     *     live_session_aggregates: ?array<string, int>,
     *     live_traffic_buckets: array<string, array<string, int|float|string>>,
     *     live_geo_counts: array<string, int>
     * }
     */
    public function derive(
        Collection $rows,
        Collection $orders,
        array $split,
        VisitorAnalyticsService $visitorAnalytics,
    ): array {
        $buyerSessionIds = [];
        foreach ($orders as $order) {
            $buyerSessionIds[(string) $order->session_id] = true;
        }

        $deviceBuckets = [];
        $browserBuckets = [];
        $sessionDeviceMap = [];
        $sessionBrowserMap = [];
        $durationBucketCounts = [];
        foreach (SessionDurationBuckets::definitions() as $bucket) {
            $durationBucketCounts[$bucket['key']] = 0;
        }

        $cartAbandoned = 0;
        $beginAbandoned = 0;
        $proceedAbandoned = 0;
        $buyerDurationTotal = 0;
        $buyerDurationCount = 0;
        $nonBuyerDurationTotal = 0;
        $nonBuyerDurationCount = 0;
        $lastActiveAt = null;

        $liveFrom = $split['live_from'] ?? null;
        $liveTo = $split['live_to'] ?? null;
        $liveBounds = ($liveFrom !== null && $liveTo !== null)
            ? TrackerTime::storageRange($liveFrom, $liveTo)
            : null;

        $liveAggregates = null;
        $liveTraffic = [];
        $liveGeo = [];
        $liveVisitorIds = [];

        foreach ($rows as $row) {
            $sessionId = (string) $row->session_id;
            $deviceLabel = $this->normalizeLabel($row->device_type, 'Unknown device');
            $browserLabel = $this->normalizeLabel($row->browser, 'Unknown browser');
            $sessionDeviceMap[$sessionId] = $deviceLabel;
            $sessionBrowserMap[$sessionId] = $browserLabel;

            $this->incBucket($deviceBuckets, $deviceLabel, 'sessions');
            $this->incBucket($browserBuckets, $browserLabel, 'sessions');
            if ($row->has_add_to_cart) {
                $this->incBucket($deviceBuckets, $deviceLabel, 'add_to_cart');
                $this->incBucket($browserBuckets, $browserLabel, 'add_to_cart');
            }
            if ($row->has_begin_checkout) {
                $this->incBucket($deviceBuckets, $deviceLabel, 'begin_checkout');
                $this->incBucket($browserBuckets, $browserLabel, 'begin_checkout');
            }
            if ($row->has_proceed_checkout) {
                $this->incBucket($deviceBuckets, $deviceLabel, 'proceed_checkout');
                $this->incBucket($browserBuckets, $browserLabel, 'proceed_checkout');
            }

            if ($row->has_add_to_cart && ! $row->has_begin_checkout && ! $row->has_proceed_checkout && ! $row->has_payment_success) {
                $cartAbandoned++;
            }
            if ($row->has_begin_checkout && ! $row->has_proceed_checkout && ! $row->has_payment_success) {
                $beginAbandoned++;
            }
            if ($row->has_proceed_checkout && ! $row->has_payment_success) {
                $proceedAbandoned++;
            }

            $activeAt = $row->last_active_at ?? $row->updated_at;
            if ($activeAt !== null && ($lastActiveAt === null || (string) $activeAt > (string) $lastActiveAt)) {
                $lastActiveAt = (string) $activeAt;
            }

            if ($row->session_duration_seconds !== null) {
                $seconds = max(0, (int) $row->session_duration_seconds);
                foreach (SessionDurationBuckets::definitions() as $bucket) {
                    if ($seconds >= $bucket['min'] && $seconds <= $bucket['max']) {
                        $durationBucketCounts[$bucket['key']]++;
                        break;
                    }
                }

                if (isset($buyerSessionIds[$sessionId])) {
                    $buyerDurationTotal += $seconds;
                    $buyerDurationCount++;
                } else {
                    $nonBuyerDurationTotal += $seconds;
                    $nonBuyerDurationCount++;
                }
            }

            if ($liveBounds !== null && $this->createdAtInRange((string) $row->created_at, $liveBounds)) {
                $liveAggregates ??= [
                    'sessions' => 0,
                    'unique_visitors' => 0,
                    'total_stay_seconds' => 0,
                    'add_to_cart' => 0,
                    'begin_checkout' => 0,
                    'proceed_checkout' => 0,
                    'payment_success' => 0,
                ];
                $liveAggregates['sessions']++;
                $liveAggregates['total_stay_seconds'] += max(0, (int) ($row->session_duration_seconds ?? 0));
                $liveAggregates['add_to_cart'] += (int) $row->has_add_to_cart;
                $liveAggregates['begin_checkout'] += (int) $row->has_begin_checkout;
                $liveAggregates['proceed_checkout'] += (int) $row->has_proceed_checkout;
                $liveAggregates['payment_success'] += (int) $row->has_payment_success;
                $vid = (string) ($row->visitor_id ?? '');
                if ($vid !== '') {
                    $liveVisitorIds[$vid] = true;
                }

                [$source, $medium] = $this->trafficBucket($row);
                $key = $source."\0".$medium;
                if (! isset($liveTraffic[$key])) {
                    $liveTraffic[$key] = [
                        'source' => $source,
                        'medium' => $medium,
                        'sessions' => 0,
                        'views' => 0,
                        'add_to_cart' => 0,
                        'begin_checkout' => 0,
                        'proceed_checkout' => 0,
                        'payment_success' => 0,
                        'sold_qty' => 0,
                        'revenue' => 0.0,
                    ];
                }
                $liveTraffic[$key]['sessions']++;
                $liveTraffic[$key]['add_to_cart'] += (int) $row->has_add_to_cart;
                $liveTraffic[$key]['begin_checkout'] += (int) $row->has_begin_checkout;
                $liveTraffic[$key]['proceed_checkout'] += (int) $row->has_proceed_checkout;

                $geoKey = $this->geoKey($row);
                $liveGeo[$geoKey] = ($liveGeo[$geoKey] ?? 0) + 1;
            }
        }

        if ($liveAggregates !== null) {
            $sessions = (int) $liveAggregates['sessions'];
            $totalStay = (int) $liveAggregates['total_stay_seconds'];
            $liveAggregates['unique_visitors'] = count($liveVisitorIds);
            $liveAggregates['avg_stay_seconds'] = $sessions > 0 ? (int) round($totalStay / $sessions) : 0;
        }

        $buyerSeconds = $buyerDurationCount > 0 ? (int) round($buyerDurationTotal / $buyerDurationCount) : 0;
        $nonBuyerSeconds = $nonBuyerDurationCount > 0 ? (int) round($nonBuyerDurationTotal / $nonBuyerDurationCount) : 0;
        $labels = ['Category page', 'Product page'];
        $buyers = [$buyerSeconds, $buyerSeconds];
        $nonBuyers = [$nonBuyerSeconds, $nonBuyerSeconds];
        $maxSeconds = max(1, ...$buyers, ...$nonBuyers);

        $engagementRows = collect($labels)->map(function (string $label, int $index) use ($buyers, $nonBuyers, $maxSeconds) {
            $buyerSec = (int) ($buyers[$index] ?? 0);
            $nonBuyerSec = (int) ($nonBuyers[$index] ?? 0);
            $deltaSeconds = $buyerSec - $nonBuyerSec;

            return [
                'page_type' => $label,
                'buyers_seconds' => $buyerSec,
                'non_buyers_seconds' => $nonBuyerSec,
                'buyers_formatted' => format_duration($buyerSec),
                'non_buyers_formatted' => format_duration($nonBuyerSec),
                'delta_seconds' => $deltaSeconds,
                'delta_formatted' => $deltaSeconds === 0 ? '—' : format_duration(abs($deltaSeconds)),
                'buyers_bar_percent' => round(($buyerSec / $maxSeconds) * 100, 1),
                'non_buyers_bar_percent' => round(($nonBuyerSec / $maxSeconds) * 100, 1),
            ];
        })->values()->all();

        $distribution = SessionDurationBuckets::fromBucketCounts($durationBucketCounts);

        return [
            'abandonment' => [
                'cart_abandoned_count' => $cartAbandoned,
                'begin_checkout_abandoned_count' => $beginAbandoned,
                'proceed_checkout_abandoned_count' => $proceedAbandoned,
            ],
            'engagement' => [
                'labels' => $labels,
                'buyers' => $buyers,
                'non_buyers' => $nonBuyers,
                'rows' => $engagementRows,
            ],
            'duration_distribution' => [
                'buckets' => $distribution['buckets'],
                'total_sessions' => $distribution['total_sessions'],
                'median_seconds' => $distribution['median_seconds'],
                'median_label' => $visitorAnalytics->formatDuration($distribution['median_seconds']),
            ],
            'last_active_at' => $lastActiveAt,
            'device_buckets' => $deviceBuckets,
            'browser_buckets' => $browserBuckets,
            'session_device_map' => $sessionDeviceMap,
            'session_browser_map' => $sessionBrowserMap,
            'live_session_aggregates' => $liveAggregates,
            'live_traffic_buckets' => $liveTraffic,
            'live_geo_counts' => $liveGeo,
        ];
    }

    /**
     * @param  list<string>  $closedDates
     * @return array{total_stay: int, closed_visitors: int, overlap_visitors: int}
     */
    public function closedVisitorStats(array $closedDates, ?Carbon $liveFrom, ?Carbon $liveTo): array
    {
        if ($closedDates === []) {
            return ['total_stay' => 0, 'closed_visitors' => 0, 'overlap_visitors' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($closedDates), '?'));
        $bindings = [...$closedDates, ...$closedDates];
        $overlapSql = '0 AS overlap_visitors';

        if ($liveFrom !== null && $liveTo !== null) {
            [$liveStart, $liveEnd] = TrackerTime::storageRange($liveFrom, $liveTo);
            $overlapSql = '(SELECT COUNT(DISTINCT d.visitor_id) FROM activity_ecom_daily_visitor_metrics AS d
                INNER JOIN activity_ecom_user AS s ON s.visitor_id = d.visitor_id
                WHERE d.metric_date IN ('.$placeholders.')
                AND s.created_at BETWEEN ? AND ?) AS overlap_visitors';
            $bindings = [...$closedDates, ...$closedDates, ...$closedDates, $liveStart, $liveEnd];
        }

        $row = DB::selectOne(
            'SELECT
                (SELECT COALESCE(SUM(total_duration_seconds), 0) FROM activity_ecom_daily_visitors WHERE visit_date IN ('.$placeholders.')) AS total_stay,
                (SELECT COUNT(DISTINCT visitor_id) FROM activity_ecom_daily_visitor_metrics WHERE metric_date IN ('.$placeholders.')) AS closed_visitors,
                '.$overlapSql,
            $bindings,
        );

        return [
            'total_stay' => (int) ($row->total_stay ?? 0),
            'closed_visitors' => (int) ($row->closed_visitors ?? 0),
            'overlap_visitors' => (int) ($row->overlap_visitors ?? 0),
        ];
    }

    /**
     * @param  array<string, array<string, int|float>>  $deviceBuckets
     * @param  array<string, array<string, int|float>>  $browserBuckets
     * @param  array<string, string>  $sessionDeviceMap
     * @param  array<string, string>  $sessionBrowserMap
     * @param  Collection<int, object>  $orders
     * @return array{by_device: array<int, array<string, mixed>>, by_browser: array<int, array<string, mixed>>}
     */
    public function finalizeDeviceBreakdown(
        array $deviceBuckets,
        array $browserBuckets,
        array $sessionDeviceMap,
        array $sessionBrowserMap,
        Collection $orders,
        Carbon $from,
        Carbon $to,
        ?string $period,
    ): array {
        $viewQuery = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereIn('li.funnel_stage', ['product_view', 'product_view_popup'])
            ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to));
        TrackerTime::applyEcomActivitySessionScope($viewQuery, $from, $to, $period, 's');

        foreach ($viewQuery->selectRaw("COALESCE(NULLIF(TRIM(s.device_type), ''), 'unknown') as device_bucket,
                COALESCE(NULLIF(TRIM(s.browser), ''), 'unknown') as browser_bucket,
                COUNT(*) as view_count")->groupBy('device_bucket', 'browser_bucket')->get() as $row) {
            $deviceLabel = $this->normalizeLabel((string) $row->device_bucket, 'Unknown device');
            $browserLabel = $this->normalizeLabel((string) $row->browser_bucket, 'Unknown browser');
            $views = (int) $row->view_count;
            $this->incBucket($deviceBuckets, $deviceLabel, 'views', $views);
            $this->incBucket($browserBuckets, $browserLabel, 'views', $views);
        }

        $devicePurchaseSeen = [];
        $browserPurchaseSeen = [];

        foreach ($orders as $order) {
            $sessionId = (string) $order->session_id;
            $deviceLabel = $sessionDeviceMap[$sessionId] ?? null;
            $browserLabel = $sessionBrowserMap[$sessionId] ?? null;
            $soldQty = max(0, (int) ($order->item_qty ?? 0));
            $revenue = (float) ($order->amount_paid ?? 0);

            if ($deviceLabel) {
                if (! isset($devicePurchaseSeen[$sessionId])) {
                    $devicePurchaseSeen[$sessionId] = true;
                    $this->incBucket($deviceBuckets, $deviceLabel, 'purchases');
                }
                $this->incBucket($deviceBuckets, $deviceLabel, 'sold_qty', $soldQty);
                $this->incBucket($deviceBuckets, $deviceLabel, 'revenue', $revenue);
            }

            if ($browserLabel) {
                if (! isset($browserPurchaseSeen[$sessionId])) {
                    $browserPurchaseSeen[$sessionId] = true;
                    $this->incBucket($browserBuckets, $browserLabel, 'purchases');
                }
                $this->incBucket($browserBuckets, $browserLabel, 'sold_qty', $soldQty);
                $this->incBucket($browserBuckets, $browserLabel, 'revenue', $revenue);
            }
        }

        return [
            'by_device' => array_values($deviceBuckets),
            'by_browser' => array_values($browserBuckets),
        ];
    }

    /**
     * @param  array<string, int>  $liveGeoCounts
     * @return array<int, array{location: string, sessions: int, revenue: float}>
     */
    public function mergeLiveGeoIntoRows(array $baseGeoBuckets, array $liveGeoCounts): array
    {
        foreach ($liveGeoCounts as $key => $sessions) {
            $baseGeoBuckets[$key] = ($baseGeoBuckets[$key] ?? 0) + $sessions;
        }

        return collect($baseGeoBuckets)
            ->map(function (int $sessions, string $key) {
                [$city, $country] = array_pad(explode("\0", $key, 2), 2, 'Unknown');

                return [
                    'location' => $city.', '.$country,
                    'sessions' => $sessions,
                    'revenue' => 0.0,
                ];
            })
            ->sortByDesc('sessions')
            ->values()
            ->all();
    }

    /**
     * @param  array{live_from: ?Carbon, live_to: ?Carbon}  $split
     */
    public function liveOrderTotals(Collection $orders, array $split): array
    {
        $liveFrom = $split['live_from'] ?? null;
        $liveTo = $split['live_to'] ?? null;

        if ($liveFrom === null || $liveTo === null) {
            return ['revenue' => 0.0, 'items_sold_qty' => 0];
        }

        [$start, $end] = TrackerTime::storageRange($liveFrom, $liveTo);
        $revenue = 0.0;
        $qty = 0;

        foreach ($orders as $order) {
            $at = (string) ($order->ordered_at ?? '');
            if ($at === '' || $at < $start || $at > $end) {
                continue;
            }
            $revenue += (float) ($order->amount_paid ?? 0);
            $qty += (int) ($order->item_qty ?? 0);
        }

        return ['revenue' => $revenue, 'items_sold_qty' => $qty];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function trafficBucket(object $row): array
    {
        $stored = trim((string) ($row->list_traffic_utm_source ?? ''));
        if ($stored !== '') {
            return [$stored, (string) ($row->list_traffic_utm_medium ?? 'none')];
        }

        if ((int) ($row->has_payment_success ?? 0) === 1) {
            $conversion = trim((string) ($row->conversion_utm_source ?? ''));
            if ($conversion !== '') {
                return [$conversion, 'none'];
            }
        }

        $utm = trim((string) ($row->utm_source ?? ''));
        if ($utm !== '') {
            return [$utm, 'none'];
        }

        return ['(direct)', 'none'];
    }

    private function geoKey(object $row): string
    {
        $city = trim((string) ($row->city ?? ''));
        $country = trim((string) ($row->country ?? ''));
        $city = $city === '' ? 'Unknown' : $city;
        $country = $country === '' ? 'Unknown' : $country;

        return $city."\0".$country;
    }

    /**
     * @param  list<string>  $range
     */
    private function createdAtInRange(string $createdAt, array $range): bool
    {
        return $createdAt >= $range[0] && $createdAt <= $range[1];
    }

    private function normalizeLabel(?string $value, string $fallback): string
    {
        $label = trim((string) $value);

        return $label === '' ? $fallback : ucfirst($label);
    }

    /**
     * @param  array<string, array<string, int|float>>  $buckets
     */
    private function incBucket(array &$buckets, string $label, string $field, int|float $amount = 1): void
    {
        if (! isset($buckets[$label])) {
            $buckets[$label] = [
                'label' => $label,
                'sessions' => 0,
                'views' => 0,
                'add_to_cart' => 0,
                'begin_checkout' => 0,
                'proceed_checkout' => 0,
                'purchases' => 0,
                'sold_qty' => 0,
                'revenue' => 0.0,
            ];
        }

        if ($field === 'revenue') {
            $buckets[$label][$field] = round((float) $buckets[$label][$field] + (float) $amount, 2);

            return;
        }

        $buckets[$label][$field] = (int) $buckets[$label][$field] + (int) $amount;
    }
}
