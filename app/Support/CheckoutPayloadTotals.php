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
}
