<?php

namespace App\Support;

class TrackingCtrProductResolver
{
    /**
     * Stable numeric id from style code only (no ecommerce DB lookup).
     *
     * @return array{product_id: int, style_code: string}|null
     */
    public function resolveForAction(object $action): ?array
    {
        $styleCode = $this->styleCodeFromAction($action);

        if ($styleCode === '') {
            return null;
        }

        return [
            'product_id' => $this->productIdFromStyleCode($styleCode),
            'style_code' => $styleCode,
        ];
    }

    public function styleCodeFromAction(object $action): string
    {
        $productCode = trim((string) ($action->product_code ?? ''));
        $sku = trim((string) ($action->sku ?? ''));

        if ($productCode !== '') {
            return $productCode;
        }

        if ($sku !== '') {
            return $sku;
        }

        return '';
    }

    public function productIdFromStyleCode(string $styleCode): int
    {
        $normalized = strtolower(trim($styleCode));

        if ($normalized === '') {
            return 0;
        }

        return (int) sprintf('%u', crc32($normalized));
    }
}
