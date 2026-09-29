<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Support\CommerceLineItemQuery;
use App\Support\CommerceReadSupport;
use App\Support\EcomActivityCommerceEvents;
use App\Support\EcomActivityCommerceSummary;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivitySessionSort;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerCategoryIdentity;
use App\Support\TrackerMultiSelectFilter;
use App\Support\TrackerProductCatalogIdentity;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        ?Request $request = null,
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
                    'period_event_at' => $row['occurred_at'] ?? null,
                ]);
            }
        }

        if ($request !== null && EcomActivityFocus::shouldAttachPaymentMetrics($focus, $request, $funnelMetrics)) {
            $this->attachPaymentMetrics($metrics, $sessionIds, $from, $to);
        }

        if ($focus === 'products') {
            $this->attachProductMetrics($metrics, $sessionIds, $from, $to, $productCatalogOptions);
        }

        if ($focus === 'categories') {
            $this->attachCategoryMetrics($metrics, $sessionIds, $from, $to, $productCatalogOptions);
        }

        if ($focus === 'traffic' || self::shouldAttachTrafficMetrics($focus, $request)) {
            foreach ($sessions as $session) {
                $bucket = filled($session->list_traffic_utm_source ?? null)
                    ? [
                        'source' => (string) $session->list_traffic_utm_source,
                        'medium' => filled($session->list_traffic_utm_medium ?? null)
                            ? (string) $session->list_traffic_utm_medium
                            : 'none',
                    ]
                    : SessionTrafficAttribution::listTrafficDisplayBucket($session);

                $sourceKey = $bucket['source'] ?? '(direct)';
                $metrics[$session->session_id]['traffic_source'] = $sourceKey === '(direct)'
                    ? 'Direct'
                    : (SessionTrafficAttribution::displaySourceLabel($sourceKey) ?? $sourceKey);
                $medium = filled($bucket['medium'] ?? null) && $bucket['medium'] !== 'none'
                    ? (string) $bucket['medium']
                    : '—';
                $metrics[$session->session_id]['traffic_medium'] = $medium;
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

        if ($focus === 'audience' || $focus === null || self::shouldAttachDeviceMetric($focus, $request)) {
            foreach ($sessions as $session) {
                $metrics[$session->session_id]['device'] = ucfirst((string) ($session->device_type ?? '—'));
            }
        }

        $this->attachCommerceSummary(
            $metrics,
            $sessions,
            $from,
            $to,
            $productCatalogOptions,
            $request,
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
     * @param  Collection<int, ActivityEcomUser>  $sessions
     */
    private function attachCommerceSummary(
        array &$metrics,
        Collection $sessions,
        Carbon $from,
        Carbon $to,
        array $catalogOptions = [],
        ?Request $request = null,
    ): void {
        $periodOnlyCommerce = EcomActivityFocus::usesPeriodOnlyCommerce($request);
        $sessionIds = $sessions->pluck('session_id');
        $useCatalogScope = EcomActivitySessionSort::usesCatalogActionScope($catalogOptions);
        $commerceFunnelStages = ['add_to_cart', 'begin_checkout', 'proceed_checkout', 'payment_success'];
        $viewFunnelStages = ['product_view', 'product_view_popup', 'category_view'];
        $catalogOptionsForQuery = $useCatalogScope ? $catalogOptions : [];
        $epoch = Carbon::parse('2000-01-01', 'UTC');

        $lines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $from,
            $to,
            $commerceFunnelStages,
            $catalogOptionsForQuery,
        );
        $orders = CommerceReadSupport::ordersForSessions($sessionIds, $from, $to);
        $cumulativeLines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $epoch,
            $to,
            $commerceFunnelStages,
            $catalogOptionsForQuery,
        );
        $cumulativeOrders = CommerceReadSupport::ordersForSessions($sessionIds, $epoch, $to);
        $viewLines = CommerceReadSupport::linesForSessions(
            $sessionIds,
            $from,
            $to,
            $viewFunnelStages,
            $catalogOptionsForQuery,
        );
        $linesBySession = $lines->groupBy(fn (object $line) => (string) $line->session_id);
        $ordersBySession = $orders->groupBy(fn (object $order) => (string) $order->session_id);
        $cumulativeLinesBySession = $cumulativeLines->groupBy(fn (object $line) => (string) $line->session_id);
        $cumulativeOrdersBySession = $cumulativeOrders->groupBy(fn (object $order) => (string) $order->session_id);
        $viewLinesBySession = $viewLines->groupBy(fn (object $line) => (string) $line->session_id);
        $paymentActionsBySession = CommerceReadSupport::paymentSuccessActionsForSessions($sessionIds, $from, $to);

        foreach ($sessions as $session) {
            $sessionId = (string) $session->session_id;
            [$sessionLines, $sessionOrders] = $this->scopedCommerceRows(
                $linesBySession->get($sessionId, collect()),
                $ordersBySession->get($sessionId, collect()),
                $catalogOptions,
                $useCatalogScope,
            );

            $summary = EcomActivityCommerceSummary::summarizeFromCommerce($sessionLines, $sessionOrders);
            $eventLines = $sessionLines;
            $eventOrders = $sessionOrders;
            $paymentActionsForEvents = null;

            if (($summary['commerce_label'] ?? null) === null) {
                $paymentActions = $paymentActionsBySession->get($sessionId, collect());

                if ($paymentActions->isNotEmpty()) {
                    $summary = EcomActivityCommerceSummary::summarizeFromPaymentSuccessActions($paymentActions);
                    $paymentActionsForEvents = $paymentActions;

                    if (($metrics[$sessionId]['period_event_at'] ?? null) === null) {
                        $latestPayment = $paymentActions->first();
                        $metrics[$sessionId]['period_event_at'] = $latestPayment?->created_at;
                    }
                }
            }

            if (
                ($summary['commerce_label'] ?? null) === null
                && $periodOnlyCommerce
                && $request !== null
                && EcomActivityFocus::usesPeriodEventTimestamps($request)
                && ($session->has_payment_success ?? false)
            ) {
                $paymentDataUpper = TrackerTime::nowUtc();
                $paymentFallbackOrders = CommerceReadSupport::ordersForSessions(
                    collect([$sessionId]),
                    $epoch,
                    $paymentDataUpper,
                );
                $paymentFallbackLines = CommerceReadSupport::linesForSessions(
                    collect([$sessionId]),
                    $epoch,
                    $paymentDataUpper,
                    ['payment_success'],
                    $catalogOptionsForQuery,
                );
                [$paymentFallbackLines, $paymentFallbackOrders] = $this->scopedCommerceRows(
                    $paymentFallbackLines,
                    $paymentFallbackOrders,
                    $catalogOptions,
                    $useCatalogScope,
                );
                $paymentFallbackSummary = EcomActivityCommerceSummary::summarizeFromCommerce(
                    $paymentFallbackLines,
                    $paymentFallbackOrders,
                );

                if (($paymentFallbackSummary['commerce_label'] ?? null) !== null) {
                    $summary = $paymentFallbackSummary;
                    $eventLines = $paymentFallbackLines;
                    $eventOrders = $paymentFallbackOrders;

                    if (($metrics[$sessionId]['period_event_at'] ?? null) === null) {
                        $latestOrder = $paymentFallbackOrders
                            ->sortByDesc(fn (object $order) => strtotime((string) ($order->ordered_at ?? '')) ?: 0)
                            ->first();
                        $latestLine = $paymentFallbackLines
                            ->sortByDesc(fn (object $line) => strtotime((string) ($line->staged_at ?? '')) ?: 0)
                            ->first();

                        $metrics[$sessionId]['period_event_at'] = TrackerTime::fromStorage(
                            $latestOrder->ordered_at ?? $latestLine->staged_at ?? null,
                        );
                    }

                    if (($metrics[$sessionId]['order_value'] ?? 0) == 0 && ($paymentFallbackSummary['commerce_value'] ?? null) !== null) {
                        $metrics[$sessionId]['order_value'] = round((float) $paymentFallbackSummary['commerce_value'], 2);
                    }
                }
            }

            if (($summary['commerce_label'] ?? null) === null && ! $periodOnlyCommerce && $this->sessionHasCommerceFunnelState($session)) {
                [$cumulativeSessionLines, $cumulativeSessionOrders] = $this->scopedCommerceRows(
                    $cumulativeLinesBySession->get($sessionId, collect()),
                    $cumulativeOrdersBySession->get($sessionId, collect()),
                    $catalogOptions,
                    $useCatalogScope,
                );
                $fallbackSummary = EcomActivityCommerceSummary::summarizeFromCommerce(
                    $cumulativeSessionLines,
                    $cumulativeSessionOrders,
                );

                if (($fallbackSummary['commerce_label'] ?? null) !== null) {
                    $summary = $fallbackSummary;
                    $eventLines = $cumulativeSessionLines;
                    $eventOrders = $cumulativeSessionOrders;
                }
            }

            if (($summary['commerce_label'] ?? null) === null && ! $periodOnlyCommerce) {
                $viewSessionLines = $viewLinesBySession->get($sessionId, collect());

                if ($useCatalogScope) {
                    $viewSessionLines = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions(
                        $viewSessionLines,
                        $catalogOptions,
                    );
                }

                $viewSummary = EcomActivityCommerceSummary::summarizeFromViewLines($viewSessionLines);

                if ($viewSummary !== null) {
                    $summary = $viewSummary;
                }
            }

            $linesForEvents = $eventLines;

            if ($linesForEvents->isEmpty()) {
                $viewSessionLines = $viewLinesBySession->get($sessionId, collect());

                if ($useCatalogScope) {
                    $viewSessionLines = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions(
                        $viewSessionLines,
                        $catalogOptions,
                    );
                }

                if ($viewSessionLines->isNotEmpty()) {
                    $linesForEvents = $viewSessionLines;
                }
            }

            $commerceEvents = $paymentActionsForEvents instanceof Collection
                ? EcomActivityCommerceEvents::fromActions($paymentActionsForEvents)
                : EcomActivityCommerceEvents::fromCommerceRows($linesForEvents, $eventOrders);

            if (
                $request !== null
                && EcomActivityFocus::usesPeriodEventTimestamps($request)
                && ($metrics[$sessionId]['period_event_at'] ?? null) === null
            ) {
                $latestPayment = $paymentActionsBySession->get($sessionId, collect())->first();

                if ($latestPayment !== null) {
                    $metrics[$sessionId]['period_event_at'] = $latestPayment->created_at;
                } elseif ($eventOrders->isNotEmpty()) {
                    $latestOrder = $eventOrders
                        ->sortByDesc(fn (object $order) => strtotime((string) ($order->ordered_at ?? '')) ?: 0)
                        ->first();
                    $metrics[$sessionId]['period_event_at'] = TrackerTime::fromStorage($latestOrder->ordered_at ?? null);
                }
            }

            $metrics[$sessionId] = array_merge($metrics[$sessionId] ?? [], $summary, [
                'commerce_events' => $commerceEvents,
            ]);
        }
    }

    /**
     * @param  Collection<int, object>  $sessionLines
     * @param  Collection<int, object>  $sessionOrders
     * @return array{0: Collection<int, object>, 1: Collection<int, object>}
     */
    private function scopedCommerceRows(
        Collection $sessionLines,
        Collection $sessionOrders,
        array $catalogOptions,
        bool $useCatalogScope,
    ): array {
        if (! $useCatalogScope) {
            return [$sessionLines, $sessionOrders];
        }

        $sessionLines = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions(
            $sessionLines,
            $catalogOptions,
        );
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

        return [$sessionLines, $sessionOrders];
    }

    private function sessionHasCommerceFunnelState(ActivityEcomUser $session): bool
    {
        return ($session->has_payment_success ?? false)
            || ($session->has_proceed_checkout ?? false)
            || ($session->has_begin_checkout ?? false)
            || ($session->has_add_to_cart ?? false);
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
        if (
            ! filled($catalogOptions['department'] ?? null)
            && TrackerMultiSelectFilter::values($catalogOptions['category'] ?? null) === []
        ) {
            return;
        }

        $categoryFilters = TrackerMultiSelectFilter::values($catalogOptions['category'] ?? null);

        if (filled($catalogOptions['department'] ?? null) && count($categoryFilters) === 1) {
            $catalogPath = TrackerCategoryIdentity::label(
                (string) $catalogOptions['department'],
                $categoryFilters[0],
            );

            foreach ($sessionIds as $sessionId) {
                $metrics[$sessionId]['catalog_path'] = $catalogPath;
            }

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
            $sessionLines = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions(
                $lines->get($sessionId, collect()),
                $catalogOptions,
            );
            $rowCatalogPath = null;

            foreach ($sessionLines->sortByDesc(fn (object $line) => (int) ($line->id ?? 0)) as $line) {
                $department = trim((string) ($line->department_name ?? ''));
                $category = trim((string) ($line->category_name ?? ''));

                if ($department === '' && $category === '') {
                    continue;
                }

                $rowCatalogPath = TrackerCategoryIdentity::label($department, $category);
                break;
            }

            $metrics[$sessionId]['catalog_path'] = $rowCatalogPath ?? '—';
        }
    }

    private static function shouldAttachTrafficMetrics(?string $focus, ?Request $request): bool
    {
        if ($request === null || $focus === 'traffic') {
            return false;
        }

        return TrackerMultiSelectFilter::requestFilled($request, 'utm_source')
            || TrackerMultiSelectFilter::requestFilled($request, 'utm_medium');
    }

    private static function shouldAttachDeviceMetric(?string $focus, ?Request $request): bool
    {
        if ($request === null || in_array($focus, ['audience', 'devices'], true)) {
            return false;
        }

        return TrackerMultiSelectFilter::requestFilled($request, 'device_type');
    }
}
