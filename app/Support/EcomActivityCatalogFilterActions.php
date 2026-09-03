<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class EcomActivityCatalogFilterActions
{
  /**
   * @var array<string, string>
   */
    private const STAGE_LABELS = [
        'category_view' => 'Category view',
        'product_view' => 'Product view',
        'product_view_popup' => 'Popup view',
        'add_to_cart' => 'Add to cart',
        'begin_checkout' => 'Checkout',
        'proceed_checkout' => 'Proceed checkout',
        'payment_success' => 'Order',
    ];

    /**
     * @param  Collection<int, object>  $lines
     * @return list<array{label: string, detail: string|null}>
     */
    public static function fromLines(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $actions = [];

        foreach ($lines->sortBy(fn (object $line) => [
            strtotime((string) ($line->staged_at ?? '')) ?: 0,
            (int) ($line->id ?? 0),
        ]) as $line) {
            $stage = (string) ($line->funnel_stage ?? '');

            if ($stage === '' || ! isset(self::STAGE_LABELS[$stage])) {
                continue;
            }

            $detail = self::lineDetail($line, $stage);
            $signature = $stage.'|'.$detail;

            if (isset($actions[$signature])) {
                continue;
            }

            $actions[$signature] = [
                'label' => self::STAGE_LABELS[$stage],
                'detail' => $detail !== '' ? $detail : null,
            ];
        }

        return array_values($actions);
    }

    private static function lineDetail(object $line, string $stage): string
    {
        if (in_array($stage, ['category_view'], true)) {
            return '';
        }

        $productCode = trim((string) ($line->product_code ?? ''));
        $sku = trim((string) ($line->sku ?? ''));
        $productName = trim((string) ($line->product_name ?? ''));

        if ($productCode !== '') {
            return $productCode;
        }

        if ($sku !== '') {
            return $sku;
        }

        if ($productName !== '') {
            return $productName;
        }

        if ($stage === 'payment_success') {
            $orderId = trim((string) ($line->order_id ?? ''));

            return $orderId !== '' ? '#'.$orderId : '';
        }

        return '';
    }
}
