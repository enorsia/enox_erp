<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Collection;

final class EcomActivityCommerceSummary
{
    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return array{
     *     commerce_label: ?string,
     *     commerce_value: ?float,
     *     commerce_has_order: bool,
     *     commerce_display: string,
     *     commerce_tip: ?string,
     * }
     */
    public static function summarizeActions(Collection $actions): array
    {
        $latest = self::latestByStage($actions);

        if ($latest === null) {
            return self::emptySummary();
        }

        [$stage, $action] = $latest;
        $value = self::amountForStage($stage, $action);
        $label = self::labelForStage($stage);

        return [
            'commerce_label' => $label,
            'commerce_value' => $value,
            'commerce_has_order' => $stage === 'payment_success',
            'commerce_display' => self::formatDisplay($label, $value),
            'commerce_tip' => self::tipForStage($stage, $action, $value),
        ];
    }

    /**
     * Summarize commerce using only actions that match catalog drill-down filters.
     *
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @param  array<string, mixed>  $options
     */
    public static function summarizeCatalogActions(
        Collection $actions,
        array $options,
        EcomTrackerDashboardService $dashboard,
    ): array {
        $filtered = $actions->filter(function (ActivityEcomUserAction $action) use ($dashboard, $options) {
            if ($action->action_type === 'payment_success') {
                return $dashboard->paymentActionMatchesCategoryCatalog($action, $options);
            }

            return $dashboard->actionMatchesCatalogOptions($action, $options);
        });

        if ($filtered->isEmpty()) {
            return self::emptySummary();
        }

        $summary = self::summarizeActions($filtered);

        if (($summary['commerce_label'] ?? '') !== 'Order') {
            return $summary;
        }

        $payment = $filtered
            ->where('action_type', 'payment_success')
            ->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])
            ->first();

        if (! $payment instanceof ActivityEcomUserAction) {
            return $summary;
        }

        $scopedValue = $dashboard->catalogPaymentAmount($payment, $options);

        if ($scopedValue === null) {
            return self::emptySummary();
        }

        $summary['commerce_value'] = $scopedValue;
        $summary['commerce_display'] = self::formatDisplay('Order', $scopedValue);
        $summary['commerce_tip'] = self::tipForStage('payment_success', $payment, $scopedValue);

        return $summary;
    }

    public static function formatDisplay(?string $label, ?float $value): string
    {
        if ($label === null) {
            return '—';
        }

        if ($value !== null && $value > 0) {
            return $label.' · £'.number_format($value, 2);
        }

        return $label;
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return array{0: string, 1: ActivityEcomUserAction}|null
     */
    private static function latestByStage(Collection $actions): ?array
    {
        $priority = [
            'payment_success',
            'proceed_checkout',
            'begin_checkout',
            'add_to_cart',
        ];

        $grouped = $actions
            ->filter(fn (ActivityEcomUserAction $action) => in_array($action->action_type, $priority, true))
            ->groupBy('action_type');

        foreach ($priority as $stage) {
            $stageActions = $grouped->get($stage);

            if ($stageActions === null || $stageActions->isEmpty()) {
                continue;
            }

            $latest = $stageActions->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])->first();

            if ($latest instanceof ActivityEcomUserAction) {
                return [$stage, $latest];
            }
        }

        return null;
    }

    private static function labelForStage(string $stage): string
    {
        return match ($stage) {
            'payment_success' => 'Order',
            'proceed_checkout' => 'Proceed',
            'begin_checkout' => 'Checkout',
            'add_to_cart' => 'Cart',
            default => '—',
        };
    }

    private static function amountForStage(string $stage, ActivityEcomUserAction $action): ?float
    {
        if ($stage === 'payment_success') {
            $payload = is_array($action->payment_success) ? $action->payment_success : [];
            $total = 0.0;

            foreach ([$payload['amount_paid'] ?? null, $payload['checkout_info']['totals']['grand_total'] ?? null] as $candidate) {
                if (is_numeric($candidate)) {
                    $total = max($total, round((float) $candidate, 2));
                }
            }

            return $total > 0 ? $total : null;
        }

        $payloadKey = match ($stage) {
            'proceed_checkout' => 'proceed_to_checkout',
            'begin_checkout' => 'begin_checkout',
            'add_to_cart' => 'add_to_cart',
            default => null,
        };

        if ($payloadKey === null) {
            return null;
        }

        $payload = is_array($action->{$payloadKey} ?? null) ? $action->{$payloadKey} : [];
        $amount = (float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0);

        return $amount > 0 ? round($amount, 2) : null;
    }

    private static function tipForStage(string $stage, ActivityEcomUserAction $action, ?float $value): ?string
    {
        $parts = [self::labelForStage($stage)];

        if ($value !== null && $value > 0) {
            $parts[] = '£'.number_format($value, 2);
        }

        if ($stage === 'payment_success') {
            $payload = is_array($action->payment_success) ? $action->payment_success : [];
            $orderId = trim((string) ($payload['order_id'] ?? $payload['checkout_info']['order_number'] ?? ''));

            if ($orderId !== '') {
                $parts[] = 'Order #'.$orderId;
            }
        }

        $when = TrackerTime::formatFromStorage($action->created_at);

        if ($when !== null) {
            $parts[] = $when;
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @return array{
     *     commerce_label: null,
     *     commerce_value: null,
     *     commerce_has_order: false,
     *     commerce_display: string,
     *     commerce_tip: null,
     * }
     */
    private static function emptySummary(): array
    {
        return [
            'commerce_label' => null,
            'commerce_value' => null,
            'commerce_has_order' => false,
            'commerce_display' => '—',
            'commerce_tip' => null,
        ];
    }
}
