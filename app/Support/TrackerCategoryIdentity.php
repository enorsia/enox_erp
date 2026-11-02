<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class TrackerCategoryIdentity
{
    /**
     * Keep in sync with DeptWiseCategoryImageNew::DISPLAY_NAME_MAP (enorsia_v2).
     *
     * @var array<string, string>
     */
    public const DISPLAY_NAME_MAP = [
        'Co-ords and multipacks' => 'Co-ords',
        'Jumpers and Cardigans' => 'Jumpers',
        'Jumpsuits and Playsuits' => 'Jumpsuits',
    ];

    /**
     * URL department slugs under /c/{slug}/...
     *
     * @var array<string, string>
     */
    public const URL_DEPARTMENT_SLUG_MAP = [
        'men' => 'Men',
        'women' => 'Women',
        'boys' => 'Boys',
        'girls' => 'Girls',
    ];

    /**
     * @var list<string>
     */
    public const DEPARTMENTS = ['Men', 'Women', 'Boys', 'Girls'];

    public const NO_DEPARTMENT_STORED_LABEL = '(no department)';

    public const NO_DEPARTMENT_DISPLAY_LABEL = 'Custom Link';

    public static function normalizeDepartmentName(string $departmentName): string
    {
        $departmentName = trim($departmentName);

        if ($departmentName === '') {
            return '';
        }

        foreach (self::URL_DEPARTMENT_SLUG_MAP as $slug => $label) {
            if (strcasecmp($departmentName, $label) === 0 || strcasecmp($departmentName, $slug) === 0) {
                return $label;
            }
        }

        return $departmentName;
    }

    public static function formatDepartmentDisplayName(mixed $departmentName): string
    {
        $name = trim((string) ($departmentName ?? ''));
        if ($name === '') {
            return '';
        }

        if (strcasecmp($name, self::NO_DEPARTMENT_STORED_LABEL) === 0) {
            return self::NO_DEPARTMENT_DISPLAY_LABEL;
        }

        return $name;
    }

    public static function departmentNameFromPageUrl(?string $pageUrl): string
    {
        $pageUrl = trim((string) $pageUrl);

        if ($pageUrl === '') {
            return '';
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '';
        }

        if (preg_match('#/c/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            || preg_match('#/style/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            || preg_match('#^/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            || preg_match('#^/(men|women|boys|girls)-#i', $path, $matches)) {
            return self::URL_DEPARTMENT_SLUG_MAP[strtolower($matches[1])] ?? '';
        }

        return '';
    }

    /**
     * Legacy category landing URLs such as /women-everyday-essentials.
     */
    public static function categoryNameFromPageUrl(?string $pageUrl, string $departmentName = ''): string
    {
        $pageUrl = trim((string) $pageUrl);

        if ($pageUrl === '') {
            return '';
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '';
        }

        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        if (preg_match('#^(?:c|style)/(men|women|boys|girls)/([^/]+)#i', $path, $matches)) {
            return self::displayName(self::titleFromUrlSlug($matches[2]));
        }

        if (preg_match('#^(men|women|boys|girls)/([^/]+)#i', $path, $matches)) {
            return self::displayName(self::titleFromUrlSlug($matches[2]));
        }

        if (preg_match('#^(men|women|boys|girls)-(.+)$#i', $path, $matches)) {
            $slugDepartment = self::URL_DEPARTMENT_SLUG_MAP[strtolower($matches[1])] ?? '';

            if ($departmentName !== '' && strcasecmp($departmentName, $slugDepartment) !== 0) {
                return '';
            }

            return self::displayName(self::titleFromUrlSlug($matches[2]));
        }

        return '';
    }

    private static function titleFromUrlSlug(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            return '';
        }

        $slug = preg_replace('/-\d+$/', '', $slug) ?? $slug;

        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineHasCategoryIdentity(array $line): bool
    {
        return self::metaFromLine($line) !== null;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function ensureLineCategoryIdentity(array $line): array
    {
        if (self::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $departmentName = trim((string) ($line['department_name'] ?? ''));

        if ($departmentName !== '') {
            $line['category_name'] = $departmentName;
        }

        return $line;
    }

    /**
     * @param  array{department_name?: string, category_name?: string, category_code?: string, category_id?: string, page_url?: string}  $context
     */
    public static function resolveDepartmentName(array $context): string
    {
        $departmentName = trim((string) ($context['department_name'] ?? ''));

        if ($departmentName !== '') {
            return $departmentName;
        }

        return self::departmentNameFromPageUrl((string) ($context['page_url'] ?? ''));
    }

    public static function displayName(?string $categoryName): string
    {
        $name = trim((string) $categoryName);

        if ($name === '') {
            return '';
        }

        return self::DISPLAY_NAME_MAP[$name] ?? $name;
    }

    /**
     * Exact stored category_name values that match a dashboard filter label.
     *
     * @return list<string>
     */
    public static function storedCategoryNamesForFilter(string $category): array
    {
        $category = trim($category);

        if ($category === '') {
            return [];
        }

        $names = [$category];
        $display = self::displayName($category);

        if ($display !== '' && strcasecmp($display, $category) !== 0) {
            $names[] = $display;
        }

        foreach (self::DISPLAY_NAME_MAP as $stored => $mapped) {
            if (strcasecmp($mapped, $category) === 0 || strcasecmp($mapped, $display) === 0) {
                $names[] = $stored;
            }
        }

        return array_values(array_unique($names));
    }

    public static function categoryMatchesFilter(?string $storedName, string $filter): bool
    {
        $storedName = trim((string) $storedName);
        $filter = trim($filter);

        if ($filter === '') {
            return true;
        }

        if ($storedName === '') {
            return false;
        }

        if (strcasecmp(self::displayName($storedName), self::displayName($filter)) === 0) {
            return true;
        }

        foreach (self::storedCategoryNamesForFilter($filter) as $name) {
            if (strcasecmp($storedName, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     key: string,
     *     department_name: string,
     *     category_name: string,
     *     category_code: string,
     *     category_id: string,
     *     label: string
     * }
     */
    public static function meta(
        string $departmentName = '',
        string $categoryCode = '',
        string $categoryName = '',
        string $categoryId = '',
    ): array {
        $departmentName = trim($departmentName);
        $categoryCode = trim($categoryCode);
        $categoryName = self::displayName($categoryName);
        $categoryId = trim($categoryId);

        return [
            'key' => self::key($departmentName, $categoryCode, $categoryName, $categoryId),
            'department_name' => $departmentName,
            'category_name' => $categoryName,
            'category_code' => $categoryCode,
            'category_id' => $categoryId,
            'label' => self::label($departmentName, $categoryName),
        ];
    }

    public static function key(
        string $departmentName,
        string $categoryCode,
        string $categoryName,
        string $categoryId = '',
    ): string {
        $identity = $categoryCode !== ''
            ? $categoryCode
            : ($categoryId !== '' ? $categoryId : $categoryName);

        return strtolower(trim($departmentName).'|'.trim($identity));
    }

    public static function label(string $departmentName, string $categoryName): string
    {
        $departmentName = trim($departmentName);
        $categoryName = self::displayName($categoryName);

        if ($departmentName !== '' && $categoryName !== '' && strcasecmp($departmentName, $categoryName) !== 0) {
            return $departmentName.' -> '.$categoryName;
        }

        return $categoryName !== '' ? $categoryName : $departmentName;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $row
     */
    public static function lineMatchesRow(array $line, array $row): bool
    {
        $lineDepartment = trim((string) ($line['department_name'] ?? ''));
        $rowDepartment = trim((string) ($row['department_name'] ?? ''));
        $lineCode = trim((string) ($line['category_code'] ?? ''));
        $rowCode = trim((string) ($row['category_code'] ?? ''));
        $lineId = trim((string) ($line['category_id'] ?? ''));
        $rowId = trim((string) ($row['category_id'] ?? ''));
        $lineName = self::displayName((string) ($line['category_name'] ?? $line['category'] ?? ''));
        $rowName = self::displayName((string) ($row['category_name'] ?? ''));

        if ($lineDepartment !== '' && $rowDepartment !== '' && strcasecmp($lineDepartment, $rowDepartment) !== 0) {
            return false;
        }

        if ($rowId !== '' && $lineId !== '' && $rowId === $lineId) {
            return true;
        }

        if ($rowCode !== '' && $lineCode !== '' && strcasecmp($rowCode, $lineCode) === 0) {
            return true;
        }

        if ($rowName !== '' && $lineName !== '' && strcasecmp($rowName, $lineName) === 0) {
            if ($lineDepartment !== '' && $rowDepartment !== '' && strcasecmp($lineDepartment, $rowDepartment) !== 0) {
                return false;
            }

            // Name-only match: do not merge rows when only one side has a department
            // (e.g. Men Jumpers cart line vs legacy Women+Men mixed "Jumpers" row).
            if ($lineDepartment !== '' xor $rowDepartment !== '') {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{
     *     key: string,
     *     department_name: string,
     *     category_name: string,
     *     category_code: string,
     *     category_id: string,
     *     label: string
     * }|null
     */
    public static function metaFromLine(array $line): ?array
    {
        $departmentName = trim((string) ($line['department_name'] ?? ''));
        $categoryCode = trim((string) ($line['category_code'] ?? ''));
        $categoryName = trim((string) ($line['category_name'] ?? $line['category'] ?? ''));
        $categoryId = trim((string) ($line['category_id'] ?? ''));

        if ($categoryName === '' && $categoryCode === '' && $categoryId === '') {
            return null;
        }

        return self::meta($departmentName, $categoryCode, $categoryName, $categoryId);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function categoryRowHasActivity(array $row): bool
    {
        foreach (['category_views', 'product_views', 'views', 'adds', 'proceed_checkouts', 'purchases', 'sale_items'] as $field) {
            if ((int) ($row[$field] ?? 0) > 0) {
                return true;
            }
        }

        return (float) ($row['sale_amount'] ?? 0) > 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array{
     *     departments: list<string>,
     *     categories_by_department: array<string, list<string>>
     * }
     */
    public static function filterOptionsFromCategoryPerformance(array $categories): array
    {
        $pairs = [];

        foreach ($categories as $row) {
            if (! self::categoryRowHasActivity($row)) {
                continue;
            }

            $pairs[] = [
                'department_name' => (string) ($row['department_name'] ?? ''),
                'category_name' => (string) ($row['category_name'] ?? ''),
            ];
        }

        return self::filterOptionsFromPairs($pairs);
    }

    public static function departmentsForCategoryInFilterOptions(string $category, array $filterOptions): array
    {
        $category = trim($category);

        if ($category === '') {
            return [];
        }

        $matches = [];

        foreach (array_keys($filterOptions['categories_by_department'] ?? []) as $department) {
            if (self::categoryListedForDepartment($category, $department, $filterOptions)) {
                $matches[] = $department;
            }
        }

        return $matches;
    }

    public static function categoryListedForDepartment(
        string $category,
        string $department,
        array $filterOptions,
    ): bool {
        $department = self::normalizeDepartmentName($department);
        $category = trim($category);

        if ($category === '' || $department === '') {
            return false;
        }

        foreach ($filterOptions['categories_by_department'][$department] ?? [] as $listedCategory) {
            if (self::categoryMatchesFilter($listedCategory, $category)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Department/category dropdowns from pairs that actually occurred.
     * Empty storefront departments are omitted.
     *
     * @param  iterable<int, object|array<string, mixed>>  $pairs
     * @return array{
     *     departments: list<string>,
     *     categories_by_department: array<string, list<string>>
     * }
     */
    public static function filterOptionsFromPairs(iterable $pairs): array
    {
        $categoriesByDepartment = array_fill_keys(self::DEPARTMENTS, []);

        foreach ($pairs as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $department = self::normalizeDepartmentName((string) ($row['department_name'] ?? ''));

            if (! in_array($department, self::DEPARTMENTS, true)) {
                continue;
            }

            $category = self::displayName((string) ($row['category_name'] ?? $row['category'] ?? ''));

            if ($category === '' || strcasecmp($category, $department) === 0) {
                continue;
            }

            if (! in_array($category, $categoriesByDepartment[$department], true)) {
                $categoriesByDepartment[$department][] = $category;
            }
        }

        foreach ($categoriesByDepartment as $department => $categories) {
            sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            $categoriesByDepartment[$department] = array_values($categories);
        }

        $departments = array_values(array_filter(
            self::DEPARTMENTS,
            fn (string $department) => $categoriesByDepartment[$department] !== [],
        ));

        return [
            'departments' => $departments,
            'categories_by_department' => array_intersect_key(
                $categoriesByDepartment,
                array_flip($departments),
            ),
        ];
    }

    /**
     * Category id used in stored rows. A sale category saved under a department
     * uses the Custom Link row when that same name exists under (no department).
     *
     * @return array<int, int> tracking_category_id => stored category id
     */
    public static function canonicalCategoryIds(): array
    {
        $customRootId = TrackerDatabase::table('tracking_category')
            ->where('name', self::NO_DEPARTMENT_STORED_LABEL)
            ->whereNull('parent_id')
            ->value('id');

        $customByName = [];
        if ($customRootId !== null) {
            foreach (TrackerDatabase::table('tracking_category')->where('parent_id', (int) $customRootId)->get(['id', 'name']) as $row) {
                $customByName[(string) $row->name] = (int) $row->id;
            }
        }

        $map = [];
        foreach (TrackerDatabase::table('tracking_category')->get(['id', 'name']) as $row) {
            $id = (int) $row->id;
            $map[$id] = $customByName[(string) $row->name] ?? $id;
        }

        return $map;
    }

    /**
     * One primary tracking category per product for a visit day.
     * Highest tracking_session_p_cat link count wins.
     * When that count ties across departments, the style code initial picks the department: W women, M men, G girls, B boys.
     * A remaining tie keeps the lower category id.
     *
     * @return array<string, int> product code => tracking_category_id
     */
    public static function primaryCategoryIdByProductCodeForVisitDate(string $visitDate): array
    {
        $map = [];
        foreach (self::primaryCategoryByProductDayQuery($visitDate, $visitDate)->get() as $row) {
            $code = Str::limit(trim((string) $row->code), 100, '');
            if ($code === '') {
                continue;
            }

            $map[$code] = (int) $row->category_id;
        }

        return $map;
    }

    /**
     * One row per product per visit day.
     * A sale or edit category stored under Men, Women, Boys, or Girls is the same Custom Link when that name also exists under (no department).
     * Highest link count wins. A tie across departments uses the style code initial (W, M, G, B). A remaining tie keeps the lower category id.
     *
     * @param  list<int>  $categoryIds empty = every category
     */
    public static function primaryCategoryByProductDayQuery(string $fromDate, string $toDate, array $categoryIds = []): Builder
    {
        $categoryId = 'COALESCE(custom.id, sp.tracking_category_id)';
        $expectedDept = "CASE LEFT(UPPER(TRIM(p.code)), 1) WHEN 'M' THEN 'Men' WHEN 'W' THEN 'Women' WHEN 'B' THEN 'Boys' WHEN 'G' THEN 'Girls' ELSE '' END";
        $categoryDept = "CASE WHEN custom.id IS NOT NULL THEN '' WHEN dept.name IN ('Men', 'Women', 'Boys', 'Girls') THEN dept.name ELSE '' END";

        $counts = TrackerDatabase::table('tracking_session_p_cat as sp')
            ->join('tracking_product as p', 'p.id', '=', 'sp.tracking_product_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'sp.tracking_session_id')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->leftJoin('tracking_category as cat', 'cat.id', '=', 'sp.tracking_category_id')
            ->leftJoin('tracking_category as dept', 'dept.id', '=', 'cat.parent_id')
            ->leftJoin('tracking_category as custom_root', function ($join): void {
                $join->where('custom_root.name', '=', self::NO_DEPARTMENT_STORED_LABEL)
                    ->whereNull('custom_root.parent_id');
            })
            ->leftJoin('tracking_category as custom', function ($join): void {
                $join->on('custom.parent_id', '=', 'custom_root.id')
                    ->on('custom.name', '=', 'cat.name');
            })
            ->whereBetween('dv.visit_date', [$fromDate, $toDate])
            ->whereNotNull('sp.tracking_category_id')
            ->whereRaw("TRIM(p.code) <> ''")
            ->groupByRaw('LEFT(TRIM(p.code), 100), dv.visit_date, '.$categoryId.', '.$expectedDept.', '.$categoryDept)
            ->selectRaw('LEFT(TRIM(p.code), 100) as code, dv.visit_date as visit_date, '.$categoryId.' as category_id, COUNT(*) as link_count, '.$expectedDept.' as expected_dept, '.$categoryDept.' as category_dept');

        $ranked = TrackerDatabase::query()
            ->fromSub($counts, 'counts')
            ->selectRaw('code, visit_date, category_id, ROW_NUMBER() OVER (PARTITION BY code, visit_date ORDER BY link_count DESC, CASE WHEN expected_dept <> \'\' AND category_dept = expected_dept THEN 1 ELSE 0 END DESC, category_id ASC) as rn');

        $query = TrackerDatabase::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->select(['code', 'visit_date', 'category_id']);

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        return $query;
    }
}
