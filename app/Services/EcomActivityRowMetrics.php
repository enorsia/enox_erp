<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Support\CommerceLineItemQuery;
use App\Support\CommerceReadSupport;
use App\Support\EcomActivityCommerceEvents;
use App\Support\EcomActivityCommerceSummary;
use App\Support\EcomActivitySessionSort;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerCategoryIdentity;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Loads per-row focus metrics for the activity index table (one batch per page).
 */
class EcomActivityRowMetrics
{
    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
    ) {}

    /**
     * @param  Collection<int, ActivityEcomUser>  $sessions
     * @param  array<string, array<string, mixed>>  $funnelMetrics
     * @param  array<string, mixed>  $productCatalogOptions
     * @return array<string, array<string, mixed>>
     */
    public function forSessions(
        Collection $sessions,
        ?string $focus,
        Carbon $from,
        Carbon $to,
        array $funnelMetrics = [],
        array $productCatalogOptions = [],
    ): array {
        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('session_id');
        $metrics = [];

        foreach ($sessions as $session) {
            $metrics[$session->session_id] = [];
        }

        if ($funnelMetrics !== []) {
            foreach ($funnelMetrics as $sessionId => $row) {
                if (! isset($metrics[$sessionId])) {
                    continue;
                }

                $metrics[$sessionId] = array_merge($metrics[$sessionId], [
                    'cart_qty' => $row['qty'] ?? 0,
                    'cart_value' => $row['value'] ?? 0,
                    'checkout_qty' => $row['qty'] ?? 0,
                    'checkout_value' => $row['value'] ?? 0,
                    'order_qty' => $row['qty'] ?? 0,
                    'order_value' => $row['value'] ?? 0,
                    'abandoned_at' => TrackerTime::diffForHumansFromStorage($row['occurred_at'] ?? null) ?? '—',
                ]);
            }
        }

        if (in_array($focus, ['conversion', 'payment_success'], true) && $funnelMetrics === []) {
            $this->attachPaymentMetrics($metrics, $sessionIds, $from, $to);
        }

        if ($focus === 'products') {
            $this->attachProductMetrics($metrics, $sessionIds, $from, $to, $productCatalogOptions);
        }

        if ($focus === 'categories') {
            $this->attachCategoryMetrics($metrics, $sessionIds, $from, $to, $productCatalogOptions);
        }

        if ($focus === 'traffic') {
            foreach ($sessions as $session) {
                $traffic = SessionTrafficAttribution::listRowSummary($session);
                $metrics[$session->session_id]['traffic_source'] = $traffic['source'] ?? '—';
                $metrics[$session->session_id]['traffic_medium'] = filled($traffic['utm'] ?? null)
                    ? (string) $traffic['utm']
                    : (filled($session->utm_medium) ? (string) $session->utm_medium : '—');
            }
        }

        if ($focus === 'devices') {
            foreach ($sessions as $session) {
                $metrics[$session->session_id]['device_detail'] = ucfirst((string) ($session->device_type ?? '—'))
                    .' · '.($session->browser ?? '—').' · '.($session->os ?? '—');
            }
        }

        if ($focus === 'session_quality') {
            foreach ($sessions as $session) {
                $metrics[$session->session_id]['classification_reason'] = $session->botContext?->marketer_reason_label ?? 'Not classified';
            }
        }

        foreach ($sessions as $session) {
            $metrics[$session->session_id]['actions_count'] = $session->actions_count ?? 0;
        }

        if ($focus === 'audience' || $focus === null) {
            foreach ($sessions as $session) {
                $metrics[$session->session_id]['device'] = ucfirst((string) ($session->device_type ?? '—'));
            }
        }

        $this->attachCommerceSummary(
            $metrics,
            $sessionIds,
            $from,
            $to,
            $productCatalogOptions,
        );

        $this->attachCatalogContext($metrics, $sessionIds, $from, $to, $productCatalogOptions);

        foreach ($metrics as $sessionId => $row) {
            if (! empty($row['abandoned_at']) && ($row['abandoned_at'] ?? '—') !== '—') {
                $metrics[$sessionId]['commerce_meta'] = $row['abandoned_at'];
            }
        }

        return $metrics;
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     */
    private function attachCommerceSummary(
        array &$metrics,
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $catalogOptions = [],
    ): void {
        $useCatalogScope = EcomActivitySessionSort::usesCatalogActionScope($catalogOptions);
        $funnelStages = $useCatalogScope
            ? CommerceLineItemQuery::CATALOG_FUNNEL_STAGES
            : ['add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success'];
        $lines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $from,
            $to,
            $funnelStages,
            $useCatalogScope ? $catalogOptions : [],
        );
        $orders = CommerceReadSupport::ordersForSessions($sessionIds, $from, $to);
        $linesBySession = $lines->groupBy(fn (object $line) => (string) $line->session_id);
        $ordersBySession = $orders->groupBy(fn (object $order) => (string) $order->session_id);

        foreach ($sessionIds as $sessionId) {
            $sessionLines = $linesBySession->get($sessionId, collect());
            $sessionOrders = $ordersBySession->get($sessionId, collect());

            if ($useCatalogScope) {
                $paymentEventIds = $sessionLines
                    ->where('funnel_stage', 'payment_success')
                    ->pluck('event_id')
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->all();
                $sessionOrders = $sessionOrders->filter(
                    fn (object $order) => in_array((string) ($order->event_id ?? ''), $paymentEventIds, true)
                        || in_array((string) ($order->order_id ?? ''), $sessionLines->pluck('order_id')->filter()->map(fn ($id) => (string) $id)->all(), true),
                );
            }

            $summary = EcomActivityCommerceSummary::summarizeFromCommerce($sessionLines, $sessionOrders);
            $metrics[$sessionId] = array_merge($metrics[$sessionId] ?? [], $summary, [
                'commerce_events' => EcomActivityCommerceEvents::fromCommerceRows($sessionLines, $sessionOrders),
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $productCatalogOptions
     */
    private function attachProductMetrics(
        array &$metrics,
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $productCatalogOptions = [],
    ): void {
        if ($productCatalogOptions !== []) {
            $productMetrics = $this->dashboardService->countProductCatalogMetricsForSessions(
                $sessionIds,
                $from,
                $to,
                $productCatalogOptions,
            );

            foreach ($productMetrics as $sessionId => $row) {
                $metrics[$sessionId] = array_merge($metrics[$sessionId] ?? [], $row);
            }

            return;
        }

        $lines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $from,
            $to,
            ['add_to_cart', 'payment_success'],
        )->groupBy(fn (object $line) => (string) $line->session_id);

        foreach ($sessionIds as $sessionId) {
            $sessionLines = $lines->get($sessionId, collect());
            $adds = $sessionLines->where('funnel_stage', 'add_to_cart')->count();
            $purchased = $sessionLines->contains(fn (object $line) => $line->funnel_stage === 'payment_success');

            $metrics[$sessionId]['products_viewed'] = 0;
            $metrics[$sessionId]['adds'] = $adds;
            $metrics[$sessionId]['purchased'] = $purchased ? 'Yes' : '—';
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     */
    private function attachPaymentMetrics(array &$metrics, Collection $sessionIds, Carbon $from, Carbon $to): void
    {
        $payments = CommerceReadSupport::sumPaymentMetricsForSessions($sessionIds, $from, $to);

        foreach ($payments as $sessionId => $row) {
            $metrics[$sessionId]['order_qty'] = (int) ($row->order_qty ?? 0);
            $metrics[$sessionId]['order_value'] = round((float) ($row->order_value ?? 0), 2);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     */
    private function attachCategoryMetrics(
        array &$metrics,
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $categoryCatalogOptions = [],
    ): void {
        if (filled($categoryCatalogOptions['category'] ?? null)) {
            $categoryMetrics = $this->dashboardService->countCategoryCatalogMetricsForSessions(
                $sessionIds,
                $from,
                $to,
                $categoryCatalogOptions,
            );

            foreach ($categoryMetrics as $sessionId => $row) {
                $metrics[$sessionId] = array_merge($metrics[$sessionId] ?? [], $row);
            }

            return;
        }

        $payments = CommerceReadSupport::sumPaymentMetricsForSessions($sessionIds, $from, $to);
        $lines = CommerceReadSupport::linesForSessions($sessionIds, $from, $to)->groupBy(
            fn (object $line) => (string) $line->session_id,
        );

        foreach ($sessionIds as $sessionId) {
            $sessionLines = $lines->get($sessionId, collect());
            $topCategory = '—';

            foreach ($sessionLines->sortByDesc(fn (object $line) => (int) ($line->id ?? 0)) as $line) {
                $department = trim((string) ($line->department_name ?? ''));
                $category = trim((string) ($line->category_name ?? ''));

                if ($category !== '') {
                    $topCategory = TrackerCategoryIdentity::label($department, $category);
                    break;
                }
            }

            $metrics[$sessionId]['top_category'] = $topCategory;
            $metrics[$sessionId]['purchases'] = (int) ($payments->get($sessionId)?->order_qty ?? 0);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     * @param  array<string, mixed>  $catalogOptions
     */
    private function attachCatalogContext(
        array &$metrics,
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
        array $catalogOptions,
    ): void {
        if (! filled($catalogOptions['department'] ?? null) && ! filled($catalogOptions['category'] ?? null)) {
            return;
        }

        $lines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $from,
            $to,
            CommerceLineItemQuery::CATALOG_FUNNEL_STAGES,
            $catalogOptions,
        )->groupBy(fn (object $line) => (string) $line->session_id);

        foreach ($sessionIds as $sessionId) {
            $catalogPath = '—';

            foreach ($lines->get($sessionId, collect())->sortByDesc(fn (object $line) => (int) ($line->id ?? 0)) as $line) {
                $department = trim((string) ($line->department_name ?? ''));
                $category = trim((string) ($line->category_name ?? ''));

                if ($department === '' && $category === '') {
                    continue;
                }

                $catalogPath = TrackerCategoryIdentity::label($department, $category);
                break;
            }

            $metrics[$sessionId]['catalog_path'] = $catalogPath;
        }
    }
}
