<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CommerceLineItemQuery
{
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
    ): Collection {
        $query = DB::table('activity_ecom_commerce_line_items')
            ->select('session_id')
            ->whereBetween('staged_at', TrackerTime::storageRange($from, $to))
            ->distinct();

        if ($funnelStages !== []) {
            $query->whereIn('funnel_stage', $funnelStages);
        }

        self::applyCatalogFilters($query, $catalogOptions);

        return $query->pluck('session_id')->values();
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    public static function applyCatalogFilters(Builder $query, array $catalogOptions): void
    {
        $productCode = trim((string) ($catalogOptions['product_code'] ?? ''));
        if ($productCode !== '') {
            $query->where(function (Builder $inner) use ($productCode) {
                $inner->where('product_code', $productCode)
                    ->orWhere('sku', $productCode);
            });
        }

        $productName = trim((string) ($catalogOptions['product_name'] ?? ''));
        if ($productName !== '') {
            self::applyProductNameFilter($query, $productName);
        }

        $search = trim((string) ($catalogOptions['search'] ?? ''));
        if ($search !== '' && $productCode === '' && $productName === '') {
            $searchUpper = strtoupper($search);
            $query->where(function (Builder $inner) use ($search, $searchUpper) {
                $inner->where('product_code', $searchUpper)
                    ->orWhere('sku', $searchUpper);
                self::applyProductNameFilter($inner, $search, 'or');
            });
        }

        $category = trim((string) ($catalogOptions['category'] ?? ''));
        if ($category !== '') {
            $query->where('category_name', $category);
        }

        $department = trim((string) ($catalogOptions['department'] ?? ''));
        if ($department !== '') {
            $query->where('department_name', TrackerCategoryIdentity::normalizeDepartmentName($department));
        }

        $color = trim((string) ($catalogOptions['color'] ?? ''));
        if ($color !== '') {
            $query->where('color_name', $color);
        }

        $size = trim((string) ($catalogOptions['size'] ?? ''));
        if ($size !== '') {
            $query->where('size_name', $size);
        }
    }

    private static function applyProductNameFilter(Builder $query, string $term, string $boolean = 'and'): void
    {
        $driver = DB::connection()->getDriverName();
        $like = '%'.$term.'%';

        if ($driver === 'mysql' && strlen($term) >= 3) {
            $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
            $query->{$method}('MATCH(product_name) AGAINST (? IN BOOLEAN MODE)', [$term.'*']);

            return;
        }

        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}('product_name', 'like', $like);
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
