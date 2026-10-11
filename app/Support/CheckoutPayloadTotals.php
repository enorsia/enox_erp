<?php

namespace App\Support;

final class CheckoutPayloadTotals
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function totals(array $payload): array
    {
        $totals = $payload['totals'] ?? [];

        if (is_array($totals) && $totals !== []) {
            return $totals;
        }

        $checkout = $payload['checkout_info'] ?? [];

        if (is_array($checkout)) {
            $nested = $checkout['totals'] ?? [];

            if (is_array($nested) && $nested !== []) {
                return $nested;
            }
        }

        return is_array($totals) ? $totals : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function grandTotal(array $payload): ?float
    {
        $totals = self::totals($payload);

        foreach (['grand_total', 'grandTotal'] as $key) {
            if (! is_numeric($totals[$key] ?? null)) {
                continue;
            }

            $value = round((float) $totals[$key], 2);

            if ($value > 0) {
                return $value;
            }
        }

        if (is_numeric($payload['cart_total'] ?? null)) {
            $cartTotal = round((float) $payload['cart_total'], 2);

            if ($cartTotal > 0) {
                return $cartTotal;
            }
        }

        if (is_numeric($payload['amount_paid'] ?? null)) {
            $amountPaid = round((float) $payload['amount_paid'], 2);

            if ($amountPaid > 0) {
                return $amountPaid;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function commerceAmount(array $payload): ?float
    {
        return self::grandTotal($payload);
    }

    /**
     * Line-item subtotal when checkout totals block is missing (common on add_to_cart).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function itemsSubtotal(array $payload): ?float
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? null;

        if (! is_array($items) || $items === []) {
            $checkout = $payload['checkout_info'] ?? [];
            $items = is_array($checkout) ? ($checkout['items'] ?? []) : [];
        }

        $sum = 0.0;

        if (is_array($items) && $items !== []) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $price = $item['price'] ?? $item['unit_price'] ?? null;

                if (! is_numeric($price)) {
                    continue;
                }

                $qty = max(1, (int) ($item['qty'] ?? 1));
                $sum += (float) $price * $qty;
            }
        } elseif (is_numeric($payload['unit_price'] ?? null)) {
            $qty = max(1, (int) ($payload['qty'] ?? 1));
            $sum = (float) $payload['unit_price'] * $qty;
        }

        $sum = round($sum, 2);

        return $sum > 0 ? $sum : null;
    }
}
