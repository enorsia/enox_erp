<?php

namespace App\Services;

use App\Support\TrackerCatalogAliasMap;
use App\Support\TrackerCatalogNameKey;
use App\Support\TrackerCatalogSentinels;
use App\Support\TrackerCategoryIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Best-effort catalog resolution for commerce line snapshots.
 */
class TrackerCatalogResolver
{
    /** @var array<string, int> */
    private array $idCache = [];

    /**
     * @param  array<string, mixed>  $line
     * @return array{
     *     tracker_department_id: int,
     *     tracker_category_id: int,
     *     tracker_product_id: int
     * }
     */
    public function resolveLineSnapshotIds(array $line): array
    {
        try {
            if (! Schema::hasTable('tracker_categories')) {
                return $this->sentinelIds();
            }

            $departmentName = TrackerCategoryIdentity::normalizeDepartmentName((string) ($line['department_name'] ?? ''));
            $categoryName = TrackerCategoryIdentity::displayName((string) ($line['category_name'] ?? ''));
            $productCode = trim((string) ($line['product_code'] ?? ''));
            $storeProductId = isset($line['product_id']) ? (int) $line['product_id'] : null;

            $departmentId = $this->resolveDepartmentId($departmentName);
            $categoryId = $this->resolveCategoryId($departmentName, $categoryName, $departmentId);
            $productId = $this->resolveProductId($productCode, $storeProductId, $line, $departmentId, $categoryId);

            return [
                'tracker_department_id' => $departmentId,
                'tracker_category_id' => $categoryId,
                'tracker_product_id' => $productId,
            ];
        } catch (Throwable $e) {
            return $this->sentinelIds();
        }
    }

    /**
     * @return array{tracker_department_id: int, tracker_category_id: int, tracker_product_id: int}
     */
    public function sentinelIds(): array
    {
        return [
            'tracker_department_id' => TrackerCatalogSentinels::DEPARTMENT_ID,
            'tracker_category_id' => TrackerCatalogSentinels::CATEGORY_ID,
            'tracker_product_id' => TrackerCatalogSentinels::PRODUCT_ID,
        ];
    }

    private function resolveDepartmentId(string $departmentName): int
    {
        if ($departmentName === '') {
            return TrackerCatalogSentinels::DEPARTMENT_ID;
        }

        $nameKey = TrackerCatalogAliasMap::resolveCanonicalNameKey(
            'department',
            '',
            TrackerCatalogNameKey::fromDisplayName($departmentName),
        );

        return $this->rememberCategoryId('department', '', $nameKey, $departmentName);
    }

    private function resolveCategoryId(string $departmentName, string $categoryName, int $departmentId): int
    {
        if ($categoryName === '') {
            return TrackerCatalogSentinels::CATEGORY_ID;
        }

        $parentKey = $departmentName !== ''
            ? TrackerCatalogNameKey::departmentParentKey($departmentName)
            : TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY;

        $nameKey = TrackerCatalogAliasMap::resolveCanonicalNameKey(
            'category',
            $parentKey,
            TrackerCatalogNameKey::fromDisplayName($categoryName),
        );

        return $this->rememberCategoryId('category', $parentKey, $nameKey, $categoryName);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveProductId(
        string $productCode,
        ?int $storeProductId,
        array $line,
        int $departmentId,
        int $categoryId,
    ): int {
        if ($productCode === '') {
            return TrackerCatalogSentinels::PRODUCT_ID;
        }

        $cacheKey = 'product:'.md5($productCode);
        if (isset($this->idCache[$cacheKey])) {
            return $this->idCache[$cacheKey];
        }

        $now = now();
        DB::table('tracker_products')->insertOrIgnore([
            'product_code' => $productCode,
            'store_product_id' => $storeProductId > 0 ? $storeProductId : null,
            'product_name' => $line['product_name'] ?? null,
            'tracker_department_id' => $departmentId,
            'tracker_category_id' => $categoryId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::table('tracker_products')->where('product_code', $productCode)->value('id');
        if ($id > 0) {
            $this->idCache[$cacheKey] = $id;
        }

        return $id > 0 ? $id : TrackerCatalogSentinels::PRODUCT_ID;
    }

    private function rememberCategoryId(string $type, string $parentKey, string $nameKey, string $displayName): int
    {
        $cacheKey = 'cat:'.md5($type.'|'.$parentKey.'|'.$nameKey);
        if (isset($this->idCache[$cacheKey])) {
            return $this->idCache[$cacheKey];
        }

        $now = now();
        DB::table('tracker_categories')->insertOrIgnore([
            'type' => $type,
            'parent_key' => $parentKey,
            'name_key' => $nameKey,
            'display_name' => $displayName !== '' ? $displayName : $nameKey,
            'code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) DB::table('tracker_categories')
            ->where('type', $type)
            ->where('parent_key', $parentKey)
            ->where('name_key', $nameKey)
            ->value('id');

        if ($id > 0) {
            $this->idCache[$cacheKey] = $id;
        }

        return $id > 0 ? $id : ($type === 'department'
            ? TrackerCatalogSentinels::DEPARTMENT_ID
            : TrackerCatalogSentinels::CATEGORY_ID);
    }
}
