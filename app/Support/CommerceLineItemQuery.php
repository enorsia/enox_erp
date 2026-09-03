<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommerceLineItemQuery
{
    /**
     * @var list<string>
     */
    public const CATALOG_FUNNEL_STAGES = [
        'category_view',
        'product_view',
        'product_view_popup',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ];

    /**
     * @param  list<string>  $funnelStages
     * @param  array<string, mixed>  $catalogOptions
     * @return Collection<int, string>
     */
    public static function sessionIds(
        Carbon $from,
        Carbon $to,
        array $catalogOptions = [],
        array $funnelStages = [],
        ?string $period = null,
    ): Collection {
        $query = DB::table('activity_ecom_commerce_line_items as li')
            ->select('li.session_id')
            ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to))
            ->distinct();

        if ($period !== null) {
            $query->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id');
            TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 's');
        }

        if ($funnelStages !== []) {
            $query->whereIn('li.funnel_stage', $funnelStages);
        }

        self::applyCatalogFilters($query, $catalogOptions, 'li');

        return $query->pluck('li.session_id')->values();
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    public static function applyCatalogFilters(Builder $query, array $catalogOptions, string $table = ''): void
    {
        $prefix = $table !== '' ? $table.'.' : '';

        $productCode = trim((string) ($catalogOptions['product_code'] ?? ''));
        if ($productCode !== '') {
            $query->where(function (Builder $inner) use ($productCode, $prefix) {
                $inner->where($prefix.'product_code', $productCode)
                    ->orWhere($prefix.'sku', $productCode);
            });
        }

        $productName = trim((string) ($catalogOptions['product_name'] ?? ''));
        if ($productName !== '') {
            self::applyProductNameFilter($query, $productName, 'and', $prefix.'product_name');
        }

        $search = trim((string) ($catalogOptions['search'] ?? ''));
        if ($search !== '' && $productCode === '' && $productName === '') {
            $searchUpper = strtoupper($search);
            $query->where(function (Builder $inner) use ($search, $searchUpper, $prefix) {
                $inner->where($prefix.'product_code', $searchUpper)
                    ->orWhere($prefix.'sku', $searchUpper);
                self::applyProductNameFilter($inner, $search, 'or', $prefix.'product_name');
            });
        }

        $category = trim((string) ($catalogOptions['category'] ?? ''));
        if ($category !== '') {
            self::applyCategoryNameFilter($query, $category, $prefix.'category_name');
        }

        $department = trim((string) ($catalogOptions['department'] ?? ''));
        if ($department !== '') {
            self::applyDepartmentFilter($query, $department, $prefix.'department_name');
        }

        $color = trim((string) ($catalogOptions['color'] ?? ''));
        if ($color !== '') {
            $query->where($prefix.'color_name', $color);
        }

        $size = trim((string) ($catalogOptions['size'] ?? ''));
        if ($size !== '') {
            $query->where($prefix.'size_name', $size);
        }
    }

    public static function applyCategoryNameFilter(
        Builder $query,
        string $category,
        string $column = 'category_name',
    ): void {
        $categoryNames = TrackerCategoryIdentity::storedCategoryNamesForFilter($category);

        if ($categoryNames === []) {
            return;
        }

        if (count($categoryNames) === 1) {
            $query->where($column, $categoryNames[0]);

            return;
        }

        $query->whereIn($column, $categoryNames);
    }

    public static function applyDepartmentFilter(
        Builder $query,
        string $department,
        string $column = 'department_name',
    ): void {
        $department = TrackerCategoryIdentity::normalizeDepartmentName($department);

        if ($department === '') {
            return;
        }

        $query->where($column, $department);
    }

    /**
     * Department/category pairs from synced commerce line items in the activity list window.
     *
     * @return Collection<int, object>
     */
    public static function categoryDepartmentPairsForRange(
        Carbon $from,
        Carbon $to,
        ?string $period = null,
    ): Collection {
        $query = DB::table('activity_ecom_commerce_line_items as li')
            ->select('li.department_name', 'li.category_name')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', TrackerTime::storageRange($from, $to))
            ->whereIn('li.funnel_stage', self::CATALOG_FUNNEL_STAGES)
            ->whereNotNull('li.department_name')
            ->where('li.department_name', '!=', '')
            ->whereNotNull('li.category_name')
            ->where('li.category_name', '!=', '')
            ->distinct();

        TrackerTime::applyEcomActivitySessionScope($query, $from, $to, $period, 's');

        return $query->get();
    }

    /**
     * BOOLEAN MODE prefix query, or null when MATCH cannot be used safely.
     */
    public static function booleanModePrefixQuery(string $term): ?string
    {
        $cleaned = trim((string) preg_replace('/[+\-><()~*"@]+/', ' ', $term));
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        if ($cleaned === '') {
            return null;
        }

        $parts = [];

        foreach (preg_split('/\s+/', $cleaned) ?: [] as $word) {
            if (strlen($word) < 3) {
                continue;
            }

            $parts[] = $word.'*';
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', $parts);
    }

    public static function applyProductNameContains(
        Builder $query,
        string $term,
        string $boolean = 'and',
        string $column = 'product_name',
    ): void {
        $like = '%'.$term.'%';
        $booleanQuery = self::booleanModePrefixQuery($term);

        if (DB::connection()->getDriverName() === 'mysql' && $booleanQuery !== null) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $query->{$method}('MATCH('.$column.') AGAINST (? IN BOOLEAN MODE)', [$booleanQuery]);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}($column, 'like', $like);
    }

    private static function applyProductNameFilter(Builder $query, string $term, string $boolean = 'and'): void
    {
        self::applyProductNameContains($query, $term, $boolean);
    }

    /**
     * @param  Collection<int|string, mixed>  $sessionIds
     * @return array<string, true>
     */
    public static function sessionIdsHavingFunnelStage(
        Collection $sessionIds,
        string $funnelStage,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        if ($sessionIds->isEmpty()) {
            return [];
        }

        $found = [];

        foreach ($sessionIds->chunk(1000) as $chunk) {
            $query = DB::table('activity_ecom_commerce_line_items')
                ->select('session_id')
                ->whereIn('session_id', $chunk->values()->all())
                ->where('funnel_stage', $funnelStage)
                ->distinct();

            if ($from instanceof Carbon && $to instanceof Carbon) {
                $query->whereBetween('staged_at', TrackerTime::storageRange($from, $to));
            }

            foreach ($query->pluck('session_id') as $id) {
                $found[(string) $id] = true;
            }
        }

        return $found;
    }
}
