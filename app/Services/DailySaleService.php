<?php

namespace App\Services;

use App\Models\DailySale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class DailySaleService
{
    /**
     * Return paginated, filtered list of daily sales.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return DailySale::with(['salePlatform.parent.parent'])
            // Join 3 levels to allow platform-hierarchy ordering within each date
            ->join('sale_platforms as sp',       'sp.id',     '=', 'daily_sales.sale_platform_id')
            ->leftJoin('sale_platforms as sp_p', 'sp_p.id',   '=', 'sp.parent_id')
            ->leftJoin('sale_platforms as sp_g', 'sp_g.id',   '=', 'sp_p.parent_id')
            ->select('daily_sales.*')
            ->filter($filters)
            // PRIMARY: date DESC — keeps all records for the same date together for pagination
            ->orderByDesc('daily_sales.date')
            // SECONDARY: platform hierarchy order within each date
            ->orderByRaw('COALESCE(sp_g.sort_order, sp_p.sort_order, sp.sort_order)')
            ->orderByRaw('COALESCE(sp_p.sort_order, sp.sort_order, 0)')
            ->orderBy('sp.sort_order')
            ->orderByDesc('daily_sales.id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Build date-wise view groups for the index page.
     *
     * Structure returned:
     *   dateGroups[]
     *     date / dateFormatted / totals / entries (for edit-btn)
     *     rootGroups[]
     *       rootName  — level-1 (root) platform name
     *       subGroups[]
     *         subName  — level-2 platform name, or NULL when entries are root/level-2 owned
     *         entries  — Collection of DailySale models
     *
     * Hierarchy rule:
     *   • Record at depth 0 (root)   → rootGroup only,  subName = null
     *   • Record at depth 1 (mid)    → rootGroup only,  subName = null
     *   • Record at depth 2+ (leaf)  → rootGroup + subGroup, subName = level-2 name
     */
    public function buildDateViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): array
    {
        $dateGroups = [];

        foreach (
            $paginator->getCollection()
                ->groupBy(fn($s) => optional($s->date)->format('Y-m-d') ?? '')
                ->sortKeysDesc()
            as $date => $dateSales
        ) {
            $rootGroupsMap = [];  // [rootKey => ['rootName', 'subMap' => [subKey => [...]], 'subOrder' => []]]
            $rootOrderList = [];  // preserves first-seen order

            foreach ($dateSales as $sale) {
                $plat = $sale->salePlatform;

                // Build ancestor chain: [root, level-2?, level-3?]  (index 0 = topmost)
                $ancestors = [];
                $cur = $plat;
                while ($cur) {
                    array_unshift($ancestors, $cur);
                    $cur = $cur->parent ?? null;
                }

                $root = $ancestors[0] ?? null;
                // Mid-group only if depth >= 2 (i.e. there are ≥3 ancestors: root, mid, leaf)
                $mid  = count($ancestors) >= 3 ? $ancestors[1] : null;

                $rootKey = $root ? ('r' . $root->id) : 'r_unknown';
                $subKey  = $mid  ? ('s' . $mid->id)  : 'direct';

                if (!isset($rootGroupsMap[$rootKey])) {
                    $rootGroupsMap[$rootKey] = [
                        'rootName' => $root?->name ?? '—',
                        'subMap'   => [],
                        'subOrder' => [],
                    ];
                    $rootOrderList[] = $rootKey;
                }

                if (!isset($rootGroupsMap[$rootKey]['subMap'][$subKey])) {
                    $rootGroupsMap[$rootKey]['subMap'][$subKey] = [
                        'subName' => $mid?->name,   // null → no sub-header rendered
                        'entries' => [],
                    ];
                    $rootGroupsMap[$rootKey]['subOrder'][] = $subKey;
                }

                $rootGroupsMap[$rootKey]['subMap'][$subKey]['entries'][] = $sale;
            }

            // Flatten to plain indexed arrays so the blade needs zero PHP logic
            $rootGroups = [];
            foreach ($rootOrderList as $rk) {
                $rg = $rootGroupsMap[$rk];
                $subGroups = [];
                foreach ($rg['subOrder'] as $sk) {
                    $sg = $rg['subMap'][$sk];
                    $subGroups[] = [
                        'subName' => $sg['subName'],
                        'entries' => collect($sg['entries']),
                    ];
                }
                $rootGroups[] = [
                    'rootName'  => $rg['rootName'],
                    'subGroups' => $subGroups,
                ];
            }

            $dateGroups[] = [
                'date'          => $date,
                'dateFormatted' => \Carbon\Carbon::parse($date)->format('d M Y'),
                'totalSales'    => $dateSales->sum('sales'),
                'totalSpent'    => $dateSales->sum('spent'),
                'totalOrders'   => $dateSales->sum('number_of_orders'),
                'totalQty'      => $dateSales->sum('number_of_quantities'),
                'rootGroups'    => $rootGroups,
                'entries'       => $dateSales->values(),   // kept for Edit Date button
            ];
        }

        return $dateGroups;
    }

    /**
     * Build grouped view data (year → month → platform) with pre-computed totals.
     * All heavy aggregation is done here so the blade template remains logic-free.
     */
    public function buildViewGroups(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator, array $salePlatforms): array
    {
        $monthsMap = config('constants.months', [
            1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
            7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December',
        ]);

        $platformLookup = collect($salePlatforms)->keyBy('id');

        // DFS position index built from the already-sorted getParentOptions output
        $platformDfsIndex = array_flip(array_column($salePlatforms, 'id'));

        $allSales   = $paginator->getCollection();
        $yearGroups = [];

        foreach ($allSales->groupBy(fn($s) => optional($s->date)->year ?? 0)->sortKeysDesc() as $year => $yearSales) {
            $monthGroups = [];

            foreach ($yearSales->sortBy('date')->groupBy(fn($s) => optional($s->date)->month ?? 0)->sortKeys() as $monthNum => $monthSales) {
                $platformGroups = [];

                foreach ($monthSales->groupBy(fn($s) => $s->salePlatform?->parent_id ?? ('p'.$s->sale_platform_id)) as $groupKey => $groupSales) {
                    $parentId = is_numeric($groupKey) ? (int) $groupKey : null;

                    // Use parent's DFS index for groups, own index for standalone roots
                    $representativeId = $parentId ?? $groupSales->first()->sale_platform_id;
                    $dfsIndex         = $platformDfsIndex[$representativeId] ?? PHP_INT_MAX;

                    $platformGroups[] = [
                        '_dfsIndex'      => $dfsIndex,
                        'headerName'     => $parentId
                            ? ($platformLookup->get($parentId, [])['name'] ?? '—')
                            : ($groupSales->first()->salePlatform?->name ?? '—'),
                        'parentPlatform' => $parentId ? $platformLookup->get($parentId) : null,
                        'sales'          => $groupSales
                            // Sort child platforms within a group by their DFS order
                            ->sortBy(fn($s) => $platformDfsIndex[$s->sale_platform_id] ?? PHP_INT_MAX)
                            ->map(function ($sale) {
                                // Pre-compute all boolean flags so blade stays logic-free
                                $sale->hasGenderBreakdown = (
                                    ($sale->number_of_male_orders   ?? 0) +
                                    ($sale->number_of_female_orders ?? 0) +
                                    ($sale->number_of_kids_orders   ?? 0)
                                ) > 0;
                                return $sale;
                            }),
                    ];
                }

                // Sort platform groups by DFS order (same sequence as SalePlatformExport)
                usort($platformGroups, fn($a, $b) => $a['_dfsIndex'] <=> $b['_dfsIndex']);
                foreach ($platformGroups as &$pg) { unset($pg['_dfsIndex']); }
                unset($pg);

                $monthGroups[] = [
                    'monthNum'         => $monthNum,
                    'monthName'        => $monthsMap[$monthNum] ?? (string) $monthNum,
                    'year'             => $year,
                    'monthTotalSales'  => $monthSales->sum('sales'),
                    'monthTotalSpent'  => $monthSales->sum('spent'),
                    'monthTotalOrders' => $monthSales->sum('number_of_orders'),
                    'platformGroups'   => $platformGroups,
                ];
            }

            $yearGroups[] = [
                'year'            => $year,
                'yearTotalSales'  => $yearSales->sum('sales'),
                'yearTotalOrders' => $yearSales->sum('number_of_orders'),
                'monthGroups'     => $monthGroups,
            ];
        }

        return $yearGroups;
    }

    /**
     * Return an un-paginated query for export (respects the same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return DailySale::with('salePlatform')
            ->filter($filters)
            ->latest('date')
            ->latest('id');
    }

    /**
     * Validation rules for creating a daily sale (single record).
     */
    public function storeRules(): array
    {
        return [
            'sale_platform_id'          => 'required|exists:sale_platforms,id',
            'date'                       => 'required|date',
            'spent'                      => 'required|numeric|min:0',
            'sales'                      => 'required|numeric|min:0',
            'number_of_orders'           => 'required|integer|min:0',
            'number_of_quantities'       => 'required|integer|min:0',
            'number_of_male_orders'      => 'nullable|integer|min:0',
            'number_of_female_orders'    => 'nullable|integer|min:0',
            'number_of_kids_orders'      => 'nullable|integer|min:0',
            'number_of_male_quantities'  => 'nullable|integer|min:0',
            'number_of_female_quantities'=> 'nullable|integer|min:0',
            'number_of_kids_quantities'  => 'nullable|integer|min:0',
        ];
    }

    /**
     * Validation rules for bulk creation via the entries[] array.
     */
    public function bulkStoreRules(): array
    {
        return [
            'date'                                    => 'required|date',
            'entries'                                 => 'required|array|min:1',
            'entries.*.sale_platform_id'              => 'required|exists:sale_platforms,id',
            'entries.*.spent'                         => 'required|numeric|min:0',
            'entries.*.sales'                         => 'required|numeric|min:0',
            'entries.*.number_of_orders'              => 'required|numeric|min:0',
            'entries.*.number_of_quantities'          => 'required|numeric|min:0',
            'entries.*.number_of_male_orders'         => 'nullable|numeric|min:0',
            'entries.*.number_of_female_orders'       => 'nullable|numeric|min:0',
            'entries.*.number_of_kids_orders'         => 'nullable|numeric|min:0',
            'entries.*.number_of_male_quantities'     => 'nullable|numeric|min:0',
            'entries.*.number_of_female_quantities'   => 'nullable|numeric|min:0',
            'entries.*.number_of_kids_quantities'     => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Validation rules for bulk update via the entries[] array.
     */
    public function bulkUpdateRules(): array
    {
        return [
            'date'                                    => 'required|date',
            'entries'                                 => 'present|array',
            'entries.*.sale_platform_id'              => 'required_with:entries|exists:sale_platforms,id',
            'entries.*.spent'                         => 'required_with:entries|numeric|min:0',
            'entries.*.sales'                         => 'required_with:entries|numeric|min:0',
            'entries.*.number_of_orders'              => 'required_with:entries|numeric|min:0',
            'entries.*.number_of_quantities'          => 'required_with:entries|numeric|min:0',
            'entries.*.number_of_male_orders'         => 'nullable|numeric|min:0',
            'entries.*.number_of_female_orders'       => 'nullable|numeric|min:0',
            'entries.*.number_of_kids_orders'         => 'nullable|numeric|min:0',
            'entries.*.number_of_male_quantities'     => 'nullable|numeric|min:0',
            'entries.*.number_of_female_quantities'   => 'nullable|numeric|min:0',
            'entries.*.number_of_kids_quantities'     => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Additional uniqueness rule for store (prevents duplicate platform+date combos).
     */
    public function uniqueRule(?int $ignoreId = null): array
    {
        $rule = Rule::unique('daily_sales')->where(function ($q) {
            // combined with sale_platform_id validated separately
        });

        return [
            'sale_platform_id' => [
                'required',
                'exists:sale_platforms,id',
                Rule::unique('daily_sales', 'sale_platform_id')
                    ->where(fn($q) => $q->where('date', request('date')))
                    ->ignore($ignoreId),
            ],
        ];
    }

    /**
     * Create a new daily sale record.
     */
    public function create(array $validated): DailySale
    {
        return DailySale::create($this->normaliseNullables($validated));
    }

    /**
     * Bulk-create daily sale records for a given date.
     * Returns the list of created models.
     */
    public function bulkCreate(string $date, array $entries): array
    {
        $created = [];
        foreach ($entries as $entry) {
            $entry['date'] = $date;
            $created[]     = DailySale::create($this->normaliseNullables($entry));
        }
        return $created;
    }

    /**
     * Load all daily sale records for a specific date (with salePlatform relation).
     */
    public function getByDate(string $date): \Illuminate\Database\Eloquent\Collection
    {
        return DailySale::with('salePlatform')
            ->whereDate('date', $date)
            ->get();
    }

    /**
     * Sync all entries for a date:
     *  – Delete records whose IDs appear in $deleteIds.
     *  – Update records that have an 'id' key.
     *  – Create (or update on conflict) records that have no 'id' key.
     */
    public function syncForDate(string $date, array $entries, array $deleteIds = []): void
    {
        if (!empty($deleteIds)) {
            DailySale::where('date', $date)->whereIn('id', $deleteIds)->delete();
        }

        foreach ($entries as $entry) {
            $entry['date'] = $date;
            $data          = $this->normaliseNullables($entry);

            if (!empty($data['id'])) {
                $id   = (int) $data['id'];
                unset($data['id']);
                DailySale::where('id', $id)->where('date', $date)->update($data);
            } else {
                // Use updateOrCreate so a race condition or missed delete never
                // causes a hard DB duplicate-key error; it just overwrites silently.
                $platId = $data['sale_platform_id'];
                unset($data['id']);
                DailySale::updateOrCreate(
                    ['sale_platform_id' => $platId, 'date' => $date],
                    $data
                );
            }
        }
    }

    /**
     * Update an existing daily sale record.
     */
    public function update(DailySale $dailySale, array $validated): DailySale
    {
        $dailySale->update($this->normaliseNullables($validated));
        return $dailySale;
    }

    /**
     * Delete a daily sale record.
     */
    public function delete(DailySale $dailySale): void
    {
        $dailySale->delete();
    }

    // ── Private helpers ───────────────────────────────────────────

    private function normaliseNullables(array $data): array
    {
        // Cast integer fields (form may submit "5.0" etc.)
        $intFields = [
            'number_of_orders', 'number_of_quantities',
            'number_of_male_orders', 'number_of_female_orders', 'number_of_kids_orders',
            'number_of_male_quantities', 'number_of_female_quantities', 'number_of_kids_quantities',
        ];
        foreach ($intFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && $data[$field] !== null) {
                $data[$field] = (int) $data[$field];
            }
        }

        // Nullable int fields default to 0
        $nullableInts = [
            'number_of_male_orders', 'number_of_female_orders', 'number_of_kids_orders',
            'number_of_male_quantities', 'number_of_female_quantities', 'number_of_kids_quantities',
        ];

        foreach ($nullableInts as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        return $data;
    }
}

