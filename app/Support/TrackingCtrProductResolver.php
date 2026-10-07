<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrackingCtrProductResolver
{
    /**
     * @return array{product_id: int, style_code: string}|null
     */
    public function resolveForAction(object $action): ?array
    {
        $styleCode = $this->styleCodeFromAction($action);

        if ($styleCode === '') {
            return null;
        }

        $productId = $this->productIdForStyleCode($styleCode);

        if ($productId === null) {
            return null;
        }

        return [
            'product_id' => $productId,
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

    public function productIdForStyleCode(string $styleCode): ?int
    {
        $normalized = trim($styleCode);

        if ($normalized === '') {
            return null;
        }

        $cacheKey = 'tracker_ctr_product_id:' . strtolower($normalized);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($normalized) {
            $id = DB::table('ecommerce_products')
                ->where('sku', $normalized)
                ->value('id');

            if ($id === null) {
                return null;
            }

            return (int) $id;
        });
    }
}
