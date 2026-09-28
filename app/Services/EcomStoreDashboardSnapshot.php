<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * In-memory result of one batched dashboard read (one HTTP request, no cache store).
 */
final class EcomStoreDashboardSnapshot
{
    /**
     * @param  array<string, int|float>  $periodSessionAggregates
     * @param  array{cart_abandoned_count: int, begin_checkout_abandoned_count: int, proceed_checkout_abandoned_count: int}  $abandonmentCounts
     * @param  array<string, array<string, int>>  $siteMetricsByDate
     * @param  array{products: array{products: array<int, array<string, mixed>>, filter_options: array<string, mixed>, sort_by: string}, categories: array<int, array<string, mixed>>}  $catalog
     * @param  array<string, array<string, int|float|string>>  $trafficBuckets
     * @param  array<int, array{location: string, sessions: int, revenue: float}>  $geographyRows
     * @param  array{by_device: array<int, array<string, mixed>>, by_browser: array<int, array<string, mixed>>}  $deviceBreakdown
     * @param  array<string, mixed>  $engagement
     * @param  array{buckets: array<int, array<string, mixed>>, total_sessions: int, median_seconds: int, median_label: string}  $durationDistribution
     * @param  array<string, mixed>  $visitorQualitySummary
     * @param  Collection<string, object>  $sessions
     * @param  Collection<int, object>  $commerceLineItems
     * @param  Collection<int, object>  $orders
     * @param  list<array<string, mixed>>  $periodPaymentRows
     */
    public function __construct(
        public array $periodSessionAggregates,
        public array $abandonmentCounts,
        public float $revenueTotal,
        public array $periodPaymentRows,
        public array $siteMetricsByDate,
        public array $catalog,
        public array $trafficBuckets,
        public array $geographyRows,
        public array $deviceBreakdown,
        public array $engagement,
        public array $durationDistribution,
        public array $visitorQualitySummary,
        public Collection $sessions,
        public Collection $commerceLineItems,
        public Collection $orders,
        public ?string $lastActiveAt = null,
        public bool $slimRecoverableHydration = false,
    ) {}
}
