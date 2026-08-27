<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EcomActivityCommerceEvents
{
    private const STAGE_PRIORITY = [
        'payment_success' => 4,
        'proceed_checkout' => 3,
        'begin_checkout' => 2,
        'add_to_cart' => 1,
    ];

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @param  array<string, mixed>  $catalogOptions
     * @return list<array<string, mixed>>
     */
    public static function fromActions(
        Collection $actions,
        array $catalogOptions = [],
        ?EcomTrackerDashboardService $dashboard = null,
    ): array {
        $dashboard ??= app(EcomTrackerDashboardService::class);
        $useCatalogScope = self::usesCatalogScope($catalogOptions);

        $events = [];

        foreach ($actions->sortBy(fn (ActivityEcomUserAction $action) => [
            $action->created_at?->timestamp ?? 0,
            $action->id,
        ]) as $action) {
            if (! in_array($action->action_type, array_keys(self::STAGE_PRIORITY), true)) {
                continue;
            }

            if ($useCatalogScope && ! self::actionMatchesCatalogScope($action, $catalogOptions, $dashboard)) {
                continue;
            }

            $event = match ($action->action_type) {
                'payment_success' => self::paymentEvent($action),
                'proceed_checkout' => self::checkoutEvent($action, 'proceed_checkout', 'Proceed'),
                'begin_checkout' => self::checkoutEvent($action, 'begin_checkout', 'Checkout'),
                'add_to_cart' => self::cartEvent($action),
                default => null,
            };

            if ($event === null) {
                continue;
            }

            $events[] = $event;
        }

        $events = self::dedupePaymentEvents($events);

        return self::collapseToDisplayEvents($events);
    }

    /**
     * Keep every distinct order; otherwise only the latest funnel action.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function collapseToDisplayEvents(array $events): array
    {
        $payments = array_values(array_filter(
            $events,
            fn (array $event) => ($event['stage'] ?? '') === 'payment_success',
        ));
        $funnel = array_values(array_filter(
            $events,
            fn (array $event) => ($event['stage'] ?? '') !== 'payment_success',
        ));

        if ($payments !== []) {
            usort($payments, fn (array $left, array $right) => ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0));

            return $payments;
        }

        if ($funnel === []) {
            return [];
        }

        usort($funnel, function (array $left, array $right) {
            $leftTime = (int) ($left['sort_at'] ?? 0);
            $rightTime = (int) ($right['sort_at'] ?? 0);

            if ($leftTime !== $rightTime) {
                return $rightTime <=> $leftTime;
            }

            return (self::STAGE_PRIORITY[$right['stage']] ?? 0) <=> (self::STAGE_PRIORITY[$left['stage']] ?? 0);
        });

        return [$funnel[0]];
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    private static function usesCatalogScope(array $catalogOptions): bool
    {
        return EcomActivitySessionSort::usesCatalogActionScope($catalogOptions);
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    private static function actionMatchesCatalogScope(
        ActivityEcomUserAction $action,
        array $catalogOptions,
        EcomTrackerDashboardService $dashboard,
    ): bool {
        if ($action->action_type === 'payment_success') {
            return $dashboard->catalogPaymentHasMatchingLines($action, $catalogOptions);
        }

        return $dashboard->actionMatchesCatalogOptions($action, $catalogOptions);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function paymentEvent(ActivityEcomUserAction $action): ?array
    {
        $order = CommerceReadSupport::orderForEvent((string) $action->event_id);
        $orderId = CommerceReadSupport::orderIdForAction($action) ?: trim((string) ($order->order_id ?? ''));
        $amount = CommerceReadSupport::amountForAction($action);
        $qty = CommerceReadSupport::itemQtyForAction($action);

        if ($orderId === '' && $amount === null && $qty === 0) {
            return null;
        }

        return [
            'id' => 'payment:'.($orderId !== '' ? $orderId : (string) $action->id),
            'stage' => 'payment_success',
            'stage_label' => 'Order',
            'trigger_label' => self::orderTriggerLabel($orderId, $amount),
            'title' => 'Order details'.($orderId !== '' ? ': #'.$orderId : ''),
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'info_groups' => array_values(array_filter([
                [
                    'title' => 'Order info',
                    'fields' => array_values(array_filter([
                        $orderId !== '' ? ['label' => 'Order ID', 'value' => $orderId] : null,
                        $amount !== null ? ['label' => 'Total', 'value' => self::formatMoney($amount), 'emphasis' => true] : null,
                        $qty > 0 ? ['label' => 'Quantity', 'value' => (string) $qty] : null,
                        filled($order?->payment_method ?? null) ? ['label' => 'Payment', 'value' => (string) $order->payment_method] : null,
                        ['label' => 'Ordered', 'value' => TrackerTime::formatFromStorage($action->created_at, 'Y-m-d h:i A') ?? '—'],
                    ])),
                ],
                (filled($order?->customer_email ?? null) || filled($order?->customer_phone ?? null))
                    ? [
                        'title' => 'Customer',
                        'fields' => array_values(array_filter([
                            filled($order?->customer_phone ?? null) ? ['label' => 'Phone', 'value' => (string) $order->customer_phone] : null,
                            filled($order?->customer_email ?? null) ? ['label' => 'Email', 'value' => (string) $order->customer_email] : null,
                        ])),
                    ]
                    : null,
                [
                    'title' => 'Prices',
                    'fields' => array_values(array_filter([
                        self::moneyField('Sub total', $order?->subtotal ?? $action->commerce_subtotal ?? null),
                        self::moneyField('Delivery charge', $order?->shipping_charge ?? $action->commerce_shipping ?? null),
                        self::moneyField('Discount', $action->commerce_discount ?? null, true),
                        $amount !== null ? ['label' => 'Grand total', 'value' => self::formatMoney($amount), 'emphasis' => true] : null,
                    ])),
                ],
            ])),
            'products' => CommerceReadSupport::displayProductsForAction($action),
            'layout' => 'detail',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function checkoutEvent(ActivityEcomUserAction $action, string $stage, string $title): ?array
    {
        $amount = CommerceReadSupport::amountForAction($action);
        $itemQty = CommerceReadSupport::itemQtyForAction($action);
        $products = CommerceReadSupport::displayProductsForAction($action);

        if ($amount === null && $products === [] && $itemQty === 0) {
            return null;
        }

        $coupon = trim((string) ($action->coupon_code ?? ''));
        $discountTotal = is_numeric($action->commerce_discount ?? null) ? (float) $action->commerce_discount : 0.0;
        $shippingCost = is_numeric($action->commerce_shipping ?? null) ? (float) $action->commerce_shipping : 0.0;

        return [
            'id' => $stage.':'.$action->id,
            'stage' => $stage,
            'stage_label' => $title,
            'trigger_label' => $amount !== null ? $title.' · '.self::formatMoney($amount) : $title,
            'title' => $title,
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'layout' => 'compact',
            'cart_qty' => $itemQty,
            'cart_total' => $amount !== null ? self::formatMoney($amount) : null,
            'footer_note' => self::joinSummaryParts(array_filter([
                $coupon !== '' ? 'Coupon '.$coupon : null,
                $discountTotal > 0 ? 'Discount '.self::formatMoney($discountTotal) : null,
                $shippingCost > 0 ? 'Shipping '.self::formatMoney($shippingCost) : null,
            ])) ?: null,
            'products' => $products,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cartEvent(ActivityEcomUserAction $action): ?array
    {
        $amount = CommerceReadSupport::amountForAction($action);
        $qty = CommerceReadSupport::itemQtyForAction($action);
        $products = CommerceReadSupport::displayProductsForAction($action);

        if ($amount === null && $products === [] && $qty === 0) {
            return null;
        }

        return [
            'id' => 'cart:'.$action->id,
            'stage' => 'add_to_cart',
            'stage_label' => 'Cart',
            'trigger_label' => $amount !== null ? 'Cart · '.self::formatMoney($amount) : 'Cart',
            'title' => 'Cart details',
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'layout' => 'compact',
            'cart_qty' => $qty,
            'cart_total' => $amount !== null ? self::formatMoney($amount) : null,
            'footer_note' => null,
            'products' => $products,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function dedupePaymentEvents(array $events): array
    {
        $seen = [];
        $result = [];

        foreach ($events as $event) {
            if (($event['stage'] ?? '') !== 'payment_success') {
                $result[] = $event;

                continue;
            }

            $orderKey = preg_replace('/^payment:/', '', (string) ($event['id'] ?? ''));

            if ($orderKey !== '' && isset($seen[$orderKey])) {
                continue;
            }

            if ($orderKey !== '') {
                $seen[$orderKey] = true;
            }

            $result[] = $event;
        }

        return $result;
    }

    private static function moneyAmount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }

    private static function orderTriggerLabel(string $orderId, ?float $amount): string
    {
        $label = $orderId !== '' ? '#'.$orderId : 'Order';

        if ($amount !== null) {
            return $label.' · '.self::formatMoney($amount);
        }

        return $label;
    }

    private static function formatMoney(float $amount): string
    {
        return '£'.number_format($amount, 2);
    }

    /**
     * @param  list<string|null>  $parts
     */
    private static function joinSummaryParts(array $parts): string
    {
        return implode(' · ', array_values(array_filter($parts, fn (?string $part) => filled($part))));
    }

    /**
     * @return array{label: string, value: string, emphasis?: bool}|null
     */
    private static function moneyField(string $label, mixed $value, bool $negative = false): ?array
    {
        $amount = self::moneyAmount($value);

        if ($amount === null) {
            return null;
        }

        $formatted = self::formatMoney($amount);

        if ($negative) {
            $formatted = '- '.$formatted;
        }

        return ['label' => $label, 'value' => $formatted];
    }
}
