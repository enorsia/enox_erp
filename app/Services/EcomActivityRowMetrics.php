<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\EcomActivityCommerceSummary;
use App\Support\SessionTrafficAttribution;
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
            $this->attachCategoryMetrics($metrics, $sessionIds, $from, $to);
        }

        if ($focus === 'traffic') {
            foreach ($sessions as $session) {
                $traffic = SessionTrafficAttribution::listRowSummary($session);
                $metrics[$session->session_id]['traffic_source'] = $traffic['source'] ?? '—';
                $metrics[$session->session_id]['traffic_medium'] = $traffic['utm']['medium'] ?? ($session->utm_medium ?? '—');
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

        $this->attachCommerceSummary($metrics, $sessionIds, $from, $to);

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
    ): void {
        $actions = ActivityEcomUserAction::query()
            ->select('id', 'session_id', 'action_type', 'add_to_cart', 'begin_checkout', 'proceed_to_checkout', 'payment_success', 'created_at')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', ['add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success'])
            ->get()
            ->groupBy('session_id');

        foreach ($sessionIds as $sessionId) {
            $summary = EcomActivityCommerceSummary::summarizeActions($actions->get($sessionId, collect()));
            $metrics[$sessionId] = array_merge($metrics[$sessionId] ?? [], $summary);
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

        $actions = ActivityEcomUserAction::query()
            ->select('session_id', 'action_type')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', ['product_view', 'product_view_popup', 'add_to_cart', 'payment_success'])
            ->get()
            ->groupBy('session_id');

        foreach ($actions as $sessionId => $sessionActions) {
            $viewed = 0;
            $adds = 0;
            $purchased = false;

            foreach ($sessionActions as $action) {
                if (in_array($action->action_type, ['product_view', 'product_view_popup'], true)) {
                    $viewed++;
                }

                if ($action->action_type === 'add_to_cart') {
                    $adds++;
                }

                if ($action->action_type === 'payment_success') {
                    $purchased = true;
                }
            }

            $metrics[$sessionId]['products_viewed'] = $viewed;
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
        $payments = ActivityEcomUserAction::query()
            ->select('session_id', 'payment_success', 'created_at')
            ->whereIn('session_id', $sessionIds)
            ->where('action_type', 'payment_success')
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('session_id');

        foreach ($payments as $sessionId => $actions) {
            $qty = 0;
            $value = 0.0;

            foreach ($actions as $action) {
                $payload = is_array($action->payment_success) ? $action->payment_success : [];
                $value += (float) ($payload['amount_paid'] ?? 0);
                $qty += max(1, (int) ($payload['qty'] ?? $payload['quantity'] ?? 1));
            }

            $metrics[$sessionId]['order_qty'] = $qty;
            $metrics[$sessionId]['order_value'] = round($value, 2);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $metrics
     * @param  Collection<int, string>  $sessionIds
     */
    private function attachCategoryMetrics(array &$metrics, Collection $sessionIds, Carbon $from, Carbon $to): void
    {
        $actions = ActivityEcomUserAction::query()
            ->select('session_id', 'action_type', 'category_name', 'payment_success')
            ->whereIn('session_id', $sessionIds)
            ->whereBetween('created_at', TrackerTime::storageRange($from, $to))
            ->whereIn('action_type', ['category_view', 'payment_success'])
            ->get()
            ->groupBy('session_id');

        foreach ($actions as $sessionId => $sessionActions) {
            $topCategory = '—';
            $purchases = 0;

            foreach ($sessionActions as $action) {
                if ($action->action_type === 'category_view') {
                    $label = trim((string) ($action->category_name ?? ''));

                    if ($label !== '') {
                        $topCategory = $label;
                    }
                }

                if ($action->action_type === 'payment_success') {
                    $purchases++;
                }
            }

            $metrics[$sessionId]['top_category'] = $topCategory;
            $metrics[$sessionId]['purchases'] = $purchases;
        }
    }
}
