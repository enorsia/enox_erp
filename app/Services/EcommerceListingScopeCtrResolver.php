<?php

namespace App\Services;

use App\Models\TrackingCtrCategory;
use App\Support\TrackerDatabase;
use Illuminate\Support\Facades\Log;

class EcommerceListingScopeCtrResolver
{
    /** Global “Shop All” static category id (Enorsia catalog). */
    private const GLOBAL_SHOP_ALL_CATEGORY_ID = 1001;

    /**
     * @param  list<string>  $productCodes
     * @return array<string, array{ctr: float, ctr_average: float, total_click: int, total_impression: int}>
     */
    public function metricsByProductCode(string $listingScope, int $listingScopeId, array $productCodes): array
    {
        $productCodes = array_values(array_unique(array_filter(array_map(
            fn (mixed $code): string => trim((string) $code),
            $productCodes,
        ))));

        if ($productCodes === []) {
            return [];
        }

        $filters = $this->resolveSummaryFilters($listingScope, $listingScopeId);
        if ($filters === null) {
            Log::warning('Ranking products: could not resolve CTR filters for listing scope.', [
                'listing_scope' => $listingScope,
                'listing_scope_id' => $listingScopeId,
            ]);

            return [];
        }

        $rows = TrackerDatabase::table('tracking_ctr_summaries')
            ->where('type', $filters['type'])
            ->where('department_id', $filters['department_id'])
            ->where('category_id', $filters['category_id'])
            ->when(
                (int) $filters['micro_category_id'] > 0,
                fn ($query) => $query->where('micro_category_id', (int) $filters['micro_category_id']),
                fn ($query) => $query->where(function ($query): void {
                    $query->where('micro_category_id', 0)->orWhereNull('micro_category_id');
                }),
            )
            ->whereIn('product_code', $productCodes)
            ->get(['product_code', 'ctr', 'ctr_average', 'total_click', 'total_impression']);

        $byCode = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row->product_code ?? ''));
            if ($code === '') {
                continue;
            }

            $byCode[$code] = [
                'ctr' => (float) ($row->ctr ?? 0),
                'ctr_average' => (float) ($row->ctr_average ?? 0),
                'total_click' => (int) ($row->total_click ?? 0),
                'total_impression' => (int) ($row->total_impression ?? 0),
            ];
        }

        return $byCode;
    }

    /**
     * @return array{type: string, department_id: int, category_id: int, micro_category_id: int}|null
     */
    public function resolveSummaryFilters(string $listingScope, int $listingScopeId): ?array
    {
        $departmentId = 0;
        $categoryId = 0;
        $microCategoryId = 0;
        $type = TrackingCtrCategory::TYPE_STATIC;

        switch ($listingScope) {
            case 'global':
                return [
                    'type' => TrackingCtrCategory::TYPE_STATIC,
                    'department_id' => 0,
                    'category_id' => $this->globalShopAllCategoryId(),
                    'micro_category_id' => 0,
                ];

            case 'department':
                $categoryId = $this->staticCategoryId($listingScopeId, 'shop-all');
                if ($categoryId === null) {
                    return null;
                }

                return [
                    'type' => TrackingCtrCategory::TYPE_STATIC,
                    'department_id' => $listingScopeId,
                    'category_id' => $categoryId,
                    'micro_category_id' => 0,
                ];

            case 'new-in':
                $categoryId = $this->staticCategoryId($listingScopeId, 'new-in');
                if ($categoryId === null) {
                    return null;
                }

                return [
                    'type' => TrackingCtrCategory::TYPE_STATIC,
                    'department_id' => $listingScopeId,
                    'category_id' => $categoryId,
                    'micro_category_id' => 0,
                ];

            case 'category':
                $categoryRow = TrackerDatabase::table('tracking_ctr_categories')
                    ->where('type', TrackingCtrCategory::TYPE_CATEGORY)
                    ->where('category_id', $listingScopeId)
                    ->orderBy('id')
                    ->first(['department_id', 'category_id']);

                if (! $categoryRow) {
                    return null;
                }

                return [
                    'type' => TrackingCtrCategory::TYPE_CATEGORY,
                    'department_id' => (int) ($categoryRow->department_id ?? 0),
                    'category_id' => (int) ($categoryRow->category_id ?? $listingScopeId),
                    'micro_category_id' => 0,
                ];

            case 'micro-category':
                $microRow = TrackerDatabase::table('tracking_ctr_categories')
                    ->where('type', TrackingCtrCategory::TYPE_MICRO_CATEGORY)
                    ->where('micro_category_id', $listingScopeId)
                    ->orderBy('id')
                    ->first(['department_id', 'category_id', 'micro_category_id']);

                if (! $microRow) {
                    return null;
                }

                return [
                    'type' => TrackingCtrCategory::TYPE_MICRO_CATEGORY,
                    'department_id' => (int) ($microRow->department_id ?? 0),
                    'category_id' => (int) ($microRow->category_id ?? 0),
                    'micro_category_id' => (int) ($microRow->micro_category_id ?? $listingScopeId),
                ];

            case 'special-department':
                return [
                    'type' => TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK,
                    'department_id' => 0,
                    'category_id' => $listingScopeId,
                    'micro_category_id' => 0,
                ];

            default:
                return null;
        }
    }

    public function listingScopeLabel(string $listingScope, int $listingScopeId): string
    {
        return match ($listingScope) {
            'global' => 'Shop All',
            'department' => 'Shop All — department '.$listingScopeId,
            'new-in' => 'New In — department '.$listingScopeId,
            'category' => 'Category '.$listingScopeId,
            'micro-category' => 'Micro category '.$listingScopeId,
            'special-department' => 'Special department '.$listingScopeId,
            default => $listingScope.' #'.$listingScopeId,
        };
    }

    private function globalShopAllCategoryId(): int
    {
        $fromDb = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_STATIC)
            ->where('department_id', 0)
            ->where('category_slug', 'shop-all')
            ->value('category_id');

        if ($fromDb !== null && (int) $fromDb > 0) {
            return (int) $fromDb;
        }

        return self::GLOBAL_SHOP_ALL_CATEGORY_ID;
    }

    private function staticCategoryId(int $departmentId, string $categorySlug): ?int
    {
        $categoryId = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_STATIC)
            ->where('department_id', $departmentId)
            ->where('category_slug', $categorySlug)
            ->value('category_id');

        if ($categoryId === null || (int) $categoryId <= 0) {
            return null;
        }

        return (int) $categoryId;
    }
}
