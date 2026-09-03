<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TrackerProductCatalogIdentity
{
    /**
     * @var array<string, int>
     */
    private const STAGE_WEIGHTS = [
        'product_view' => 10,
        'product_view_popup' => 10,
        'begin_checkout' => 8,
        'proceed_checkout' => 8,
        'payment_success' => 8,
        'add_to_cart' => 3,
        'category_view' => 1,
    ];

    /**
     * @param  Collection<int, object>  $lines
     * @param  array<string, mixed>  $catalogOptions
     * @return Collection<int, object>
     */
    public static function filterLinesMatchingCatalogOptions(Collection $lines, array $catalogOptions): Collection
    {
        $department = trim((string) ($catalogOptions['department'] ?? ''));
        $category = trim((string) ($catalogOptions['category'] ?? ''));

        if ($department === '' && $category === '') {
            return $lines;
        }

        $productCodes = $lines
            ->pluck('product_code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $canonicalByCode = self::canonicalIdentitiesForProductCodes($productCodes);

        return $lines->filter(
            fn (object $line) => self::lineMatchesCatalogOptions($line, $catalogOptions, $canonicalByCode),
        )->values();
    }

    /**
     * @param  array<string, array{department_name: string, category_name: string}>  $canonicalByCode
     */
    public static function lineMatchesCatalogOptions(
        object $line,
        array $catalogOptions,
        array $canonicalByCode = [],
    ): bool {
        $departmentFilter = trim((string) ($catalogOptions['department'] ?? ''));
        $categoryFilter = trim((string) ($catalogOptions['category'] ?? ''));

        if ($departmentFilter === '' && $categoryFilter === '') {
            return true;
        }

        [$departmentName, $categoryName] = self::resolvedIdentityForLine($line, $canonicalByCode);

        if ($departmentFilter !== '') {
            $lineDepartment = TrackerCategoryIdentity::normalizeDepartmentName($departmentName);

            if ($lineDepartment === ''
                || strcasecmp(
                    $lineDepartment,
                    TrackerCategoryIdentity::normalizeDepartmentName($departmentFilter),
                ) !== 0) {
                return false;
            }
        }

        if ($categoryFilter !== ''
            && ! TrackerCategoryIdentity::categoryMatchesFilter($categoryName, $categoryFilter)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $productCodes
     * @return array<string, array{department_name: string, category_name: string}>
     */
    public static function canonicalIdentitiesForProductCodes(array $productCodes): array
    {
        $productCodes = array_values(array_filter(array_unique(array_map(
            fn ($code) => trim((string) $code),
            $productCodes,
        ))));

        if ($productCodes === []) {
            return [];
        }

        /** @var array<string, array<string, int>> $scores */
        $scores = [];

        $rows = DB::table('activity_ecom_commerce_line_items')
            ->select('product_code', 'department_name', 'category_name', 'funnel_stage')
            ->whereIn('product_code', $productCodes)
            ->whereNotNull('department_name')
            ->where('department_name', '!=', '')
            ->whereNotNull('category_name')
            ->where('category_name', '!=', '')
            ->get();

        foreach ($rows as $row) {
            $productCode = trim((string) ($row->product_code ?? ''));

            if ($productCode === '') {
                continue;
            }

            $departmentName = TrackerCategoryIdentity::normalizeDepartmentName((string) ($row->department_name ?? ''));
            $categoryName = trim((string) ($row->category_name ?? ''));

            if ($departmentName === '' || $categoryName === '') {
                continue;
            }

            $identityKey = $departmentName.'|'.$categoryName;
            $weight = self::STAGE_WEIGHTS[(string) ($row->funnel_stage ?? '')] ?? 1;
            $scores[$productCode][$identityKey] = ($scores[$productCode][$identityKey] ?? 0) + $weight;
        }

        $canonical = [];

        foreach ($scores as $productCode => $identityScores) {
            if ($identityScores === []) {
                continue;
            }

            arsort($identityScores);
            $bestIdentity = array_key_first($identityScores);
            [$departmentName, $categoryName] = array_pad(explode('|', (string) $bestIdentity, 2), 2, '');

            if ($departmentName === '' || $categoryName === '') {
                continue;
            }

            $canonical[$productCode] = [
                'department_name' => $departmentName,
                'category_name' => $categoryName,
            ];
        }

        return $canonical;
    }

    /**
     * @param  array<string, array{department_name: string, category_name: string}>  $canonicalByCode
     * @return array{0: string, 1: string}
     */
    private static function resolvedIdentityForLine(object $line, array $canonicalByCode): array
    {
        $productCode = trim((string) ($line->product_code ?? ''));

        if ($productCode !== '' && isset($canonicalByCode[$productCode])) {
            return [
                $canonicalByCode[$productCode]['department_name'],
                $canonicalByCode[$productCode]['category_name'],
            ];
        }

        return [
            trim((string) ($line->department_name ?? '')),
            trim((string) ($line->category_name ?? '')),
        ];
    }
}
